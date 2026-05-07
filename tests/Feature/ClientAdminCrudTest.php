<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Inquiry;
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

    public function test_show_displays_client_with_invoices_section(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create(['first_name' => 'Sarah', 'last_name' => 'Lee']);

        $response = $this->actingAs($admin)->get(route('admin.clients.show', $client));

        $response->assertOk();
        $response->assertSee('Sarah Lee');
        $response->assertSee('Invoices');
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

        $client = Client::where('inquiry_id', $inquiry->id)->first();
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
        $inquiry = Inquiry::factory()->create();
        $existing = Client::factory()->create(['inquiry_id' => $inquiry->id]);

        $response = $this->actingAs($admin)
            ->post(route('admin.clients.convert-from-inquiry', $inquiry));

        $response->assertRedirect(route('admin.clients.show', $existing));
        $this->assertSame(1, Client::where('inquiry_id', $inquiry->id)->count());
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

        $client = Client::where('inquiry_id', $inquiry->id)->first();
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
        $inquiry = Inquiry::factory()->create();
        Client::factory()->create([
            'inquiry_id' => $inquiry->id,
            'first_name' => 'Sarah',
            'last_name' => 'Lee',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.inquiries.edit', $inquiry));

        $response->assertOk();
        $response->assertDontSee('Convert to Client');
        $response->assertSee('View Client');
        $response->assertSee('Sarah Lee');
    }
}
