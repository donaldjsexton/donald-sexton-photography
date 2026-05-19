<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceOverdueReminder extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string $payUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reminder: invoice '.$this->invoice->number.' is past due',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoices.overdue-reminder',
            with: [
                'invoice' => $this->invoice,
                'payUrl' => $this->payUrl,
                'brandName' => config('payments.business.name'),
            ],
        );
    }
}
