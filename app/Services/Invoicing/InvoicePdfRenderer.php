<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use Illuminate\Support\Facades\URL;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class InvoicePdfRenderer
{
    public function build(Invoice $invoice): PdfBuilder
    {
        $invoice->loadMissing(['client', 'lineItems', 'installments', 'payments']);

        return Pdf::view('invoices.pdf', $this->viewData($invoice))
            ->name($this->filename($invoice));
    }

    public function filename(Invoice $invoice): string
    {
        return 'invoice-'.$invoice->number.'.pdf';
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(Invoice $invoice): array
    {
        return [
            'invoice' => $invoice,
            'brandName' => config('payments.business.name'),
            'brandEmail' => config('payments.business.email'),
            'brandPhone' => config('payments.business.phone'),
            'brandAddress' => config('payments.business.address'),
            'payUrl' => $this->signedPayUrl($invoice),
        ];
    }

    public function signedPayUrl(Invoice $invoice): string
    {
        $ttl = (int) config('payments.invoice_signed_url_ttl_days', 90);

        return URL::temporarySignedRoute(
            'invoices.public.show',
            now()->addDays($ttl),
            ['invoice' => $invoice->uuid],
        );
    }
}
