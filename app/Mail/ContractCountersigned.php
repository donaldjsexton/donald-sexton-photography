<?php

namespace App\Mail;

use App\Models\Contract;
use App\Services\Contracts\ContractPdfRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractCountersigned extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Contract $contract) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Contract '.$this->contract->number.' is fully executed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contracts.countersigned',
            with: [
                'contract' => $this->contract,
                'brandName' => config('payments.business.name'),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $renderer = app(ContractPdfRenderer::class);

        return [
            Attachment::fromData(
                fn () => base64_decode($renderer->build($this->contract)->base64()),
                $renderer->filename($this->contract),
            )->withMime('application/pdf'),
        ];
    }
}
