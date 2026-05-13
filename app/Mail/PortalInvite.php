<?php

namespace App\Mail;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PortalInvite extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Client $client,
        public string $setupUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Set up your '.config('payments.business.name').' client portal',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal.invite',
            with: [
                'client' => $this->client,
                'setupUrl' => $this->setupUrl,
                'brandName' => config('payments.business.name'),
            ],
        );
    }
}
