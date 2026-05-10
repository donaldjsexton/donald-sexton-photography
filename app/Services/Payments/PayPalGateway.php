<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PaypalServerSdkLib\Authentication\ClientCredentialsAuthCredentialsBuilder;
use PaypalServerSdkLib\Environment;
use PaypalServerSdkLib\Models\Builders\AmountWithBreakdownBuilder;
use PaypalServerSdkLib\Models\Builders\OrderRequestBuilder;
use PaypalServerSdkLib\Models\Builders\PurchaseUnitRequestBuilder;
use PaypalServerSdkLib\PaypalServerSdkClient;
use PaypalServerSdkLib\PaypalServerSdkClientBuilder;
use Throwable;

class PayPalGateway
{
    public function __construct(
        private readonly ?PaypalServerSdkClient $client = null,
    ) {}

    public function isConfigured(): bool
    {
        return (bool) config('payments.gateways.paypal.enabled')
            && filled($this->credentials()['client_id'] ?? null)
            && filled($this->credentials()['client_secret'] ?? null);
    }

    public function clientId(): ?string
    {
        return $this->credentials()['client_id'] ?? null;
    }

    public function mode(): string
    {
        return config('payments.mode', 'sandbox');
    }

    public function isLive(): bool
    {
        return $this->mode() === 'live';
    }

    public function jsSdkUrl(): string
    {
        $clientId = (string) $this->clientId();
        $currency = config('payments.currency', 'USD');

        return 'https://www.paypal.com/sdk/js?'.http_build_query([
            'client-id' => $clientId,
            'currency' => $currency,
            'intent' => 'capture',
            'components' => 'buttons',
        ]);
    }

    /**
     * Create a PayPal order for the invoice's outstanding balance.
     * Returns ['order_id' => string] on success; PaymentResult::failed otherwise.
     *
     * @return array{order_id: string}|PaymentResult
     */
    public function createOrder(Invoice $invoice): array|PaymentResult
    {
        if (! $this->isConfigured()) {
            return PaymentResult::failed('PayPal is not configured.');
        }

        $amountCents = $invoice->amountDueCents();
        if ($amountCents <= 0) {
            return PaymentResult::failed('This invoice has no balance due.');
        }

        $amount = number_format($amountCents / 100, 2, '.', '');
        $currency = $invoice->currency ?: 'USD';

        $orderRequest = OrderRequestBuilder::init('CAPTURE', [
            PurchaseUnitRequestBuilder::init(
                AmountWithBreakdownBuilder::init($currency, $amount)->build(),
            )
                ->referenceId($invoice->number)
                ->invoiceId($invoice->number)
                ->description('Invoice '.$invoice->number)
                ->build(),
        ])->build();

        try {
            $response = $this->paypalClient()->getOrdersController()->createOrder([
                'body' => $orderRequest,
                'prefer' => 'return=representation',
            ]);
        } catch (Throwable $e) {
            Log::error('PayPal createOrder error', [
                'invoice_id' => $invoice->id,
                'message' => $e->getMessage(),
            ]);

            return PaymentResult::failed('Could not start PayPal payment.', ['exception' => $e->getMessage()]);
        }

        if (! $response->isSuccess()) {
            return PaymentResult::failed('PayPal returned an error creating the order.', [
                'status' => $response->getStatusCode(),
                'body' => $response->getBody(),
            ]);
        }

        $order = $response->getResult();
        $orderId = method_exists($order, 'getId') ? $order->getId() : null;

        if (! $orderId) {
            return PaymentResult::failed('PayPal did not return an order id.');
        }

        return ['order_id' => (string) $orderId];
    }

    public function captureOrder(string $orderId): PaymentResult
    {
        if (! $this->isConfigured()) {
            return PaymentResult::failed('PayPal is not configured.');
        }

        try {
            $response = $this->paypalClient()->getOrdersController()->captureOrder([
                'id' => $orderId,
                'prefer' => 'return=representation',
            ]);
        } catch (Throwable $e) {
            Log::error('PayPal captureOrder error', ['order_id' => $orderId, 'message' => $e->getMessage()]);

            return PaymentResult::failed('Could not capture PayPal payment.', ['exception' => $e->getMessage()]);
        }

        if (! $response->isSuccess()) {
            return PaymentResult::failed('PayPal returned an error capturing the order.', [
                'status' => $response->getStatusCode(),
                'body' => $response->getBody(),
            ]);
        }

        $order = $response->getResult();
        $status = strtoupper((string) (method_exists($order, 'getStatus') ? $order->getStatus() : ''));

        if ($status !== 'COMPLETED') {
            return PaymentResult::failed('PayPal capture did not complete (status: '.$status.').', [
                'order' => $this->orderToArray($order),
            ]);
        }

        $captureId = $this->extractCaptureId($order);

        return PaymentResult::completed(
            gatewayPaymentId: $captureId ?: $orderId,
            payload: ['order' => $this->orderToArray($order)],
            gatewayOrderId: $orderId,
        );
    }

    /**
     * Verify a PayPal webhook by calling their /v1/notifications/verify-webhook-signature endpoint.
     *
     * @param  array<string, string>  $headers  request headers (lower-case keys)
     */
    public function verifyWebhookSignature(string $rawBody, array $headers, ?string $webhookId = null): bool
    {
        $webhookId ??= $this->credentials()['webhook_id'] ?? null;

        if (! $webhookId) {
            return false;
        }

        try {
            $token = $this->fetchAccessToken();
        } catch (Throwable $e) {
            Log::warning('PayPal access token fetch failed for webhook verification', ['message' => $e->getMessage()]);

            return false;
        }

        if (! $token) {
            return false;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->post($this->apiBase().'/v1/notifications/verify-webhook-signature', [
                    'auth_algo' => $headers['paypal-auth-algo'] ?? '',
                    'cert_url' => $headers['paypal-cert-url'] ?? '',
                    'transmission_id' => $headers['paypal-transmission-id'] ?? '',
                    'transmission_sig' => $headers['paypal-transmission-sig'] ?? '',
                    'transmission_time' => $headers['paypal-transmission-time'] ?? '',
                    'webhook_id' => $webhookId,
                    'webhook_event' => json_decode($rawBody, true),
                ]);
        } catch (Throwable $e) {
            Log::warning('PayPal webhook verification HTTP error', ['message' => $e->getMessage()]);

            return false;
        }

        return $response->successful() && $response->json('verification_status') === 'SUCCESS';
    }

    public function apiBase(): string
    {
        return $this->isLive()
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function fetchAccessToken(): ?string
    {
        $creds = $this->credentials();

        $response = Http::asForm()
            ->withBasicAuth((string) $creds['client_id'], (string) $creds['client_secret'])
            ->post($this->apiBase().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if (! $response->successful()) {
            return null;
        }

        return (string) $response->json('access_token');
    }

    private function paypalClient(): PaypalServerSdkClient
    {
        if ($this->client) {
            return $this->client;
        }

        $creds = $this->credentials();

        return PaypalServerSdkClientBuilder::init()
            ->environment($this->isLive() ? Environment::PRODUCTION : Environment::SANDBOX)
            ->clientCredentialsAuthCredentials(
                ClientCredentialsAuthCredentialsBuilder::init(
                    (string) $creds['client_id'],
                    (string) $creds['client_secret'],
                ),
            )
            ->build();
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        return (array) config('payments.gateways.paypal.'.$this->mode(), []);
    }

    private function extractCaptureId(mixed $order): ?string
    {
        if (! is_object($order) || ! method_exists($order, 'getPurchaseUnits')) {
            return null;
        }

        $units = $order->getPurchaseUnits() ?? [];
        foreach ($units as $unit) {
            if (! method_exists($unit, 'getPayments')) {
                continue;
            }
            $payments = $unit->getPayments();
            if (! $payments || ! method_exists($payments, 'getCaptures')) {
                continue;
            }
            foreach ($payments->getCaptures() ?? [] as $capture) {
                if (method_exists($capture, 'getId')) {
                    return (string) $capture->getId();
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function orderToArray(mixed $order): array
    {
        if (is_object($order) && method_exists($order, 'jsonSerialize')) {
            return (array) $order->jsonSerialize();
        }

        return is_array($order) ? $order : [];
    }
}
