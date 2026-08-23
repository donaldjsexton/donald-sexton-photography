<?php

namespace Tests\Feature;

use App\Models\BookedJob;
use App\Models\Client;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\BookedJobSync;
use App\Services\GoogleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class InquiryEventTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /**
     * Stub out Google only where a test touches the calendar — binding it
     * globally breaks the public pages that render Google reviews.
     */
    private function withoutGoogleCalendar(): void
    {
        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('calendar')->andReturn(null);
        $this->app->instance(GoogleClient::class, $googleClient);
    }

    public function test_the_public_form_does_not_preselect_a_type(): void
    {
        $response = $this->get(route('inquiry.create'))->assertOk();

        $response->assertDontSee('value="wedding" selected', false);
        $response->assertSee('Please choose', false);
    }

    public function test_the_public_form_rejects_a_type_outside_the_vocabulary(): void
    {
        $this->post(route('inquiry.store'), $this->inquiryPayload(['event_type' => 'quinceañera-ish']))
            ->assertSessionHasErrors('event_type');
    }

    public function test_a_family_shoot_can_be_submitted_as_a_family_shoot(): void
    {
        $this->post(route('inquiry.store'), $this->inquiryPayload(['event_type' => 'family']));

        $this->assertDatabaseHas('inquiries', [
            'email' => 'lead@example.com',
            'event_type' => 'family',
        ]);
    }

    public function test_admin_can_correct_a_mislabelled_event_type(): void
    {
        $inquiry = Inquiry::factory()->create(['status' => 'active', 'event_type' => 'wedding']);

        $this->actingAs(User::factory()->create())
            ->put(route('admin.inquiries.update', $inquiry), [
                'status' => 'active',
                'event_type' => 'family',
            ]);

        $this->assertSame('family', $inquiry->fresh()->event_type);
    }

    public function test_correcting_the_type_rewrites_the_booked_job_summary(): void
    {
        $this->withoutGoogleCalendar();

        $inquiry = Inquiry::factory()->booked()->create([
            'event_type' => 'wedding',
            'primary_name' => 'Dana Reyes',
            'partner_name' => null,
            'event_date' => '2027-04-04',
        ]);

        $job = (new BookedJobSync)->syncFromInquiry($inquiry);
        $this->assertSame('Dana Reyes — Wedding', $job->summary);

        $this->actingAs(User::factory()->create())
            ->put(route('admin.inquiries.update', $inquiry), [
                'status' => 'booked',
                'event_type' => 'family',
                'event_date' => '2027-04-04',
            ]);

        $job->refresh();

        $this->assertSame('family', $job->event_type);
        $this->assertSame('Dana Reyes — Family', $job->summary);
    }

    public function test_the_booked_job_records_its_own_event_type(): void
    {
        $this->withoutGoogleCalendar();

        $inquiry = Inquiry::factory()->booked()->family()->create(['event_date' => '2027-07-07']);

        $job = (new BookedJobSync)->syncFromInquiry($inquiry);

        $this->assertSame('family', $job->event_type);
        $this->assertSame('Family', $job->eventTypeLabel());
    }

    public function test_summary_falls_back_when_there_is_no_type_or_name(): void
    {
        $this->assertSame('Booked Job', BookedJob::buildSummary(null, null));
        $this->assertSame('Dana Reyes', BookedJob::buildSummary('Dana Reyes', null));
        $this->assertSame('Family', BookedJob::buildSummary(null, 'family'));
    }

    public function test_an_unstated_type_is_unknown_rather_than_a_wedding(): void
    {
        $inquiry = Inquiry::factory()->create(['event_type' => null]);

        $this->assertFalse($inquiry->isWedding());
        $this->assertSame('Not provided', $inquiry->eventTypeLabel());

        $this->actingAs(User::factory()->create())
            ->post(route('admin.inquiries.questionnaire.generate', $inquiry));

        $this->assertDatabaseMissing('wedding_questionnaires', ['inquiry_id' => $inquiry->id]);
    }

    public function test_legacy_free_text_types_still_render_a_label(): void
    {
        $inquiry = Inquiry::factory()->make(['event_type' => 'destination_wedding']);

        $this->assertSame('Destination Wedding', $inquiry->eventTypeLabel());
    }

    public function test_a_non_wedding_inquiry_is_not_offered_the_wedding_questionnaire(): void
    {
        $inquiry = Inquiry::factory()->family()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.inquiries.edit', $inquiry))
            ->assertOk()
            ->assertDontSee('Generate Questionnaire Link');
    }

    public function test_generating_a_wedding_questionnaire_for_a_family_shoot_is_refused(): void
    {
        $inquiry = Inquiry::factory()->family()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.inquiries.questionnaire.generate', $inquiry))
            ->assertRedirect(route('admin.inquiries.edit', $inquiry));

        $this->assertDatabaseMissing('wedding_questionnaires', ['inquiry_id' => $inquiry->id]);
    }

    public function test_a_wedding_inquiry_still_gets_a_questionnaire(): void
    {
        $inquiry = Inquiry::factory()->create(['event_type' => 'wedding']);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.inquiries.questionnaire.generate', $inquiry));

        $this->assertDatabaseHas('wedding_questionnaires', ['inquiry_id' => $inquiry->id]);
    }

    public function test_sending_a_questionnaire_to_a_non_wedding_client_is_refused(): void
    {
        $client = Client::factory()->create();
        Inquiry::factory()->family()->create(['client_id' => $client->id]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.clients.questionnaire', $client))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('wedding_questionnaires', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function inquiryPayload(array $overrides = []): array
    {
        return array_merge([
            'primary_name' => 'Dana Reyes',
            'email' => 'lead@example.com',
            'event_type' => 'family',
            'message' => 'Looking for photos with our kids.',
        ], $overrides);
    }
}
