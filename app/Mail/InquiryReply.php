<?php

namespace App\Mail;

use App\Models\Inquiry;
use App\Models\InquiryMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InquiryReply extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Inquiry $inquiry,
        public InquiryMessage $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->resolveSubject(),
        );
    }

    private function resolveSubject(): string
    {
        if (! $this->isInitialAdminOutreach()) {
            return 'Re: Your inquiry — Donald Sexton Photography';
        }

        $eventType = str((string) $this->inquiry->event_type)
            ->replace('_', ' ')
            ->headline()
            ->toString();

        if ($eventType === '') {
            $eventType = 'Photography';
        }

        $eventDate = $this->inquiry->event_date?->format('F j, Y');

        return $eventDate !== null
            ? "Donald Sexton Photography — {$eventType} on {$eventDate}"
            : "Donald Sexton Photography — {$eventType} inquiry";
    }

    private function isInitialAdminOutreach(): bool
    {
        if ($this->inquiry->source !== 'admin') {
            return false;
        }

        return $this->inquiry->messages()
            ->where('direction', 'outbound')
            ->whereKeyNot($this->message->getKey())
            ->doesntExist();
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inquiries.reply',
            with: [
                'inquiry' => $this->inquiry,
                'reply' => $this->message,
            ],
        );
    }
}
