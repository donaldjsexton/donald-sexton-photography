<?php

namespace App\Mail;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class InquiryAcknowledgment extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{status: string, event_date: ?Carbon, nearby_dates: array<int, Carbon>}|null  $availability
     */
    public function __construct(public Inquiry $inquiry, public ?array $availability = null) {}

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
                'availability' => $this->availability ?? [
                    'status' => 'unknown',
                    'event_date' => $this->inquiry->event_date,
                    'nearby_dates' => [],
                ],
            ],
        );
    }
}
