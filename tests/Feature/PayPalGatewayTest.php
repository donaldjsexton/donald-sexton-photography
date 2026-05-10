<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\PayPalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use PaypalServerSdkLib\Controllers\OrdersController;
use PaypalServerSdkLib\Http\ApiResponse;
use PaypalServerSdkLib\Models\CapturedPayment;
use PaypalServerSdkLib\Models\Order;
use PaypalServerSdkLib\Models\PaymentCollection;
use PaypalServerSdkLib\Models\PurchaseUnit;
use PaypalServerSdkLib\PaypalServerSdkClient;
use Tests\TestCase;

class PayPalGatewayTest extends TestCase
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
            'payments.gateways.paypal.enabled' => false,
            'payments.mode' => 'sandbox',
        ]);

        $this->assertFalse((new PayPalGateway)->isConfigured());
    }

    public function test_is_configured_true_when_credentials_present(): void
    {
        $this->primeSandboxConfig();

        $gateway = new PayPalGateway;

        $this->assertTrue($gateway->isConfigured());
        $this->assertSame('sandbox-client-id', $gateway->clientId());
        $this->assertFalse($gateway->isLive());
        $this->assertSame('https://api-m.sandbox.paypal.com', $gateway->apiBase());
    }

    public function test_js_sdk_url_includes_client_id_and_currency(): void
    {
        $this->primeSandboxConfig();
        config(['payments.currency' => 'USD']);

        $url = (new PayPalGateway)->jsSdkUrl();

        $this->assertStringStartsWith('https://www.paypal.com/sdk/js?', $url);
        $this->assertStringContainsString('client-id=sandbox-client-id', $url);
        $this->assertStringContainsString('currency=USD', $url);
        $this->assertStringContainsString('intent=capture', $url);
    }

    public function test_create_order_returns_failed_when_not_configured(): void
    {
        config(['payments.gateways.paypal.enabled' => false]);
        $invoice = $this->makeInvoice(50000);

        $result = (new PayPalGateway)->createOrder($invoice);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertFalse($result->success);
    }

    public function test_create_order_returns_order_id_on_success(): void
    {
        $this->primeSandboxConfig();
        $invoice = $this->makeInvoice(50000);

        $order = Mockery::mock(Order::class);
        $order->shouldReceive('getId')->andReturn('PP-ORDER-1');

        $client = $this->mockClientReturning('createOrder', $this->successResponse($order));

        $result = (new PayPalGateway($client))->createOrder($invoice);

        $this->assertSame(['order_id' => 'PP-ORDER-1'], $result);
    }

    public function test_create_order_returns_failed_when_paypal_responds_with_error(): void
    {
        $this->primeSandboxConfig();
        $invoice = $this->makeInvoice(50000);

        $client = $this->mockClientReturning('createOrder', $this->errorResponse());

        $result = (new PayPalGateway($client))->createOrder($invoice);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertFalse($result->success);
    }

    public function test_capture_order_returns_completed_when_status_completed(): void
    {
        $this->primeSandboxConfig();

        $capture = Mockery::mock(CapturedPayment::class);
        $capture->shouldReceive('getId')->andReturn('PP-CAPTURE-1');

        $payments = Mockery::mock(PaymentCollection::class);
        $payments->shouldReceive('getCaptures')->andReturn([$capture]);

        $unit = Mockery::mock(PurchaseUnit::class);
        $unit->shouldReceive('getPayments')->andReturn($payments);

        $order = Mockery::mock(Order::class);
        $order->shouldReceive('getStatus')->andReturn('COMPLETED');
        $order->shouldReceive('getPurchaseUnits')->andReturn([$unit]);
        $order->shouldReceive('jsonSerialize')->andReturn(['id' => 'PP-ORDER-1', 'status' => 'COMPLETED']);

        $client = $this->mockClientReturning('captureOrder', $this->successResponse($order));

        $result = (new PayPalGateway($client))->captureOrder('PP-ORDER-1');

        $this->assertTrue($result->success);
        $this->assertSame('PP-CAPTURE-1', $result->gatewayPaymentId);
        $this->assertSame('PP-ORDER-1', $result->gatewayOrderId);
    }

    public function test_capture_order_returns_failed_when_status_not_completed(): void
    {
        $this->primeSandboxConfig();

        $order = Mockery::mock(Order::class);
        $order->shouldReceive('getStatus')->andReturn('PAYER_ACTION_REQUIRED');
        $order->shouldReceive('getPurchaseUnits')->andReturn([]);
        $order->shouldReceive('jsonSerialize')->andReturn(['status' => 'PAYER_ACTION_REQUIRED']);

        $client = $this->mockClientReturning('captureOrder', $this->successResponse($order));

        $result = (new PayPalGateway($client))->captureOrder('PP-ORDER-2');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('PAYER_ACTION_REQUIRED', $result->failureReason);
    }

    public function test_verify_webhook_signature_returns_false_without_webhook_id(): void
    {
        config([
            'payments.mode' => 'sandbox',
            'payments.gateways.paypal' => [
                'enabled' => true,
                'sandbox' => ['webhook_id' => null],
                'live' => [],
            ],
        ]);

        $this->assertFalse((new PayPalGateway)->verifyWebhookSignature('{}', []));
    }

    public function test_verify_webhook_signature_calls_paypal_and_returns_true_on_success(): void
    {
        $this->primeSandboxConfig();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'TEST-TOKEN'], 200),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS'], 200),
        ]);

        $verified = (new PayPalGateway)->verifyWebhookSignature(
            '{"event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            [
                'paypal-auth-algo' => 'SHA256withRSA',
                'paypal-cert-url' => 'https://example.test/cert',
                'paypal-transmission-id' => 'tx-1',
                'paypal-transmission-sig' => 'sig',
                'paypal-transmission-time' => '2026-05-10T00:00:00Z',
            ],
        );

        $this->assertTrue($verified);
    }

    public function test_verify_webhook_signature_returns_false_when_paypal_says_failure(): void
    {
        $this->primeSandboxConfig();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'TEST-TOKEN'], 200),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'FAILURE'], 200),
        ]);

        $this->assertFalse((new PayPalGateway)->verifyWebhookSignature('{}', [
            'paypal-auth-algo' => 'a',
            'paypal-cert-url' => 'b',
            'paypal-transmission-id' => 'c',
            'paypal-transmission-sig' => 'd',
            'paypal-transmission-time' => 'e',
        ]));
    }

    private function primeSandboxConfig(): void
    {
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

    private function makeInvoice(int $amountDueCents): Invoice
    {
        $client = Client::factory()->create();

        return Invoice::factory()->sent()->create([
            'client_id' => $client->id,
            'currency' => 'USD',
            'total_cents' => $amountDueCents,
            'amount_paid_cents' => 0,
        ]);
    }

    private function mockClientReturning(string $method, ApiResponse $response): PaypalServerSdkClient
    {
        $orders = Mockery::mock(OrdersController::class);
        $orders->shouldReceive($method)->once()->andReturn($response);

        $client = Mockery::mock(PaypalServerSdkClient::class);
        $client->shouldReceive('getOrdersController')->andReturn($orders);

        return $client;
    }

    private function successResponse(mixed $result): ApiResponse
    {
        $response = Mockery::mock(ApiResponse::class);
        $response->shouldReceive('isSuccess')->andReturnTrue();
        $response->shouldReceive('getResult')->andReturn($result);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn(json_encode(['ok' => true]));

        return $response;
    }

    private function errorResponse(): ApiResponse
    {
        $response = Mockery::mock(ApiResponse::class);
        $response->shouldReceive('isSuccess')->andReturnFalse();
        $response->shouldReceive('getStatusCode')->andReturn(422);
        $response->shouldReceive('getBody')->andReturn(json_encode(['name' => 'UNPROCESSABLE_ENTITY']));

        return $response;
    }
}
