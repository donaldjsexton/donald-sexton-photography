<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\SiteSetting;
use App\Services\Payments\SquareGateway;
use App\Services\Payments\SquareTokenRefresher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Square\Exceptions\SquareApiException;
use Square\Payments\PaymentsClient;
use Square\SquareClient;
use Square\Types\CreatePaymentResponse;
use Square\Types\Money;
use Square\Types\Payment;
use Tests\TestCase;

class SquareGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_is_configured_returns_false_when_disabled(): void
    {
        config([
            'payments.gateways.square.enabled' => false,
            'payments.mode' => 'sandbox',
        ]);

        $this->assertFalse((new SquareGateway)->isConfigured());
    }

    public function test_is_configured_returns_true_when_credentials_present(): void
    {
        $this->primeSandboxConfig();

        $this->assertTrue((new SquareGateway)->isConfigured());
    }

    public function test_application_and_location_pull_from_active_mode(): void
    {
        $this->primeSandboxConfig();

        $gateway = new SquareGateway;

        $this->assertSame('sandbox-app-id', $gateway->applicationId());
        $this->assertSame('sandbox-loc-id', $gateway->locationId());
        $this->assertFalse($gateway->isLive());
    }

    public function test_charge_returns_failed_when_not_configured(): void
    {
        config(['payments.gateways.square.enabled' => false]);
        $invoice = $this->makeInvoice(50000);

        $result = (new SquareGateway)->charge($invoice, 'cnon:1');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->failureReason);
    }

    public function test_charge_returns_failed_when_no_balance_due(): void
    {
        $this->primeSandboxConfig();
        $invoice = $this->makeInvoice(0);

        $result = (new SquareGateway)->charge($invoice, 'cnon:1');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('no balance', $result->failureReason);
    }

    public function test_charge_returns_completed_when_payment_status_is_completed(): void
    {
        $this->primeSandboxConfig();
        $invoice = $this->makeInvoice(50000);

        $payment = (new Payment)
            ->setId('sq-pay-123')
            ->setStatus('COMPLETED')
            ->setOrderId('sq-order-1')
            ->setAmountMoney(new Money(['amount' => 50000, 'currency' => 'USD']));

        $client = $this->squareClientMockReturning(new CreatePaymentResponse(['payment' => $payment]));

        $result = (new SquareGateway($client))->charge($invoice, 'cnon:abc', 'verify-token');

        $this->assertTrue($result->success);
        $this->assertSame('completed', $result->status);
        $this->assertSame('sq-pay-123', $result->gatewayPaymentId);
        $this->assertSame('sq-order-1', $result->gatewayOrderId);
    }

    public function test_charge_sends_idempotency_key_within_square_length_limit(): void
    {
        $this->primeSandboxConfig();
        $invoice = $this->makeInvoice(50000);

        $payment = (new Payment)
            ->setId('sq-pay-789')
            ->setStatus('COMPLETED')
            ->setAmountMoney(new Money(['amount' => 50000, 'currency' => 'USD']));

        $payments = Mockery::mock(PaymentsClient::class);
        $capturedRequest = null;
        $payments->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($request) use (&$capturedRequest) {
                $capturedRequest = $request;

                return true;
            }))
            ->andReturn(new CreatePaymentResponse(['payment' => $payment]));

        $client = Mockery::mock(SquareClient::class);
        $client->payments = $payments;

        (new SquareGateway($client))->charge($invoice, 'cnon:abc');

        $this->assertNotNull($capturedRequest);
        $key = $capturedRequest->getIdempotencyKey();
        $this->assertNotEmpty($key);
        $this->assertLessThanOrEqual(45, strlen($key));
    }

    public function test_charge_returns_failed_when_payment_status_is_not_completed(): void
    {
        $this->primeSandboxConfig();
        $invoice = $this->makeInvoice(50000);

        $payment = (new Payment)
            ->setId('sq-pay-456')
            ->setStatus('FAILED');

        $client = $this->squareClientMockReturning(new CreatePaymentResponse(['payment' => $payment]));

        $result = (new SquareGateway($client))->charge($invoice, 'cnon:abc');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('FAILED', $result->failureReason);
    }

    public function test_charge_refreshes_expired_token_and_retries_after_unauthorized(): void
    {
        $this->primeSandboxConfig();
        $invoice = $this->makeInvoice(17500);

        $payment = (new Payment)
            ->setId('sq-pay-retry')
            ->setStatus('COMPLETED')
            ->setAmountMoney(new Money(['amount' => 17500, 'currency' => 'USD']));

        $payments = Mockery::mock(PaymentsClient::class);
        $payments->shouldReceive('create')
            ->twice()
            ->andReturnUsing(
                fn () => throw $this->unauthorizedException(),
                fn () => new CreatePaymentResponse(['payment' => $payment]),
            );

        $client = Mockery::mock(SquareClient::class);
        $client->payments = $payments;

        $refresher = Mockery::mock(SquareTokenRefresher::class);
        $refresher->shouldReceive('refreshIfExpiring')->once()->andReturnFalse();
        $refresher->shouldReceive('refresh')->once()->with(Mockery::type(SiteSetting::class))->andReturnTrue();

        $result = (new SquareGateway($client, $refresher))->charge($invoice, 'cnon:abc');

        $this->assertTrue($result->success);
        $this->assertSame('sq-pay-retry', $result->gatewayPaymentId);
    }

    public function test_charge_hides_raw_error_when_credentials_are_rejected(): void
    {
        $this->primeSandboxConfig();
        $invoice = $this->makeInvoice(17500);

        $payments = Mockery::mock(PaymentsClient::class);
        $payments->shouldReceive('create')->once()->andThrow($this->unauthorizedException());

        $client = Mockery::mock(SquareClient::class);
        $client->payments = $payments;

        $refresher = Mockery::mock(SquareTokenRefresher::class);
        $refresher->shouldReceive('refreshIfExpiring')->andReturnFalse();
        $refresher->shouldReceive('refresh')->once()->andReturnFalse();

        $result = (new SquareGateway($client, $refresher))->charge($invoice, 'cnon:abc');

        $this->assertFalse($result->success);
        $this->assertSame(SquareGateway::UNAVAILABLE_MESSAGE, $result->failureReason);
        $this->assertStringNotContainsString('401', $result->failureReason);
    }

    public function test_charge_does_not_retry_more_than_once_after_unauthorized(): void
    {
        $this->primeSandboxConfig();
        $invoice = $this->makeInvoice(17500);

        $payments = Mockery::mock(PaymentsClient::class);
        $payments->shouldReceive('create')->twice()->andThrow($this->unauthorizedException());

        $client = Mockery::mock(SquareClient::class);
        $client->payments = $payments;

        $refresher = Mockery::mock(SquareTokenRefresher::class);
        $refresher->shouldReceive('refreshIfExpiring')->andReturnFalse();
        $refresher->shouldReceive('refresh')->once()->andReturnTrue();

        $result = (new SquareGateway($client, $refresher))->charge($invoice, 'cnon:abc');

        $this->assertFalse($result->success);
        $this->assertSame(SquareGateway::UNAVAILABLE_MESSAGE, $result->failureReason);
    }

    public function test_charge_surfaces_card_error_detail_without_refreshing(): void
    {
        $this->primeSandboxConfig();
        $invoice = $this->makeInvoice(17500);

        $payments = Mockery::mock(PaymentsClient::class);
        $payments->shouldReceive('create')->once()->andThrow(new SquareApiException(
            message: 'API request failed',
            statusCode: 400,
            body: json_encode(['errors' => [[
                'category' => 'PAYMENT_METHOD_ERROR',
                'code' => 'GENERIC_DECLINE',
                'detail' => 'Authorization error: GENERIC_DECLINE',
            ]]]),
        ));

        $client = Mockery::mock(SquareClient::class);
        $client->payments = $payments;

        $refresher = Mockery::mock(SquareTokenRefresher::class);
        $refresher->shouldReceive('refreshIfExpiring')->andReturnFalse();
        $refresher->shouldNotReceive('refresh');

        $result = (new SquareGateway($client, $refresher))->charge($invoice, 'cnon:abc');

        $this->assertFalse($result->success);
        $this->assertSame('Authorization error: GENERIC_DECLINE', $result->failureReason);
    }

    public function test_verify_webhook_signature_returns_false_when_no_key_configured(): void
    {
        config([
            'payments.mode' => 'sandbox',
            'payments.gateways.square' => [
                'enabled' => true,
                'sandbox' => ['webhook_signature_key' => null],
                'live' => [],
            ],
        ]);

        $this->assertFalse(
            (new SquareGateway)->verifyWebhookSignature('body', 'sig', 'https://example.test/webhooks/square'),
        );
    }

    public function test_verify_webhook_signature_validates_signature_when_correct(): void
    {
        $this->primeSandboxConfig();
        $url = 'https://example.test/webhooks/square';
        $body = '{"type":"payment.updated"}';
        $key = 'sandbox-webhook-key';
        $sig = base64_encode(hash_hmac('sha256', $url.$body, $key, true));

        $this->assertTrue((new SquareGateway)->verifyWebhookSignature($body, $sig, $url));
        $this->assertFalse((new SquareGateway)->verifyWebhookSignature($body, 'wrong-sig', $url));
    }

    private function primeSandboxConfig(): void
    {
        config([
            'payments.mode' => 'sandbox',
            'payments.gateways.square' => [
                'enabled' => true,
                'sandbox' => [
                    'access_token' => 'sandbox-token',
                    'application_id' => 'sandbox-app-id',
                    'location_id' => 'sandbox-loc-id',
                    'webhook_signature_key' => 'sandbox-webhook-key',
                ],
                'live' => [],
            ],
        ]);
    }

    private function makeInvoice(int $amountDueCents): Invoice
    {
        $client = Client::factory()->create(['email' => 'sarah@example.com']);

        return Invoice::factory()->sent()->create([
            'billable_type' => Client::class, 'billable_id' => $client->id,
            'currency' => 'USD',
            'total_cents' => $amountDueCents,
            'amount_paid_cents' => 0,
        ]);
    }

    private function unauthorizedException(): SquareApiException
    {
        return new SquareApiException(
            message: 'API request failed',
            statusCode: 401,
            body: json_encode(['errors' => [[
                'category' => 'AUTHENTICATION_ERROR',
                'code' => 'UNAUTHORIZED',
                'detail' => 'This request could not be authorized.',
            ]]]),
        );
    }

    private function squareClientMockReturning(CreatePaymentResponse $response): SquareClient
    {
        $payments = Mockery::mock(PaymentsClient::class);
        $payments->shouldReceive('create')->once()->andReturn($response);

        $client = Mockery::mock(SquareClient::class);
        $client->payments = $payments;

        return $client;
    }
}
