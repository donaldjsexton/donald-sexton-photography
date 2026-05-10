<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Invoicing\InvoicePdfRenderer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoicePublicController extends Controller
{
    public function show(Request $request, string $invoice): View
    {
        $model = Invoice::where('uuid', $invoice)->firstOrFail();

        if ($model->viewed_at === null) {
            $model->forceFill(['viewed_at' => now()])->save();
        }

        return view('invoices.public', [
            'invoice' => $model->load(['client', 'lineItems', 'installments', 'payments']),
        ]);
    }

    public function downloadPdf(Request $request, string $invoice, InvoicePdfRenderer $renderer)
    {
        $model = Invoice::where('uuid', $invoice)->firstOrFail();

        return $renderer->build($model)->download();
    }
}
