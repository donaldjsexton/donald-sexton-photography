<?php

namespace App\Mail;

use App\Models\WeddingQuestionnaire;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuestionnaireInvitation extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public WeddingQuestionnaire $questionnaire,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your '.config('payments.business.name').' wedding questionnaire',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.questionnaires.invitation',
            with: [
                'url' => $this->questionnaire->publicUrl(),
                'brandName' => config('payments.business.name'),
            ],
        );
    }
}
