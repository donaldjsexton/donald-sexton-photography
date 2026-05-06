<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InquiryMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'inquiry_id',
        'direction',
        'body',
        'sender_name',
        'sender_email',
        'sent_at',
        'gmail_message_id',
        'gmail_thread_id',
        'subject',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }
}
