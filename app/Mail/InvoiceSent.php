<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Services\Invoicing\InvoicePdfRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceSent extends Mailable
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
            subject: 'Invoice '.$this->invoice->number.' from '.config('payments.business.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoices.sent',
            with: [
                'invoice' => $this->invoice,
                'payUrl' => $this->payUrl,
                'brandName' => config('payments.business.name'),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $renderer = app(InvoicePdfRenderer::class);

        return [
            Attachment::fromData(
                fn () => base64_decode($renderer->build($this->invoice)->base64()),
                $renderer->filename($this->invoice),
            )->withMime('application/pdf'),
        ];
    }
}
