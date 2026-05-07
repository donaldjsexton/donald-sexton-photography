<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Payment;
use App\Services\Payments\SquareGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SquarePaymentController extends Controller
{
    public function store(Request $request, string $invoice, SquareGateway $gateway): RedirectResponse
    {
        $data = $request->validate([
            'source_id' => ['required', 'string'],
            'verification_token' => ['nullable', 'string'],
        ]);

        if (! $gateway->isConfigured()) {
            return back()->with('status', 'Online payments are not currently available. Please contact us to pay.');
        }

        $invoiceModel = $this->locate($invoice);

        if ($invoiceModel->amountDueCents() <= 0) {
            return back()->with('status', 'This invoice is already paid.');
        }

        $result = $gateway->charge(
            invoice: $invoiceModel,
            sourceId: $data['source_id'],
            verificationToken: $data['verification_token'] ?? null,
        );

        if (! $result->success) {
            return back()->with('status', 'Payment failed: '.$result->failureReason);
        }

        DB::transaction(function () use ($invoiceModel, $result, $gateway) {
            $invoiceModel->payments()->create([
                'gateway' => Payment::GATEWAY_SQUARE,
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

        return redirect()
            ->route('portal.invoices.show', ['invoice' => $invoiceModel->uuid])
            ->with('status', 'Payment received. Thank you!');
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
