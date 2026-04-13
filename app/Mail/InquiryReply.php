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
            subject: 'Re: Your inquiry — Donald Sexton Photography',
        );
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
