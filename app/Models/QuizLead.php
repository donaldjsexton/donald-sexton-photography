<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizLead extends Model
{
    protected $fillable = [
        'first_name', 'email', 'result', 'score', 'answers', 'wedding_date_text', 'venue_text',
        'consent_at', 'consent_ip', 'unsubscribe_token', 'unsubscribed_at', 'result_emailed_at',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'consent_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'result_emailed_at' => 'datetime',
        ];
    }
}
