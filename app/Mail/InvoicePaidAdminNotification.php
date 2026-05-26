<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePaidAdminNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string $adminUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment received: '.$this->invoice->billableName().' — invoice '.$this->invoice->number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoices.paid-admin',
            with: [
                'invoice' => $this->invoice,
                'adminUrl' => $this->adminUrl,
                'brandName' => config('payments.business.name'),
            ],
        );
    }
}
