<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\PortalActivity;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_records_a_portal_activity_for_the_client(): void
    {
        $client = Client::factory()->create([
            'email' => 'sarah@example.com',
            'password' => 'secret-pass',
        ]);

        $this->post(route('portal.login.store'), [
            'email' => 'sarah@example.com',
            'password' => 'secret-pass',
        ])->assertRedirect(route('portal.dashboard'));

        $activity = PortalActivity::where('actor_type', Client::class)
            ->where('actor_id', $client->id)
            ->where('type', PortalActivity::TYPE_LOGIN)
            ->first();

        $this->assertNotNull($activity);
        $this->assertNotNull($activity->ip_address);
        $this->assertNull($activity->subject_id);
    }

    public function test_failed_login_records_no_activity(): void
    {
        Client::factory()->create([
            'email' => 'sarah@example.com',
            'password' => 'secret-pass',
        ]);

        $this->post(route('portal.login.store'), [
            'email' => 'sarah@example.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');

        $this->assertSame(0, PortalActivity::count());
    }

    public function test_venue_login_records_a_portal_activity_for_the_venue(): void
    {
        $venue = Venue::factory()->billable()->create([
            'billing_email' => 'billing@example.test',
            'password' => Hash::make('venue-pass'),
        ]);

        $this->post(route('portal.login.store'), [
            'email' => 'billing@example.test',
            'password' => 'venue-pass',
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertTrue(
            PortalActivity::where('actor_type', Venue::class)
                ->where('actor_id', $venue->id)
                ->where('type', PortalActivity::TYPE_LOGIN)
                ->exists()
        );
    }

    public function test_each_contract_view_is_logged(): void
    {
        $client = Client::factory()->create();
        $contract = Contract::factory()->sent()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
        ]);

        $this->actingAs($client, 'client')
            ->get(route('portal.contracts.show', ['contract' => $contract->uuid]))
            ->assertOk();
        $this->actingAs($client, 'client')
            ->get(route('portal.contracts.show', ['contract' => $contract->uuid]))
            ->assertOk();

        $this->assertSame(2, PortalActivity::where('type', PortalActivity::TYPE_CONTRACT_VIEWED)
            ->where('subject_type', Contract::class)
            ->where('subject_id', $contract->id)
            ->count());
    }

    public function test_each_invoice_view_is_logged(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->sent()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
        ]);

        $this->actingAs($client, 'client')
            ->get(route('portal.invoices.show', ['invoice' => $invoice->uuid]))
            ->assertOk();
        $this->actingAs($client, 'client')
            ->get(route('portal.invoices.show', ['invoice' => $invoice->uuid]))
            ->assertOk();

        $this->assertSame(2, PortalActivity::where('type', PortalActivity::TYPE_INVOICE_VIEWED)
            ->where('subject_type', Invoice::class)
            ->where('subject_id', $invoice->id)
            ->count());
    }

    public function test_admin_client_profile_shows_login_and_view_activity(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create(['password' => 'secret']);
        $contract = Contract::factory()->sent()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
        ]);

        PortalActivity::factory()->create([
            'actor_type' => Client::class,
            'actor_id' => $client->id,
            'type' => PortalActivity::TYPE_LOGIN,
        ]);
        PortalActivity::factory()->contractViewed()->create([
            'actor_type' => Client::class,
            'actor_id' => $client->id,
            'subject_type' => Contract::class,
            'subject_id' => $contract->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.clients.show', $client))
            ->assertOk()
            ->assertSee('Signed in to portal')
            ->assertSee('Contract viewed')
            ->assertSee('1 sign-in');
    }
}
