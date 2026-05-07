<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SquareWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.mode' => 'sandbox',
            'payments.gateways.square' => [
                'enabled' => true,
                'sandbox' => [
                    'access_token' => 'token',
                    'application_id' => 'app',
                    'location_id' => 'loc',
                    'webhook_signature_key' => 'webhook-key',
                ],
                'live' => [],
            ],
        ]);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $response = $this->postJson(route('webhooks.square'), ['type' => 'payment.updated'], [
            'X-Square-Hmacsha256-Signature' => 'wrong',
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_accepts_valid_signature(): void
    {
        $payload = ['type' => 'payment.updated', 'data' => ['object' => ['payment' => ['id' => 'unknown']]]];
        $body = json_encode($payload);

        $signature = $this->signature($body, route('webhooks.square'));

        $this->call(
            method: 'POST',
            uri: route('webhooks.square'),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SQUARE_HMACSHA256_SIGNATURE' => $signature],
            content: $body,
        )->assertOk();
    }

    public function test_payment_updated_marks_existing_payment_completed_and_invoice_paid(): void
    {
        $invoice = Invoice::factory()->sent()->create([
            'total_cents' => 50000,
            'amount_paid_cents' => 0,
        ]);
        $payment = Payment::factory()->square()->create([
            'invoice_id' => $invoice->id,
            'gateway_payment_id' => 'sq-pay-789',
            'status' => Payment::STATUS_PENDING,
            'amount_cents' => 50000,
        ]);

        $payload = [
            'type' => 'payment.updated',
            'data' => [
                'object' => [
                    'payment' => [
                        'id' => 'sq-pay-789',
                        'status' => 'COMPLETED',
                    ],
                ],
            ],
        ];
        $body = json_encode($payload);

        $this->call(
            method: 'POST',
            uri: route('webhooks.square'),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SQUARE_HMACSHA256_SIGNATURE' => $this->signature($body, route('webhooks.square'))],
            content: $body,
        )->assertOk();

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status);
        $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()->status);
    }

    public function test_refund_completed_event_marks_payment_refunded(): void
    {
        $invoice = Invoice::factory()->paid()->create([
            'total_cents' => 50000,
            'amount_paid_cents' => 50000,
        ]);
        $payment = Payment::factory()->square()->completed()->create([
            'invoice_id' => $invoice->id,
            'gateway_payment_id' => 'sq-pay-refund',
            'amount_cents' => 50000,
        ]);

        $payload = [
            'type' => 'refund.updated',
            'data' => [
                'object' => [
                    'refund' => [
                        'payment_id' => 'sq-pay-refund',
                        'status' => 'COMPLETED',
                        'amount_money' => ['amount' => 50000, 'currency' => 'USD'],
                    ],
                ],
            ],
        ];
        $body = json_encode($payload);

        $this->call(
            method: 'POST',
            uri: route('webhooks.square'),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SQUARE_HMACSHA256_SIGNATURE' => $this->signature($body, route('webhooks.square'))],
            content: $body,
        )->assertOk();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->status);
        $this->assertSame(50000, $payment->refunded_amount_cents);
        $this->assertNotNull($payment->refunded_at);
    }

    public function test_unknown_event_type_is_acknowledged_without_changes(): void
    {
        $payment = Payment::factory()->square()->create([
            'gateway_payment_id' => 'sq-pay-noise',
            'status' => Payment::STATUS_PENDING,
        ]);

        $payload = ['type' => 'order.fulfillment.updated', 'data' => ['object' => []]];
        $body = json_encode($payload);

        $this->call(
            method: 'POST',
            uri: route('webhooks.square'),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SQUARE_HMACSHA256_SIGNATURE' => $this->signature($body, route('webhooks.square'))],
            content: $body,
        )->assertOk();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    private function signature(string $body, string $url): string
    {
        return base64_encode(hash_hmac('sha256', $url.$body, 'webhook-key', true));
    }
}
