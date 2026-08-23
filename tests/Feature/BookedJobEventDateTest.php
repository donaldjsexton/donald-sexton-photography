<?php

namespace Tests\Feature;

use App\Models\BookedJob;
use App\Models\Contract;
use App\Models\Inquiry;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\User;
use App\Services\BookedJobSync;
use App\Services\GoogleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BookedJobEventDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('calendar')->andReturn(null);
        $this->app->instance(GoogleClient::class, $googleClient);
    }

    public function test_an_inquiry_can_be_booked_before_a_date_is_agreed(): void
    {
        $inquiry = Inquiry::factory()->create(['status' => 'new', 'event_date' => null]);

        $this->actingAs(User::factory()->create())
            ->put(route('admin.inquiries.update', $inquiry), ['status' => 'booked'])
            ->assertRedirect(route('admin.inquiries.edit', $inquiry));

        $job = BookedJob::query()->where('inquiry_id', $inquiry->id)->first();

        $this->assertNotNull($job);
        $this->assertNull($job->event_date);
        $this->assertTrue($job->isAwaitingDate());
    }

    public function test_admin_can_set_the_event_date_from_the_inquiry_screen(): void
    {
        $inquiry = Inquiry::factory()->booked()->create(['event_date' => null]);
        $job = (new BookedJobSync)->syncFromInquiry($inquiry);

        $this->actingAs(User::factory()->create())
            ->put(route('admin.inquiries.update', $inquiry), [
                'status' => 'booked',
                'event_date' => '2027-06-19',
            ]);

        $this->assertSame('2027-06-19', $inquiry->fresh()->event_date->toDateString());
        $this->assertSame('2027-06-19', $job->fresh()->event_date->toDateString());
    }

    public function test_admin_can_change_the_event_date_on_the_booked_job(): void
    {
        $job = BookedJob::factory()->create(['event_date' => '2027-05-01']);

        $this->actingAs(User::factory()->create())
            ->put(route('admin.booked-jobs.update', $job), [
                'status' => 'confirmed',
                'event_date' => '2027-05-08',
            ])
            ->assertRedirect(route('admin.booked-jobs.show', $job));

        $this->assertSame('2027-05-08', $job->fresh()->event_date->toDateString());
    }

    public function test_a_resync_does_not_revert_a_date_the_studio_set_by_hand(): void
    {
        $inquiry = Inquiry::factory()->booked()->create(['event_date' => '2027-01-01']);
        $sync = new BookedJobSync;

        $job = $sync->syncFromInquiry($inquiry);
        $job->update(['event_date' => '2027-03-03']);

        $sync->syncFromInquiry($inquiry->fresh());

        $this->assertSame('2027-03-03', $job->fresh()->event_date->toDateString());
    }

    public function test_confirmation_stage_is_tentative_until_a_contract_is_signed(): void
    {
        $job = BookedJob::factory()->create();
        Contract::factory()->sent()->create(['booked_job_id' => $job->id]);

        $this->assertSame('tentative', $job->confirmationStage());
        $this->assertFalse($job->isDateLocked());
    }

    public function test_confirmation_stage_is_held_once_signed_but_unpaid(): void
    {
        $job = BookedJob::factory()->create();
        Contract::factory()->signed()->create(['booked_job_id' => $job->id]);

        $invoice = Invoice::factory()->create(['booked_job_id' => $job->id]);
        InvoiceInstallment::factory()->create(['invoice_id' => $invoice->id, 'sequence' => 1]);

        $this->assertSame('held', $job->confirmationStage());
        $this->assertTrue($job->isDateLocked());
    }

    public function test_confirmation_stage_is_confirmed_once_signed_and_retainer_paid(): void
    {
        $job = BookedJob::factory()->create();
        Contract::factory()->signed()->create(['booked_job_id' => $job->id]);

        $invoice = Invoice::factory()->create(['booked_job_id' => $job->id]);
        InvoiceInstallment::factory()->paid()->create(['invoice_id' => $invoice->id, 'sequence' => 1]);

        $this->assertSame('confirmed', $job->confirmationStage());
    }

    public function test_a_signed_contract_locks_the_date_against_an_ordinary_edit(): void
    {
        $job = BookedJob::factory()->create(['event_date' => '2027-05-01']);
        Contract::factory()->signed()->create(['booked_job_id' => $job->id]);

        $this->actingAs(User::factory()->create())
            ->put(route('admin.booked-jobs.update', $job), [
                'status' => 'confirmed',
                'event_date' => '2027-05-08',
                'location' => 'New Venue',
            ]);

        $job->refresh();

        $this->assertSame('2027-05-01', $job->event_date->toDateString());
        $this->assertSame('New Venue', $job->location);
    }

    public function test_a_locked_date_can_still_be_moved_through_an_explicit_reschedule(): void
    {
        $job = BookedJob::factory()->create(['event_date' => '2027-05-01']);
        $contract = Contract::factory()->signed()->create([
            'booked_job_id' => $job->id,
            'signed_at' => now()->subDay(),
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('admin.booked-jobs.reschedule', $job), [
                'event_date' => '2027-05-08',
                'reschedule_reason' => 'Venue double-booked the original date.',
            ])
            ->assertRedirect(route('admin.booked-jobs.show', $job));

        $job->refresh();

        $this->assertSame('2027-05-08', $job->event_date->toDateString());
        $this->assertSame('2027-05-01', $job->previous_event_date->toDateString());
        $this->assertNotNull($job->rescheduled_at);
        $this->assertSame('Venue double-booked the original date.', $job->reschedule_reason);
        $this->assertTrue($job->contractsNeedingAmendment()->contains($contract));
    }

    public function test_reschedule_requires_a_reason(): void
    {
        $job = BookedJob::factory()->create(['event_date' => '2027-05-01']);

        $this->actingAs(User::factory()->create())
            ->put(route('admin.booked-jobs.reschedule', $job), ['event_date' => '2027-05-08'])
            ->assertSessionHasErrors('reschedule_reason');

        $this->assertSame('2027-05-01', $job->fresh()->event_date->toDateString());
    }

    public function test_dateless_jobs_are_listed_as_awaiting_a_date_and_kept_off_upcoming(): void
    {
        $awaiting = BookedJob::factory()->create(['event_date' => null]);
        $dated = BookedJob::factory()->create(['event_date' => now()->addMonth()]);

        $this->assertTrue(BookedJob::awaitingDate()->get()->contains($awaiting));
        $this->assertFalse(BookedJob::upcoming()->get()->contains($awaiting));
        $this->assertTrue(BookedJob::upcoming()->get()->contains($dated));
    }

    public function test_the_calendar_page_renders_with_a_dateless_job(): void
    {
        BookedJob::factory()->create(['event_date' => null]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.booked-jobs.index'))
            ->assertOk()
            ->assertSee('Awaiting a Date');
    }

    public function test_the_booked_job_page_renders_without_a_date(): void
    {
        $job = BookedJob::factory()->create(['event_date' => null]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.booked-jobs.show', $job))
            ->assertOk()
            ->assertSee('Date to be decided');
    }
}
