<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_auto_assigns_uuid(): void
    {
        $payment = Payment::factory()->create();

        $this->assertNotEmpty($payment->uuid);
    }

    public function test_payment_belongs_to_invoice_and_optional_installment(): void
    {
        $invoice = Invoice::factory()->create();
        $installment = InvoiceInstallment::factory()->create(['invoice_id' => $invoice->id]);

        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'invoice_installment_id' => $installment->id,
        ]);

        $this->assertTrue($payment->invoice->is($invoice));
        $this->assertTrue($payment->installment->is($installment));
    }

    public function test_factory_states_set_gateway_and_completed_status(): void
    {
        $square = Payment::factory()->square()->completed()->create();
        $paypal = Payment::factory()->paypal()->create();

        $this->assertSame(Payment::GATEWAY_SQUARE, $square->gateway);
        $this->assertTrue($square->isCompleted());
        $this->assertNotNull($square->received_at);
        $this->assertSame(Payment::GATEWAY_PAYPAL, $paypal->gateway);
        $this->assertStringStartsWith('pp_', $paypal->gateway_payment_id);
    }

    public function test_payload_is_cast_to_array(): void
    {
        $payment = Payment::factory()->create([
            'payload' => ['squareId' => 'abc', 'amount' => 1000],
        ]);

        $this->assertIsArray($payment->fresh()->payload);
        $this->assertSame('abc', $payment->fresh()->payload['squareId']);
    }
}
