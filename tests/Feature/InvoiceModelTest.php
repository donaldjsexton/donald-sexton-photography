<?php

namespace Tests\Feature;

use App\Models\BookedJob;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\InvoiceLineItem;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_auto_assigns_uuid_and_number(): void
    {
        $invoice = Invoice::factory()->create();

        $this->assertNotEmpty($invoice->uuid);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{4}$/', $invoice->number);
    }

    public function test_invoice_numbers_are_sequential_per_year(): void
    {
        $a = Invoice::factory()->create();
        $b = Invoice::factory()->create();
        $c = Invoice::factory()->create();

        $this->assertSame(['0001', '0002', '0003'], [
            substr($a->number, -4),
            substr($b->number, -4),
            substr($c->number, -4),
        ]);
    }

    public function test_belongs_to_client_and_optional_booked_job(): void
    {
        $client = Client::factory()->create();
        $bookedJob = BookedJob::factory()->create();
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'booked_job_id' => $bookedJob->id,
        ]);

        $this->assertTrue($invoice->client->is($client));
        $this->assertTrue($invoice->bookedJob->is($bookedJob));
        $this->assertTrue($client->invoices->first()->is($invoice));
        $this->assertTrue($bookedJob->invoices->first()->is($invoice));
    }

    public function test_line_item_recomputes_totals_on_save(): void
    {
        $invoice = Invoice::factory()->create();
        $item = InvoiceLineItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 2,
            'unit_price_cents' => 12500,
            'tax_rate' => 7.0,
        ]);

        $this->assertSame(25000, $item->subtotal_cents);
        $this->assertSame(1750, $item->tax_cents);
        $this->assertSame(26750, $item->total_cents);
    }

    public function test_recalculate_totals_aggregates_line_items_and_discount(): void
    {
        $invoice = Invoice::factory()->create(['discount_cents' => 1000]);

        InvoiceLineItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price_cents' => 50000,
            'tax_rate' => 0,
        ]);
        InvoiceLineItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 2,
            'unit_price_cents' => 10000,
            'tax_rate' => 7.0,
        ]);

        $invoice->refresh()->recalculateTotals();

        $this->assertSame(70000, $invoice->subtotal_cents);
        $this->assertSame(1400, $invoice->tax_cents);
        $this->assertSame(70000 - 1000 + 1400, $invoice->total_cents);
    }

    public function test_amount_due_is_total_minus_paid(): void
    {
        $invoice = Invoice::factory()->create([
            'total_cents' => 100000,
            'amount_paid_cents' => 30000,
        ]);

        $this->assertSame(70000, $invoice->amountDueCents());
    }

    public function test_sync_status_marks_paid_when_payments_cover_total(): void
    {
        $invoice = Invoice::factory()->sent()->create(['total_cents' => 50000]);

        Payment::factory()->completed()->create([
            'invoice_id' => $invoice->id,
            'amount_cents' => 50000,
        ]);

        $invoice->syncStatusFromPayments();

        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertSame(50000, $invoice->amount_paid_cents);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_sync_status_marks_partially_paid(): void
    {
        $invoice = Invoice::factory()->sent()->create(['total_cents' => 50000]);

        Payment::factory()->completed()->create([
            'invoice_id' => $invoice->id,
            'amount_cents' => 20000,
        ]);

        $invoice->syncStatusFromPayments();

        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->status);
        $this->assertSame(20000, $invoice->amount_paid_cents);
    }

    public function test_void_status_is_preserved_through_sync(): void
    {
        $invoice = Invoice::factory()->void()->create(['total_cents' => 50000]);

        Payment::factory()->completed()->create([
            'invoice_id' => $invoice->id,
            'amount_cents' => 50000,
        ]);

        $invoice->syncStatusFromPayments();

        $this->assertSame(Invoice::STATUS_VOID, $invoice->status);
    }

    public function test_outstanding_scope_filters_correctly(): void
    {
        Invoice::factory()->create(['status' => Invoice::STATUS_DRAFT]);
        Invoice::factory()->sent()->create();
        Invoice::factory()->paid()->create();
        Invoice::factory()->create(['status' => Invoice::STATUS_PARTIALLY_PAID]);

        $this->assertSame(2, Invoice::outstanding()->count());
    }

    public function test_installment_amount_due_and_overdue_detection(): void
    {
        $invoice = Invoice::factory()->create();
        $installment = InvoiceInstallment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount_cents' => 10000,
            'amount_paid_cents' => 4000,
            'due_date' => now()->subDay(),
        ]);

        $this->assertSame(6000, $installment->amountDueCents());
        $this->assertTrue($installment->isOverdue());
    }
}
