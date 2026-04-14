<?php

namespace App\Mail;

use App\Models\WeddingQuestionnaire;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeddingQuestionnaireReceived extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public WeddingQuestionnaire $questionnaire) {}

    public function envelope(): Envelope
    {
        $name = $this->questionnaire->response('bride_name')
            ?: $this->questionnaire->inquiry->primary_name;

        return new Envelope(
            subject: 'Wedding questionnaire submitted — '.$name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.questionnaires.received',
            with: [
                'questionnaire' => $this->questionnaire,
                'inquiry' => $this->questionnaire->inquiry,
                'schema' => WeddingQuestionnaire::schema(),
            ],
        );
    }
}
