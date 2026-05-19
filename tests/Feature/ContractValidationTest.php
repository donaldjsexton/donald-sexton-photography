<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_rejects_invoice_belonging_to_another_billable(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $invoice = Invoice::factory()->create([
            'billable_type' => Client::class,
            'billable_id' => $otherClient->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.contracts.create'))
            ->post(route('admin.contracts.store'), [
                'billable_type' => 'client',
                'billable_id' => $client->id,
                'invoice_id' => $invoice->id,
                'title' => 'Test',
                'body' => 'Body',
                'issue_date' => '2026-05-01',
            ])
            ->assertSessionHasErrors('invoice_id');
    }

    public function test_store_rejects_invoice_belonging_to_venue_when_client_selected(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();
        $venue = Venue::factory()->create();
        $invoice = Invoice::factory()->create([
            'billable_type' => Venue::class,
            'billable_id' => $venue->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.contracts.create'))
            ->post(route('admin.contracts.store'), [
                'billable_type' => 'client',
                'billable_id' => $client->id,
                'invoice_id' => $invoice->id,
                'title' => 'Test',
                'body' => 'Body',
                'issue_date' => '2026-05-01',
            ])
            ->assertSessionHasErrors('invoice_id');
    }

    public function test_store_accepts_invoice_owned_by_same_billable(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.contracts.store'), [
                'billable_type' => 'client',
                'billable_id' => $client->id,
                'invoice_id' => $invoice->id,
                'title' => 'Test',
                'body' => 'Body',
                'issue_date' => '2026-05-01',
            ])
            ->assertSessionHasNoErrors();
    }
}
