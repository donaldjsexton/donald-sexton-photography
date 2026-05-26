<?php

namespace App\Observers;

use App\Mail\InvoicePaid;
use App\Mail\InvoicePaidAdminNotification;
use App\Models\Invoice;
use App\Services\Invoicing\InvoicePdfRenderer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class InvoicePaymentObserver
{
    public function __construct(private readonly InvoicePdfRenderer $renderer) {}

    public function updated(Invoice $invoice): void
    {
        if (! $invoice->wasChanged('status')) {
            return;
        }

        if ($invoice->status !== Invoice::STATUS_PAID) {
            return;
        }

        if ($invoice->getOriginal('status') === Invoice::STATUS_PAID) {
            return;
        }

        $this->notifyClient($invoice);
        $this->notifyAdmin($invoice);
    }

    private function notifyClient(Invoice $invoice): void
    {
        $email = $invoice->billableEmail();

        if (! $email) {
            return;
        }

        try {
            Mail::to($email, $invoice->billableName())->send(new InvoicePaid(
                invoice: $invoice,
                viewUrl: $this->renderer->signedPayUrl($invoice),
            ));
        } catch (Throwable $e) {
            Log::error('Failed to send InvoicePaid email to client', [
                'invoice_id' => $invoice->id,
                'email' => $email,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function notifyAdmin(Invoice $invoice): void
    {
        $email = config('payments.business.email') ?: config('mail.from.address');

        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new InvoicePaidAdminNotification(
                invoice: $invoice,
                adminUrl: URL::route('admin.invoices.show', $invoice),
            ));
        } catch (Throwable $e) {
            Log::error('Failed to send InvoicePaid admin notification', [
                'invoice_id' => $invoice->id,
                'email' => $email,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
