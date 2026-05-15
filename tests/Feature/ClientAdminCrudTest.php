<?php

namespace Tests\Feature;

use App\Models\BookedJob;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Inquiry;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAdminCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_auth(): void
    {
        $response = $this->get(route('admin.clients.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_index_lists_clients(): void
    {
        $admin = User::factory()->create();
        Client::factory()->create(['first_name' => 'Aaron', 'last_name' => 'Brown']);
        Client::factory()->create(['first_name' => 'Beth', 'last_name' => 'Smith']);

        $response = $this->actingAs($admin)->get(route('admin.clients.index'));

        $response->assertOk();
        $response->assertSee('Aaron Brown');
        $response->assertSee('Beth Smith');
    }

    public function test_index_search_filters_results(): void
    {
        $admin = User::factory()->create();
        Client::factory()->create(['first_name' => 'Aaron', 'last_name' => 'Brown']);
        Client::factory()->create(['first_name' => 'Beth', 'last_name' => 'Smith']);

        $response = $this->actingAs($admin)->get(route('admin.clients.index', ['search' => 'aaron']));

        $response->assertOk();
        $response->assertSee('Aaron Brown');
        $response->assertDontSee('Beth Smith');
    }

    public function test_create_displays_form(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.clients.create'));

        $response->assertOk();
        $response->assertSee('New Client');
    }

    public function test_store_creates_client(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.clients.store'), [
            'first_name' => 'Sarah',
            'last_name' => 'Lee',
            'email' => 'sarah@example.com',
            'phone' => '555-1234',
            'country' => 'US',
        ]);

        $client = Client::where('email', 'sarah@example.com')->first();
        $this->assertNotNull($client);
        $response->assertRedirect(route('admin.clients.show', $client));
        $this->assertSame('Sarah', $client->first_name);
        $this->assertNotEmpty($client->uuid);
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.clients.store'), [
            'last_name' => 'NoFirstName',
        ]);

        $response->assertSessionHasErrors(['first_name', 'email']);
    }

    public function test_show_displays_client_profile_sections(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create(['first_name' => 'Sarah', 'last_name' => 'Lee']);

        $response = $this->actingAs($admin)->get(route('admin.clients.show', $client));

        $response->assertOk();
        $response->assertSee('Sarah Lee');
        $response->assertSee('Client since');
        $response->assertSee('Activity');
    }

    public function test_show_lists_multiple_events_and_their_billing(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create(['first_name' => 'Sarah', 'last_name' => 'Lee']);

        $firstInquiry = Inquiry::factory()->booked()->create([
            'client_id' => $client->id,
            'event_date' => '2026-09-11',
        ]);
        $firstJob = BookedJob::factory()->create([
            'inquiry_id' => $firstInquiry->id,
            'event_date' => '2026-09-11',
            'summary' => 'Sarah & James — Wedding',
        ]);
        Contract::factory()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'booked_job_id' => $firstJob->id,
        ]);
        Invoice::factory()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'booked_job_id' => $firstJob->id,
            'total_cents' => 500000,
            'amount_paid_cents' => 500000,
            'status' => Invoice::STATUS_PAID,
        ]);

        $secondInquiry = Inquiry::factory()->create([
            'client_id' => $client->id,
            'event_date' => '2027-06-15',
            'event_type' => 'family',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.clients.show', $client));

        $response->assertOk();
        // Both inquiries surface as activity-feed entries.
        $this->assertSame(2, substr_count($response->getContent(), 'New inquiry'));
        // The booked job appears in the Bookings strip…
        $response->assertSee('Wedding');
        // …and the paid invoice rolls up into the Billed stat (whole dollars).
        $response->assertSee('$5,000');
    }

    public function test_update_persists_changes(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create(['first_name' => 'Sarah', 'phone' => null]);

        $response = $this->actingAs($admin)->put(route('admin.clients.update', $client), [
            'first_name' => 'Sarah',
            'last_name' => 'Lee',
            'email' => $client->email,
            'phone' => '555-9999',
            'country' => 'US',
        ]);

        $response->assertRedirect(route('admin.clients.show', $client));
        $this->assertSame('555-9999', $client->fresh()->phone);
    }

    public function test_destroy_soft_deletes_client(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $response = $this->actingAs($admin)->delete(route('admin.clients.destroy', $client));

        $response->assertRedirect(route('admin.clients.index'));
        $this->assertSoftDeleted($client);
    }

    public function test_convert_from_inquiry_creates_linked_client(): void
    {
        $admin = User::factory()->create();
        $inquiry = Inquiry::factory()->create([
            'primary_name' => 'Sarah Lee',
            'partner_name' => 'James Lee',
            'email' => 'sarah@example.com',
            'phone' => '555-1234',
            'location_city' => 'Tampa',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.clients.convert-from-inquiry', $inquiry));

        $client = $inquiry->fresh()->client;
        $this->assertNotNull($client);
        $response->assertRedirect(route('admin.clients.show', $client));
        $this->assertSame('Sarah', $client->first_name);
        $this->assertSame('Lee', $client->last_name);
        $this->assertSame('James', $client->partner_first_name);
        $this->assertSame('Lee', $client->partner_last_name);
        $this->assertSame('sarah@example.com', $client->email);
        $this->assertSame('Tampa', $client->city);
    }

    public function test_convert_from_inquiry_redirects_when_client_already_exists(): void
    {
        $admin = User::factory()->create();
        $existing = Client::factory()->create();
        $inquiry = Inquiry::factory()->create(['client_id' => $existing->id]);

        $response = $this->actingAs($admin)
            ->post(route('admin.clients.convert-from-inquiry', $inquiry));

        $response->assertRedirect(route('admin.clients.show', $existing));
        $this->assertSame(1, Client::count());
    }

    public function test_convert_handles_single_word_primary_name(): void
    {
        $admin = User::factory()->create();
        $inquiry = Inquiry::factory()->create([
            'primary_name' => 'Madonna',
            'partner_name' => null,
            'email' => 'madonna@example.com',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.clients.convert-from-inquiry', $inquiry))
            ->assertRedirect();

        $client = $inquiry->fresh()->client;
        $this->assertNotNull($client);
        $this->assertSame('Madonna', $client->first_name);
        $this->assertNull($client->last_name);
        $this->assertNull($client->partner_first_name);
    }

    public function test_inquiry_edit_shows_convert_button_when_no_client(): void
    {
        $admin = User::factory()->create();
        $inquiry = Inquiry::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.inquiries.edit', $inquiry));

        $response->assertOk();
        $response->assertSee('Convert to Client');
    }

    public function test_inquiry_edit_shows_view_link_when_client_exists(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create(['first_name' => 'Sarah', 'last_name' => 'Lee']);
        $inquiry = Inquiry::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($admin)->get(route('admin.inquiries.edit', $inquiry));

        $response->assertOk();
        $response->assertDontSee('Convert to Client');
        $response->assertSee('View Client');
        $response->assertSee('Sarah Lee');
    }
}
