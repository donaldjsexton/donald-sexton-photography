<?php

namespace App\Mail;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractDeclined extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Contract $contract, public ?string $reason = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Contract '.$this->contract->number.' declined by '.$this->contract->billableName(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contracts.declined',
            with: [
                'contract' => $this->contract,
                'reason' => $this->reason,
                'brandName' => config('payments.business.name'),
            ],
        );
    }
}
