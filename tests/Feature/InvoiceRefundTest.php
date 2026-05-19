<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_partially_refund_a_payment(): void
    {
        $admin = User::factory()->create();
        $invoice = Invoice::factory()->paid()->create([
            'total_cents' => 50000,
            'amount_paid_cents' => 50000,
        ]);
        $payment = Payment::factory()->completed()->create([
            'invoice_id' => $invoice->id,
            'amount_cents' => 50000,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.invoices.payments.refund', ['invoice' => $invoice, 'payment' => $payment]), [
                'amount' => 100.00,
                'reason' => 'Partial refund for cancelled add-on',
            ])
            ->assertRedirect(route('admin.invoices.show', $invoice));

        $payment->refresh();
        $invoice->refresh();

        $this->assertSame(10000, $payment->refunded_amount_cents);
        $this->assertSame(Payment::STATUS_PARTIALLY_REFUNDED, $payment->status);
        $this->assertSame(40000, $invoice->amount_paid_cents);
        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->status);
    }

    public function test_full_refund_marks_payment_refunded_and_invoice_refunded(): void
    {
        $admin = User::factory()->create();
        $invoice = Invoice::factory()->paid()->create([
            'total_cents' => 50000,
            'amount_paid_cents' => 50000,
        ]);
        $payment = Payment::factory()->completed()->create([
            'invoice_id' => $invoice->id,
            'amount_cents' => 50000,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.invoices.payments.refund', ['invoice' => $invoice, 'payment' => $payment]), [
                'amount' => 500.00,
            ])
            ->assertRedirect(route('admin.invoices.show', $invoice));

        $payment->refresh();
        $invoice->refresh();

        $this->assertSame(50000, $payment->refunded_amount_cents);
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->status);
        $this->assertSame(Invoice::STATUS_REFUNDED, $invoice->status);
    }

    public function test_refund_exceeding_remaining_balance_is_rejected(): void
    {
        $admin = User::factory()->create();
        $invoice = Invoice::factory()->paid()->create([
            'total_cents' => 50000,
            'amount_paid_cents' => 50000,
        ]);
        $payment = Payment::factory()->completed()->create([
            'invoice_id' => $invoice->id,
            'amount_cents' => 50000,
            'refunded_amount_cents' => 40000,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.invoices.show', $invoice))
            ->post(route('admin.invoices.payments.refund', ['invoice' => $invoice, 'payment' => $payment]), [
                'amount' => 200.00,
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(40000, $payment->fresh()->refunded_amount_cents);
    }

    public function test_refund_404s_when_payment_belongs_to_other_invoice(): void
    {
        $admin = User::factory()->create();
        $invoice = Invoice::factory()->paid()->create(['total_cents' => 50000, 'amount_paid_cents' => 50000]);
        $otherInvoice = Invoice::factory()->paid()->create(['total_cents' => 50000, 'amount_paid_cents' => 50000]);
        $payment = Payment::factory()->completed()->create([
            'invoice_id' => $otherInvoice->id,
            'amount_cents' => 50000,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.invoices.payments.refund', ['invoice' => $invoice, 'payment' => $payment]), [
                'amount' => 100.00,
            ])
            ->assertNotFound();
    }
}
