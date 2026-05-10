<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.mode' => 'sandbox',
            'payments.gateways.paypal' => [
                'enabled' => true,
                'sandbox' => [
                    'client_id' => 'sandbox-client-id',
                    'client_secret' => 'sandbox-client-secret',
                    'webhook_id' => 'sandbox-webhook-id',
                ],
                'live' => [],
            ],
        ]);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'TOKEN'], 200),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'FAILURE'], 200),
        ]);

        $this->postJson(route('webhooks.paypal'), ['event_type' => 'PAYMENT.CAPTURE.COMPLETED'], $this->signatureHeaders())
            ->assertStatus(401);
    }

    public function test_capture_completed_event_marks_payment_completed_and_invoice_paid(): void
    {
        $invoice = Invoice::factory()->sent()->create([
            'total_cents' => 50000,
            'amount_paid_cents' => 0,
        ]);
        $payment = Payment::factory()->paypal()->create([
            'invoice_id' => $invoice->id,
            'gateway_payment_id' => 'PP-CAP-1',
            'status' => Payment::STATUS_PENDING,
            'amount_cents' => 50000,
        ]);

        $this->mockSuccessfulVerification();

        $payload = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'PP-CAP-1',
                'status' => 'COMPLETED',
            ],
        ];

        $this->postJson(route('webhooks.paypal'), $payload, $this->signatureHeaders())
            ->assertOk();

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status);
        $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()->status);
    }

    public function test_capture_denied_event_marks_payment_failed(): void
    {
        $payment = Payment::factory()->paypal()->create([
            'gateway_payment_id' => 'PP-CAP-DECLINED',
            'status' => Payment::STATUS_PENDING,
            'amount_cents' => 10000,
        ]);

        $this->mockSuccessfulVerification();

        $this->postJson(route('webhooks.paypal'), [
            'event_type' => 'PAYMENT.CAPTURE.DENIED',
            'resource' => ['id' => 'PP-CAP-DECLINED', 'status' => 'DECLINED'],
        ], $this->signatureHeaders())
            ->assertOk();

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
    }

    public function test_unknown_event_type_is_acknowledged(): void
    {
        $payment = Payment::factory()->paypal()->create([
            'gateway_payment_id' => 'PP-CAP-NOISE',
            'status' => Payment::STATUS_PENDING,
        ]);

        $this->mockSuccessfulVerification();

        $this->postJson(route('webhooks.paypal'), [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'whatever'],
        ], $this->signatureHeaders())
            ->assertOk();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_unknown_payment_id_in_capture_event_is_acked_without_changes(): void
    {
        $this->mockSuccessfulVerification();

        $this->postJson(route('webhooks.paypal'), [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'PP-CAP-NOTFOUND', 'status' => 'COMPLETED'],
        ], $this->signatureHeaders())
            ->assertOk();

        $this->assertSame(0, Payment::count());
    }

    private function mockSuccessfulVerification(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'TOKEN'], 200),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS'], 200),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function signatureHeaders(): array
    {
        return [
            'Paypal-Auth-Algo' => 'SHA256withRSA',
            'Paypal-Cert-Url' => 'https://example.test/cert',
            'Paypal-Transmission-Id' => 'tx-1',
            'Paypal-Transmission-Sig' => 'sig',
            'Paypal-Transmission-Time' => '2026-05-10T00:00:00Z',
        ];
    }
}
