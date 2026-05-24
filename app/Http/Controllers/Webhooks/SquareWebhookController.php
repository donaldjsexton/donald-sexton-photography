<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payments\SquareGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SquareWebhookController extends Controller
{
    public function __invoke(Request $request, SquareGateway $gateway): JsonResponse
    {
        $body = $request->getContent();
        $signature = (string) $request->header('x-square-hmacsha256-signature', '');
        $notificationUrl = $request->fullUrl();

        if (! $gateway->verifyWebhookSignature($body, $signature, $notificationUrl)) {
            Log::warning('Square webhook signature mismatch', ['url' => $notificationUrl]);

            return response()->json(['error' => 'invalid signature'], 401);
        }

        $payload = (array) $request->json()->all();
        $type = (string) ($payload['type'] ?? '');
        $data = (array) ($payload['data']['object'] ?? []);

        match ($type) {
            'payment.updated', 'payment.created' => $this->syncPayment($data),
            'refund.updated', 'refund.created' => $this->syncRefund($data),
            default => Log::info('Square webhook unhandled', ['type' => $type]),
        };

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncPayment(array $data): void
    {
        $squarePayment = (array) ($data['payment'] ?? []);
        $gatewayId = (string) ($squarePayment['id'] ?? '');

        if ($gatewayId === '') {
            return;
        }

        $payment = Payment::where('gateway', Payment::GATEWAY_SQUARE)
            ->where('gateway_payment_id', $gatewayId)
            ->first();

        if (! $payment) {
            return;
        }

        $status = strtoupper((string) ($squarePayment['status'] ?? ''));

        $payment->status = match ($status) {
            'COMPLETED', 'APPROVED', 'CAPTURED' => Payment::STATUS_COMPLETED,
            'CANCELED', 'FAILED' => Payment::STATUS_FAILED,
            default => $payment->status,
        };

        $payment->payload = array_merge((array) $payment->payload, ['webhook' => $squarePayment]);
        $payment->save();

        $invoice = Invoice::withoutSiteScope()->find($payment->invoice_id);
        $invoice?->syncStatusFromPayments();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRefund(array $data): void
    {
        $refund = (array) ($data['refund'] ?? []);
        $gatewayPaymentId = (string) ($refund['payment_id'] ?? '');

        if ($gatewayPaymentId === '') {
            return;
        }

        $payment = Payment::where('gateway', Payment::GATEWAY_SQUARE)
            ->where('gateway_payment_id', $gatewayPaymentId)
            ->first();

        if (! $payment) {
            return;
        }

        $status = strtoupper((string) ($refund['status'] ?? ''));
        $refundAmount = (int) ($refund['amount_money']['amount'] ?? 0);

        if ($status === 'COMPLETED' && $refundAmount > 0) {
            $payment->refunded_amount_cents = $refundAmount;
            $payment->refunded_at = now();
            $payment->status = $refundAmount >= $payment->amount_cents
                ? Payment::STATUS_REFUNDED
                : Payment::STATUS_PARTIALLY_REFUNDED;
            $payment->payload = array_merge((array) $payment->payload, ['refund' => $refund]);
            $payment->save();

            $invoice = $payment->invoice()->first();
            $invoice?->syncStatusFromPayments();
        }
    }
}
