<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Payment;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\PayPalGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PayPalPaymentController extends Controller
{
    public function createOrder(Request $request, string $invoice, PayPalGateway $gateway): JsonResponse
    {
        if (! $gateway->isConfigured()) {
            return response()->json(['error' => 'PayPal is not configured.'], 422);
        }

        $invoiceModel = $this->locate($invoice);

        $result = $gateway->createOrder($invoiceModel);

        if ($result instanceof PaymentResult) {
            return response()->json(['error' => $result->failureReason ?: 'Could not start PayPal payment.'], 422);
        }

        return response()->json(['order_id' => $result['order_id']]);
    }

    public function capture(Request $request, string $invoice, PayPalGateway $gateway): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'string'],
        ]);

        $invoiceModel = $this->locate($invoice);

        if ($invoiceModel->amountDueCents() <= 0) {
            return $this->respond($request, 'This invoice is already paid.', $invoiceModel);
        }

        $result = $gateway->captureOrder($data['order_id']);

        if (! $result->success) {
            return $this->respond($request, 'Payment failed: '.$result->failureReason, $invoiceModel, status: 422);
        }

        DB::transaction(function () use ($invoiceModel, $result, $gateway) {
            $invoiceModel->payments()->create([
                'gateway' => Payment::GATEWAY_PAYPAL,
                'mode' => $gateway->mode(),
                'status' => Payment::STATUS_COMPLETED,
                'amount_cents' => $invoiceModel->amountDueCents(),
                'currency' => $invoiceModel->currency,
                'gateway_payment_id' => $result->gatewayPaymentId,
                'gateway_order_id' => $result->gatewayOrderId,
                'received_at' => now(),
                'payload' => $result->payload,
            ]);

            $invoiceModel->syncStatusFromPayments();

            if ($invoiceModel->isPaid()) {
                $invoiceModel->installments()
                    ->whereNotIn('status', [InvoiceInstallment::STATUS_PAID, InvoiceInstallment::STATUS_VOID])
                    ->get()
                    ->each(function (InvoiceInstallment $installment) {
                        $installment->forceFill([
                            'amount_paid_cents' => $installment->amount_cents,
                            'status' => InvoiceInstallment::STATUS_PAID,
                            'paid_at' => $installment->paid_at ?? now(),
                        ])->save();
                    });
            }
        });

        return $this->respond($request, 'Payment received. Thank you!', $invoiceModel);
    }

    private function respond(Request $request, string $message, Invoice $invoice, int $status = 200): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => $status === 200 ? 'ok' : 'error',
                'message' => $message,
                'redirect' => route('portal.invoices.show', ['invoice' => $invoice->uuid]),
            ], $status);
        }

        return redirect()
            ->route('portal.invoices.show', ['invoice' => $invoice->uuid])
            ->with('status', $message);
    }

    private function locate(string $uuid): Invoice
    {
        /** @var Client $client */
        $client = Auth::guard('client')->user();

        $invoice = Invoice::where('uuid', $uuid)
            ->where('client_id', $client->id)
            ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_VOID])
            ->first();

        if (! $invoice) {
            throw new NotFoundHttpException;
        }

        return $invoice;
    }
}
