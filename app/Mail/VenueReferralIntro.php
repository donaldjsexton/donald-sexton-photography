<?php

namespace App\Mail;

use App\Models\Inquiry;
use App\Models\Venue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VenueReferralIntro extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Inquiry $inquiry,
        public Venue $venue,
    ) {}

    public function envelope(): Envelope
    {
        $firstName = trim((string) explode(' ', (string) $this->inquiry->primary_name)[0]);
        $subject = $firstName !== ''
            ? "Hi {$firstName} — congrats from Donald Sexton Photography"
            : 'Congrats from Donald Sexton Photography';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inquiries.venue-referral-intro',
            with: [
                'inquiry' => $this->inquiry,
                'venue' => $this->venue,
            ],
        );
    }
}
