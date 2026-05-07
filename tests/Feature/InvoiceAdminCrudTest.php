<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceAdminCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_auth(): void
    {
        $this->get(route('admin.invoices.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_index_lists_invoices_and_filters_by_status(): void
    {
        $admin = User::factory()->create();
        $draft = Invoice::factory()->create();
        $sent = Invoice::factory()->sent()->create();

        $this->actingAs($admin)
            ->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSee($draft->number)
            ->assertSee($sent->number);

        $this->actingAs($admin)
            ->get(route('admin.invoices.index', ['status' => 'sent']))
            ->assertOk()
            ->assertSee($sent->number)
            ->assertDontSee($draft->number);
    }

    public function test_create_prefills_client_when_query_present(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.invoices.create', ['client_id' => $client->id]))
            ->assertOk()
            ->assertSee($client->displayName());
    }

    public function test_store_creates_invoice_with_line_items_and_recalculates_totals(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.invoices.store'), [
            'client_id' => $client->id,
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'default_tax_rate' => 7,
            'discount' => 0,
            'line_items' => [
                ['description' => 'Coverage', 'quantity' => 1, 'unit_price' => 5000.00, 'tax_rate' => 0],
                ['description' => 'Album', 'quantity' => 2, 'unit_price' => 250.00, 'tax_rate' => 7],
            ],
        ]);

        $invoice = Invoice::first();
        $this->assertNotNull($invoice);
        $response->assertRedirect(route('admin.invoices.show', $invoice));

        $this->assertSame($client->id, $invoice->client_id);
        $this->assertCount(2, $invoice->lineItems);
        $this->assertSame(550000, $invoice->subtotal_cents);
        $this->assertSame(3500, $invoice->tax_cents);
        $this->assertSame(553500, $invoice->total_cents);
    }

    public function test_store_creates_installments(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)->post(route('admin.invoices.store'), [
            'client_id' => $client->id,
            'issue_date' => '2026-05-01',
            'discount' => 0,
            'line_items' => [
                ['description' => 'Coverage', 'quantity' => 1, 'unit_price' => 1000.00],
            ],
            'installments' => [
                ['label' => 'Retainer', 'due_date' => '2026-05-15', 'amount' => 500.00],
                ['label' => 'Balance', 'due_date' => '2026-08-01', 'amount' => 500.00],
            ],
        ])->assertRedirect();

        $invoice = Invoice::first();
        $this->assertCount(2, $invoice->installments);
        $this->assertSame(50000, $invoice->installments[0]->amount_cents);
        $this->assertSame('Retainer', $invoice->installments[0]->label);
    }

    public function test_store_validates_at_least_one_line_item(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.invoices.store'), [
                'client_id' => $client->id,
                'issue_date' => '2026-05-01',
            ])
            ->assertSessionHasErrors(['line_items']);
    }

    public function test_update_replaces_line_items_for_draft_invoice(): void
    {
        $admin = User::factory()->create();
        $invoice = Invoice::factory()->create();
        $invoice->lineItems()->create([
            'sort_order' => 0,
            'description' => 'Old',
            'quantity' => 1,
            'unit_price_cents' => 10000,
            'tax_rate' => 0,
        ]);

        $this->actingAs($admin)->put(route('admin.invoices.update', $invoice), [
            'client_id' => $invoice->client_id,
            'issue_date' => '2026-05-01',
            'discount' => 0,
            'line_items' => [
                ['description' => 'Replaced', 'quantity' => 1, 'unit_price' => 200.00, 'tax_rate' => 0],
            ],
        ])->assertRedirect(route('admin.invoices.show', $invoice));

        $invoice->refresh()->load('lineItems');
        $this->assertCount(1, $invoice->lineItems);
        $this->assertSame('Replaced', $invoice->lineItems[0]->description);
        $this->assertSame(20000, $invoice->total_cents);
    }

    public function test_update_blocked_for_sent_invoice(): void
    {
        $admin = User::factory()->create();
        $invoice = Invoice::factory()->sent()->create();

        $response = $this->actingAs($admin)->put(route('admin.invoices.update', $invoice), [
            'client_id' => $invoice->client_id,
            'issue_date' => '2026-05-01',
            'discount' => 0,
            'line_items' => [
                ['description' => 'Hack', 'quantity' => 1, 'unit_price' => 1, 'tax_rate' => 0],
            ],
        ]);

        $response->assertRedirect(route('admin.invoices.show', $invoice));
        $this->assertSame(0, $invoice->fresh()->lineItems()->count());
    }

    public function test_send_marks_draft_invoice_as_sent(): void
    {
        $admin = User::factory()->create();
        $invoice = Invoice::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.invoices.send', $invoice))
            ->assertRedirect(route('admin.invoices.show', $invoice));

        $this->assertSame(Invoice::STATUS_SENT, $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->sent_at);
    }

    public function test_void_marks_invoice_as_void(): void
    {
        $admin = User::factory()->create();
        $invoice = Invoice::factory()->sent()->create();

        $this->actingAs($admin)
            ->post(route('admin.invoices.void', $invoice))
            ->assertRedirect(route('admin.invoices.show', $invoice));

        $this->assertSame(Invoice::STATUS_VOID, $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->voided_at);
    }

    public function test_destroy_only_works_for_drafts(): void
    {
        $admin = User::factory()->create();
        $draft = Invoice::factory()->create();
        $sent = Invoice::factory()->sent()->create();

        $this->actingAs($admin)
            ->delete(route('admin.invoices.destroy', $draft))
            ->assertRedirect(route('admin.invoices.index'));
        $this->assertSoftDeleted($draft);

        $this->actingAs($admin)
            ->delete(route('admin.invoices.destroy', $sent))
            ->assertRedirect(route('admin.invoices.show', $sent));
        $this->assertNotSoftDeleted($sent);
    }

    public function test_record_payment_marks_invoice_paid_and_updates_installment(): void
    {
        $admin = User::factory()->create();
        $invoice = Invoice::factory()->sent()->create([
            'total_cents' => 50000,
        ]);
        $installment = InvoiceInstallment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount_cents' => 50000,
        ]);

        $this->actingAs($admin)->post(route('admin.invoices.payments.store', $invoice), [
            'amount' => 500.00,
            'gateway' => Payment::GATEWAY_MANUAL,
            'invoice_installment_id' => $installment->id,
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertSame(50000, $invoice->amount_paid_cents);
        $this->assertSame(InvoiceInstallment::STATUS_PAID, $installment->fresh()->status);
        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_record_payment_validates_installment_belongs_to_invoice(): void
    {
        $admin = User::factory()->create();
        $invoice = Invoice::factory()->sent()->create();
        $other = InvoiceInstallment::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.invoices.payments.store', $invoice), [
                'amount' => 100,
                'gateway' => Payment::GATEWAY_MANUAL,
                'invoice_installment_id' => $other->id,
            ])
            ->assertSessionHasErrors(['invoice_installment_id']);
    }

    public function test_show_displays_invoice_with_payments_section(): void
    {
        $admin = User::factory()->create();
        $invoice = Invoice::factory()->sent()->create();
        $invoice->lineItems()->create([
            'sort_order' => 0,
            'description' => 'Coverage',
            'quantity' => 1,
            'unit_price_cents' => 10000,
            'tax_rate' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee('Coverage')
            ->assertSee('Payments');
    }
}
