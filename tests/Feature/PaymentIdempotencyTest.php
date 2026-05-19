<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_blocks_duplicate_gateway_payment_id(): void
    {
        $invoice = Invoice::factory()->sent()->create(['total_cents' => 50000]);

        Payment::factory()->completed()->create([
            'invoice_id' => $invoice->id,
            'gateway' => Payment::GATEWAY_SQUARE,
            'gateway_payment_id' => 'sq_dup',
        ]);

        $this->expectException(QueryException::class);

        Payment::factory()->completed()->create([
            'invoice_id' => $invoice->id,
            'gateway' => Payment::GATEWAY_SQUARE,
            'gateway_payment_id' => 'sq_dup',
        ]);
    }

    public function test_multiple_null_gateway_payment_ids_are_allowed(): void
    {
        $invoice = Invoice::factory()->sent()->create(['total_cents' => 50000]);

        Payment::factory()->completed()->create([
            'invoice_id' => $invoice->id,
            'gateway' => Payment::GATEWAY_MANUAL,
            'gateway_payment_id' => null,
        ]);
        Payment::factory()->completed()->create([
            'invoice_id' => $invoice->id,
            'gateway' => Payment::GATEWAY_MANUAL,
            'gateway_payment_id' => null,
        ]);

        $this->assertSame(2, Payment::query()->where('invoice_id', $invoice->id)->count());
    }

    public function test_record_payment_rejects_existing_gateway_reference(): void
    {
        $admin = User::factory()->create();
        $invoice = Invoice::factory()->sent()->create([
            'total_cents' => 50000,
            'amount_paid_cents' => 10000,
        ]);
        Payment::factory()->completed()->create([
            'invoice_id' => $invoice->id,
            'gateway' => Payment::GATEWAY_SQUARE,
            'gateway_payment_id' => 'sq_existing',
            'amount_cents' => 10000,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.invoices.payments.store', $invoice), [
                'amount' => 400.00,
                'gateway' => Payment::GATEWAY_SQUARE,
                'gateway_payment_id' => 'sq_existing',
            ])
            ->assertRedirect(route('admin.invoices.show', $invoice));

        $this->assertSame(1, Payment::query()->where('gateway_payment_id', 'sq_existing')->count());
    }

    public function test_find_by_gateway_payment_id_returns_null_for_empty(): void
    {
        $this->assertNull(Payment::findByGatewayPaymentId(Payment::GATEWAY_SQUARE, null));
        $this->assertNull(Payment::findByGatewayPaymentId(Payment::GATEWAY_SQUARE, ''));
    }
}
