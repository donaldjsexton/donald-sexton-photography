<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PayPalGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    public function __invoke(Request $request, PayPalGateway $gateway): JsonResponse
    {
        $body = $request->getContent();

        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[strtolower($name)] = is_array($values) ? ($values[0] ?? '') : $values;
        }

        if (! $gateway->verifyWebhookSignature($body, $headers)) {
            Log::warning('PayPal webhook signature mismatch', ['url' => $request->fullUrl()]);

            return response()->json(['error' => 'invalid signature'], 401);
        }

        $payload = (array) $request->json()->all();
        $eventType = (string) ($payload['event_type'] ?? '');
        $resource = (array) ($payload['resource'] ?? []);

        match (true) {
            $eventType === 'PAYMENT.CAPTURE.REFUNDED' => $this->syncRefund($resource),
            str_starts_with($eventType, 'PAYMENT.CAPTURE.') => $this->syncCapture($eventType, $resource),
            default => Log::info('PayPal webhook unhandled', ['event_type' => $eventType]),
        };

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function syncCapture(string $eventType, array $resource): void
    {
        $captureId = (string) ($resource['id'] ?? '');
        if ($captureId === '') {
            return;
        }

        $payment = Payment::where('gateway', Payment::GATEWAY_PAYPAL)
            ->where('gateway_payment_id', $captureId)
            ->first();

        if (! $payment) {
            return;
        }

        $status = strtoupper((string) ($resource['status'] ?? ''));

        $payment->status = match (true) {
            $status === 'COMPLETED' => Payment::STATUS_COMPLETED,
            in_array($status, ['DECLINED', 'FAILED'], true) => Payment::STATUS_FAILED,
            $eventType === 'PAYMENT.CAPTURE.PENDING' => Payment::STATUS_PROCESSING,
            default => $payment->status,
        };

        $payment->payload = array_merge((array) $payment->payload, ['webhook' => $resource]);
        $payment->save();

        $payment->invoice()->first()?->syncStatusFromPayments();
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function syncRefund(array $resource): void
    {
        $captureId = $this->extractRefundedCaptureId($resource);
        $refundId = (string) ($resource['id'] ?? '');

        if (! $captureId || $refundId === '') {
            return;
        }

        $payment = Payment::where('gateway', Payment::GATEWAY_PAYPAL)
            ->where('gateway_payment_id', $captureId)
            ->first();

        if (! $payment) {
            return;
        }

        $refundCents = (int) round(((float) ($resource['amount']['value'] ?? 0)) * 100);

        if ($refundCents <= 0) {
            return;
        }

        $payload = (array) $payment->payload;
        $refunds = (array) ($payload['refunds'] ?? []);

        if (isset($refunds[$refundId])) {
            return;
        }

        $refunds[$refundId] = $resource;
        $payload['refunds'] = $refunds;

        $totalRefundedCents = array_sum(array_map(
            fn (array $refund): int => (int) round(((float) ($refund['amount']['value'] ?? 0)) * 100),
            $refunds,
        ));

        $payment->refunded_amount_cents = $totalRefundedCents;
        $payment->refunded_at = now();
        $payment->status = $totalRefundedCents >= $payment->amount_cents
            ? Payment::STATUS_REFUNDED
            : Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->payload = $payload;
        $payment->save();

        $payment->invoice()->first()?->syncStatusFromPayments();
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function extractRefundedCaptureId(array $resource): ?string
    {
        foreach ((array) ($resource['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'up' && ! empty($link['href'])) {
                $segments = explode('/', rtrim((string) $link['href'], '/'));

                return end($segments) ?: null;
            }
        }

        return null;
    }
}
