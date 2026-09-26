<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Square\Environments;
use Square\Exceptions\SquareApiException;
use Square\Exceptions\SquareException;
use Square\Payments\Requests\CreatePaymentRequest;
use Square\SquareClient;
use Square\Types\CreatePaymentResponse;
use Square\Types\Money;
use Square\Utils\WebhooksHelper;
use Throwable;

class SquareGateway
{
    /** @var array<string, string> */
    private const SUCCESS_STATUSES = [
        'COMPLETED' => 'completed',
        'APPROVED' => 'completed',
        'CAPTURED' => 'completed',
    ];

    /**
     * Shown to clients when Square rejects our credentials — the raw API
     * error is logged instead, since it means nothing to the payer.
     */
    public const UNAVAILABLE_MESSAGE = 'Online payments are temporarily unavailable. Please contact us to complete your payment.';

    public function __construct(
        private readonly ?SquareClient $client = null,
        private readonly ?SquareTokenRefresher $tokenRefresher = null,
    ) {}

    public function isConfigured(): bool
    {
        return (bool) config('payments.gateways.square.enabled')
            && filled($this->credentials()['access_token'] ?? null)
            && filled($this->credentials()['location_id'] ?? null);
    }

    public function applicationId(): ?string
    {
        return $this->credentials()['application_id'] ?? null;
    }

    public function locationId(): ?string
    {
        return $this->credentials()['location_id'] ?? null;
    }

    public function mode(): string
    {
        return config('payments.mode', 'sandbox');
    }

    public function isLive(): bool
    {
        return $this->mode() === 'live';
    }

    /**
     * Charge an invoice against a Web Payments SDK source token.
     */
    public function charge(Invoice $invoice, string $sourceId, ?string $verificationToken = null): PaymentResult
    {
        if (! $this->isConfigured()) {
            return PaymentResult::failed('Square is not configured.');
        }

        $amountCents = $invoice->amountDueCents();

        if ($amountCents <= 0) {
            return PaymentResult::failed('This invoice has no balance due.');
        }

        $this->refresher()->refreshIfExpiring(SiteSetting::current());

        try {
            $response = $this->createPayment($invoice, $amountCents, $sourceId, $verificationToken);
        } catch (SquareApiException $e) {
            // An expired or revoked OAuth token surfaces as a 401; refresh it
            // once and retry before giving up. The rejected attempt never
            // reached the card, so the source token is still usable.
            if (! $this->isAuthenticationFailure($e) || ! $this->refresher()->refresh(SiteSetting::current())) {
                return $this->apiFailure($invoice, $e);
            }

            try {
                $response = $this->createPayment($invoice, $amountCents, $sourceId, $verificationToken);
            } catch (SquareApiException $retryException) {
                return $this->apiFailure($invoice, $retryException);
            } catch (SquareException|Throwable $retryException) {
                return $this->sdkFailure($invoice, $retryException);
            }
        } catch (SquareException|Throwable $e) {
            return $this->sdkFailure($invoice, $e);
        }

        $payment = $response->getPayment();

        if ($payment === null) {
            return PaymentResult::failed('Square returned no payment object.');
        }

        $status = strtoupper((string) $payment->getStatus());

        if (! array_key_exists($status, self::SUCCESS_STATUSES)) {
            return PaymentResult::failed(
                'Payment did not complete (status: '.($payment->getStatus() ?: 'unknown').').',
                ['payment' => $payment->jsonSerialize()],
            );
        }

        return PaymentResult::completed(
            gatewayPaymentId: (string) $payment->getId(),
            gatewayOrderId: $payment->getOrderId(),
            payload: ['payment' => $payment->jsonSerialize()],
        );
    }

    public function verifyWebhookSignature(string $body, string $signatureHeader, string $notificationUrl): bool
    {
        $key = $this->credentials()['webhook_signature_key'] ?? null;

        if (! $key) {
            return false;
        }

        try {
            return WebhooksHelper::verifySignature(
                requestBody: $body,
                signatureHeader: $signatureHeader,
                signatureKey: $key,
                notificationUrl: $notificationUrl,
            );
        } catch (Throwable $e) {
            Log::warning('Square webhook signature verification failed', ['message' => $e->getMessage()]);

            return false;
        }
    }

    private function createPayment(Invoice $invoice, int $amountCents, string $sourceId, ?string $verificationToken): CreatePaymentResponse
    {
        return $this->squareClient()->payments->create(new CreatePaymentRequest([
            'sourceId' => $sourceId,
            'idempotencyKey' => (string) Str::uuid(),
            'amountMoney' => new Money([
                'amount' => $amountCents,
                'currency' => $invoice->currency ?: 'USD',
            ]),
            'locationId' => $this->locationId(),
            'referenceId' => $invoice->number,
            'note' => 'Invoice '.$invoice->number,
            'verificationToken' => $verificationToken,
            'autocomplete' => true,
            'buyerEmailAddress' => $invoice->client?->email,
        ]));
    }

    private function apiFailure(Invoice $invoice, SquareApiException $e): PaymentResult
    {
        $reason = $this->extractErrorMessage($e);

        if ($this->isAuthenticationFailure($e)) {
            Log::error('Square rejected the payment credentials', [
                'invoice_id' => $invoice->id,
                'status' => $e->getStatusCode(),
                'reason' => $reason,
                'mode' => $this->mode(),
            ]);

            return PaymentResult::failed(self::UNAVAILABLE_MESSAGE, ['exception' => $e->getMessage()]);
        }

        Log::warning('Square payment API error', [
            'invoice_id' => $invoice->id,
            'reason' => $reason,
        ]);

        return PaymentResult::failed($reason, ['exception' => $e->getMessage()]);
    }

    private function sdkFailure(Invoice $invoice, Throwable $e): PaymentResult
    {
        Log::error('Square payment SDK error', [
            'invoice_id' => $invoice->id,
            'message' => $e->getMessage(),
        ]);

        return PaymentResult::failed('Payment processor error. Please try again.', ['exception' => $e->getMessage()]);
    }

    private function isAuthenticationFailure(SquareApiException $e): bool
    {
        return in_array($e->getStatusCode(), [401, 403], true);
    }

    private function refresher(): SquareTokenRefresher
    {
        return $this->tokenRefresher ?? app(SquareTokenRefresher::class);
    }

    private function squareClient(): SquareClient
    {
        if ($this->client) {
            return $this->client;
        }

        $token = (string) ($this->credentials()['access_token'] ?? '');

        return new SquareClient(
            token: $token,
            options: [
                'baseUrl' => $this->isLive() ? Environments::Production->value : Environments::Sandbox->value,
            ],
        );
    }

    /**
     * Credentials for the active tenant. A connected tenant's OAuth access
     * token and location take precedence; the platform application_id and
     * webhook key always come from config. Falls back entirely to config when
     * no tenant has connected (e.g. the default site using its own keys).
     *
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        $config = (array) config('payments.gateways.square.'.$this->mode(), []);

        $settings = SiteSetting::current();

        if ($settings->squareIsConnected()) {
            return array_merge($config, [
                'access_token' => $settings->square_access_token,
                'location_id' => $settings->square_location_id ?: ($config['location_id'] ?? null),
            ]);
        }

        return $config;
    }

    private function extractErrorMessage(SquareApiException $e): string
    {
        $detail = ($e->getErrors()[0] ?? null)?->getDetail();

        if (filled($detail)) {
            return $detail;
        }

        return 'Unknown payment error.';
    }
}
