<?php

namespace Tests\Feature;

use App\Models\BookedJob;
use App\Models\Client;
use App\Models\Inquiry;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_client_with_uuid(): void
    {
        $client = Client::factory()->create();

        $this->assertNotEmpty($client->uuid);
        $this->assertSame(36, strlen($client->uuid));
    }

    public function test_display_name_combines_partner(): void
    {
        $client = Client::factory()->create([
            'first_name' => 'Sarah',
            'last_name' => 'Lee',
            'partner_first_name' => 'James',
            'partner_last_name' => 'Lee',
        ]);

        $this->assertSame('Sarah Lee & James Lee', $client->displayName());
    }

    public function test_display_name_without_partner(): void
    {
        $client = Client::factory()->create([
            'first_name' => 'Sarah',
            'last_name' => 'Lee',
            'partner_first_name' => null,
            'partner_last_name' => null,
        ]);

        $this->assertSame('Sarah Lee', $client->displayName());
    }

    public function test_has_many_inquiries(): void
    {
        $client = Client::factory()->create();
        $inquiry = Inquiry::factory()->create(['client_id' => $client->id]);

        $this->assertTrue($inquiry->fresh()->client->is($client));
        $this->assertTrue($client->fresh()->inquiries->contains($inquiry));
    }

    public function test_client_collects_multiple_inquiries_across_events(): void
    {
        $client = Client::factory()->create();
        Inquiry::factory()->count(2)->create(['client_id' => $client->id]);

        $this->assertCount(2, $client->fresh()->inquiries);
    }

    public function test_current_booked_job_picks_soonest_upcoming_across_inquiries(): void
    {
        $client = Client::factory()->create();

        $pastInquiry = Inquiry::factory()->create(['client_id' => $client->id]);
        BookedJob::factory()->create([
            'inquiry_id' => $pastInquiry->id,
            'event_date' => now()->subYear()->toDateString(),
            'status' => 'completed',
        ]);

        $farInquiry = Inquiry::factory()->create(['client_id' => $client->id]);
        BookedJob::factory()->create([
            'inquiry_id' => $farInquiry->id,
            'event_date' => now()->addMonths(8)->toDateString(),
            'status' => 'confirmed',
        ]);

        $soonInquiry = Inquiry::factory()->create(['client_id' => $client->id]);
        $soon = BookedJob::factory()->create([
            'inquiry_id' => $soonInquiry->id,
            'event_date' => now()->addWeeks(3)->toDateString(),
            'status' => 'confirmed',
        ]);

        $this->assertTrue($client->currentBookedJob()->is($soon));
    }

    public function test_current_booked_job_ignores_cancelled_and_past(): void
    {
        $client = Client::factory()->create();
        $inquiry = Inquiry::factory()->create(['client_id' => $client->id]);
        BookedJob::factory()->create([
            'inquiry_id' => $inquiry->id,
            'event_date' => now()->addWeek()->toDateString(),
            'status' => 'cancelled',
        ]);

        $this->assertNull($client->currentBookedJob());
    }

    public function test_has_many_invoices(): void
    {
        $client = Client::factory()->create();
        Invoice::factory()->count(3)->create(['billable_type' => Client::class, 'billable_id' => $client->id]);

        $this->assertCount(3, $client->invoices);
    }

    public function test_password_is_hashed_when_set(): void
    {
        $client = Client::factory()->create(['password' => 'secret-pass']);

        $this->assertNotSame('secret-pass', $client->password);
        $this->assertTrue(Hash::check('secret-pass', $client->password));
    }

    public function test_search_scope_matches_name_and_email(): void
    {
        Client::factory()->create(['first_name' => 'Aaron', 'last_name' => 'Brown', 'email' => 'aaron@example.com']);
        Client::factory()->create(['first_name' => 'Beth', 'last_name' => 'Smith', 'email' => 'beth@example.com']);

        $this->assertSame(1, Client::search('aaron')->count());
        $this->assertSame(1, Client::search('Smith')->count());
        $this->assertSame(0, Client::search('zzzz')->count());
    }

    public function test_soft_deletes(): void
    {
        $client = Client::factory()->create();
        $client->delete();

        $this->assertSoftDeleted($client);
        $this->assertSame(0, Client::count());
        $this->assertSame(1, Client::withTrashed()->count());
    }
}
