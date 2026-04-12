<?php

namespace App\Mail;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InquiryAcknowledgment extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Inquiry $inquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thank you, '.$this->inquiry->primary_name.' — I received your note',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inquiries.acknowledgment',
            with: [
                'inquiry' => $this->inquiry,
            ],
        );
    }
}
