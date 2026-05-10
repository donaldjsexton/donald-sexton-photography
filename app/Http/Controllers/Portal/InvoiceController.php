<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoicing\InvoicePdfRenderer;
use App\Services\Payments\PayPalGateway;
use App\Services\Payments\SquareGateway;
use App\Support\Portal;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $billable = Portal::user();

        return view('portal.invoices.index', [
            'invoices' => $billable->invoices()
                ->whereNotIn('status', [Invoice::STATUS_DRAFT])
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function show(Request $request, string $invoice, SquareGateway $square, PayPalGateway $paypal): View
    {
        $model = $this->locate($invoice);

        if ($model->viewed_at === null) {
            $model->forceFill(['viewed_at' => now()])->save();
        }

        $payable = $model->canPayOnline();

        return view('portal.invoices.show', [
            'invoice' => $model->load(['billable', 'lineItems', 'installments', 'payments']),
            'squareEnabled' => $payable && $square->isConfigured(),
            'squareApplicationId' => $square->applicationId(),
            'squareLocationId' => $square->locationId(),
            'squareSdkUrl' => $square->isLive()
                ? 'https://web.squarecdn.com/v1/square.js'
                : 'https://sandbox.web.squarecdn.com/v1/square.js',
            'paypalEnabled' => $payable && $paypal->isConfigured(),
            'paypalSdkUrl' => $paypal->jsSdkUrl(),
        ]);
    }

    public function downloadPdf(Request $request, string $invoice, InvoicePdfRenderer $renderer)
    {
        return $renderer->build($this->locate($invoice))->download();
    }

    private function locate(string $uuid): Invoice
    {
        $billable = Portal::user();

        $invoice = Invoice::where('uuid', $uuid)
            ->where('billable_type', $billable::class)
            ->where('billable_id', $billable->id)
            ->whereNotIn('status', [Invoice::STATUS_DRAFT])
            ->first();

        if (! $invoice) {
            throw new NotFoundHttpException;
        }

        return $invoice;
    }
}
