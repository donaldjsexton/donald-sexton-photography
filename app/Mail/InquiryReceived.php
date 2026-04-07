<?php

namespace App\Mail;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InquiryReceived extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Inquiry $inquiry)
    {
    }

    public function envelope(): Envelope
    {
        $subject = 'New inquiry from '.$this->inquiry->primary_name;

        if ($this->inquiry->event_date) {
            $subject .= ' for '.$this->inquiry->event_date->format('F j, Y');
        }

        return new Envelope(
            subject: $subject,
            replyTo: array_filter([
                $this->inquiry->email
                    ? new Address($this->inquiry->email, $this->inquiry->primary_name)
                    : null,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inquiries.received',
            with: [
                'inquiry' => $this->inquiry,
            ],
        );
    }
}
