<?php

namespace Tests\Feature;

use App\Models\BookedJob;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Inquiry;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalDashboardBookingBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_upcoming_badge_for_future_booking(): void
    {
        [$client, $job] = $this->makeClientWithBooking(
            eventDate: now()->addWeeks(2),
            status: 'confirmed',
        );

        $this->actingAs($client, 'client')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Your booking')
            ->assertSee('Upcoming')
            ->assertSee($job->summary);
    }

    public function test_dashboard_shows_tentative_badge_for_a_distant_booking_with_no_signed_contract(): void
    {
        [$client] = $this->makeClientWithBooking(
            eventDate: now()->addMonths(6),
            status: 'confirmed',
        );

        $this->actingAs($client, 'client')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Tentative');
    }

    public function test_dashboard_shows_confirmed_badge_once_contract_signed_and_retainer_paid(): void
    {
        [$client, $job] = $this->makeClientWithBooking(
            eventDate: now()->addMonths(6),
            status: 'confirmed',
        );

        Contract::factory()->signed()->create(['booked_job_id' => $job->id]);
        $invoice = Invoice::factory()->create([
            'booked_job_id' => $job->id,
            'billable_type' => Client::class,
            'billable_id' => $client->id,
        ]);
        InvoiceInstallment::factory()->paid()->create([
            'invoice_id' => $invoice->id,
            'sequence' => 1,
        ]);

        $this->actingAs($client, 'client')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Confirmed');
    }

    public function test_dashboard_shows_today_badge_when_event_is_today(): void
    {
        [$client] = $this->makeClientWithBooking(
            eventDate: now(),
            status: 'confirmed',
        );

        $this->actingAs($client, 'client')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Today');
    }

    public function test_dashboard_hides_booking_when_cancelled(): void
    {
        [$client, $job] = $this->makeClientWithBooking(
            eventDate: now()->addWeeks(2),
            status: 'cancelled',
        );

        $this->actingAs($client, 'client')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertDontSee('Your booking')
            ->assertDontSee($job->summary);
    }

    public function test_dashboard_hides_booking_after_event_date_has_passed(): void
    {
        [$client] = $this->makeClientWithBooking(
            eventDate: now()->subDays(7),
            status: 'confirmed',
        );

        $this->actingAs($client, 'client')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertDontSee('Your booking');
    }

    public function test_portal_stage_returns_completed_for_status_completed(): void
    {
        $job = BookedJob::factory()->make([
            'status' => 'completed',
            'event_date' => now()->addMonths(2),
        ]);

        $this->assertSame('Completed', $job->portalStage());
    }

    /**
     * @return array{0: Client, 1: BookedJob}
     */
    private function makeClientWithBooking(Carbon $eventDate, string $status): array
    {
        $client = Client::factory()->withPortalAccess()->create();
        $inquiry = Inquiry::factory()->booked()->create([
            'client_id' => $client->id,
            'event_date' => $eventDate->copy(),
        ]);
        $job = BookedJob::factory()->create([
            'inquiry_id' => $inquiry->id,
            'event_date' => $eventDate->copy(),
            'status' => $status,
            'summary' => 'Sarah & James — Wedding',
        ]);

        return [$client, $job];
    }
}
