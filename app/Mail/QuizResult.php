<?php

namespace App\Mail;

use App\Models\QuizLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuizResult extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @param array<string, mixed> $tier */
    public function __construct(public QuizLead $lead, public array $tier, public string $why, public bool $second, public string $inquiryUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->lead->first_name.', your wedding coverage plan: '.$this->tier['hours'].' hours');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.quiz.result', with: [
            'lead' => $this->lead,
            'tier' => $this->tier,
            'why' => $this->why,
            'second' => $this->second,
            'inquiryUrl' => $this->inquiryUrl,
            'unsubscribeUrl' => route('quiz.unsubscribe', $this->lead->unsubscribe_token),
        ]);
    }
}
