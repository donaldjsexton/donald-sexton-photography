<?php

namespace App\Mail;

use App\Models\Contract;
use App\Models\Invoice;
use App\Services\Contracts\ContractPdfRenderer;
use App\Services\Invoicing\InvoicePdfRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProposalSent extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Contract $contract,
        public Invoice $invoice,
        public string $proposalUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your booking proposal from '.config('payments.business.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.proposals.sent',
            with: [
                'contract' => $this->contract,
                'invoice' => $this->invoice,
                'proposalUrl' => $this->proposalUrl,
                'brandName' => config('payments.business.name'),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $contractRenderer = app(ContractPdfRenderer::class);
        $invoiceRenderer = app(InvoicePdfRenderer::class);

        return [
            Attachment::fromData(
                fn () => base64_decode($contractRenderer->build($this->contract)->base64()),
                $contractRenderer->filename($this->contract),
            )->withMime('application/pdf'),
            Attachment::fromData(
                fn () => base64_decode($invoiceRenderer->build($this->invoice)->base64()),
                $invoiceRenderer->filename($this->invoice),
            )->withMime('application/pdf'),
        ];
    }
}
