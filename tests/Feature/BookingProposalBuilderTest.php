<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingProposalBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_requires_auth(): void
    {
        $client = Client::factory()->create();

        $this->get(route('admin.proposals.create', ['client_id' => $client->id]))
            ->assertRedirect(route('admin.login'));
    }

    public function test_create_renders_form_prefilled_for_client(): void
    {
        $admin = User::factory()->create();
        ContractTemplate::factory()->default()->create();
        $client = Client::factory()->create(['first_name' => 'Sarah', 'last_name' => 'Lee']);

        $this->actingAs($admin)
            ->get(route('admin.proposals.create', ['client_id' => $client->id]))
            ->assertOk()
            ->assertSee('New Booking Proposal')
            ->assertSee('Sarah Lee');
    }

    public function test_store_creates_linked_draft_contract_and_invoice(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.proposals.store'), [
                'client_id' => $client->id,
                'title' => 'Wedding Agreement',
                'body' => 'Full terms here.',
                'issue_date' => now()->toDateString(),
                'expires_at' => now()->addDays(14)->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'default_tax_rate' => 0,
                'discount' => 0,
                'line_items' => [
                    ['description' => 'Wedding coverage', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0],
                ],
            ])
            ->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $contract = Contract::firstOrFail();

        $this->assertSame(Client::class, $invoice->billable_type);
        $this->assertSame($client->id, $invoice->billable_id);
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertSame(500000, $invoice->total_cents);
        $this->assertCount(1, $invoice->lineItems);

        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertSame($invoice->id, $contract->invoice_id);
        $this->assertSame($client->id, $contract->billable_id);
        $this->assertTrue($contract->isProposal());
    }

    public function test_store_persists_installments(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.proposals.store'), [
                'client_id' => $client->id,
                'title' => 'Wedding Agreement',
                'body' => 'Full terms.',
                'issue_date' => now()->toDateString(),
                'line_items' => [
                    ['description' => 'Coverage', 'quantity' => 1, 'unit_price' => 4000, 'tax_rate' => 0],
                ],
                'installments' => [
                    ['label' => 'Deposit', 'due_date' => now()->toDateString(), 'amount' => 1000],
                    ['label' => 'Balance', 'due_date' => now()->addMonth()->toDateString(), 'amount' => 3000],
                ],
            ])
            ->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertCount(2, $invoice->installments);
        $this->assertSame(100000, $invoice->installments->firstWhere('label', 'Deposit')->amount_cents);
    }

    public function test_store_validation_errors(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.proposals.store'), [
                'title' => 'No client, no body, no items',
                'issue_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors(['client_id', 'body', 'line_items']);

        $this->assertSame(0, Contract::count());
        $this->assertSame(0, Invoice::count());
    }

    public function test_store_rolls_back_when_contract_creation_is_not_reached(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.proposals.store'), [
                'client_id' => 999999,
                'title' => 'Bad client',
                'body' => 'Terms.',
                'issue_date' => now()->toDateString(),
                'line_items' => [
                    ['description' => 'Coverage', 'quantity' => 1, 'unit_price' => 1000],
                ],
            ])
            ->assertSessionHasErrors('client_id');

        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, Contract::count());
    }
}
