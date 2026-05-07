<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\Invoicing\InvoicePdfRenderer;
use App\Services\Payments\SquareGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Client $client */
        $client = Auth::guard('client')->user();

        return view('portal.invoices.index', [
            'invoices' => $client->invoices()
                ->whereNotIn('status', [Invoice::STATUS_DRAFT])
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function show(Request $request, string $invoice, SquareGateway $square): View
    {
        $model = $this->locate($invoice);

        if ($model->viewed_at === null) {
            $model->forceFill(['viewed_at' => now()])->save();
        }

        return view('portal.invoices.show', [
            'invoice' => $model->load(['client', 'lineItems', 'installments', 'payments']),
            'squareEnabled' => $square->isConfigured() && $model->amountDueCents() > 0 && $model->status !== Invoice::STATUS_VOID,
            'squareApplicationId' => $square->applicationId(),
            'squareLocationId' => $square->locationId(),
            'squareSdkUrl' => $square->isLive()
                ? 'https://web.squarecdn.com/v1/square.js'
                : 'https://sandbox.web.squarecdn.com/v1/square.js',
        ]);
    }

    public function downloadPdf(Request $request, string $invoice, InvoicePdfRenderer $renderer)
    {
        return $renderer->build($this->locate($invoice))->download();
    }

    private function locate(string $uuid): Invoice
    {
        /** @var Client $client */
        $client = Auth::guard('client')->user();

        $invoice = Invoice::where('uuid', $uuid)
            ->where('client_id', $client->id)
            ->whereNotIn('status', [Invoice::STATUS_DRAFT])
            ->first();

        if (! $invoice) {
            throw new NotFoundHttpException;
        }

        return $invoice;
    }
}
