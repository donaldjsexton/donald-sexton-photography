<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePaid extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string $viewUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment received for invoice '.$this->invoice->number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoices.paid',
            with: [
                'invoice' => $this->invoice,
                'viewUrl' => $this->viewUrl,
                'brandName' => config('payments.business.name'),
            ],
        );
    }
}
