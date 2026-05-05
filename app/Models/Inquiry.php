<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Inquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'primary_name',
        'partner_name',
        'email',
        'email_secondary',
        'phone',
        'sms_opt_in_transactional',
        'sms_opt_in_marketing',
        'sms_consent_at',
        'sms_consent_ip',
        'instagram_handle',
        'event_type',
        'event_date',
        'venue_name',
        'venue_id',
        'location_city',
        'guest_count_range',
        'budget_range',
        'coverage_interest',
        'heard_about',
        'message',
        'status',
        'source',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'first_responded_at',
        'calendar_event_id',
        'gmail_thread_id',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'coverage_interest' => 'array',
            'first_responded_at' => 'datetime',
            'sms_opt_in_transactional' => 'boolean',
            'sms_opt_in_marketing' => 'boolean',
            'sms_consent_at' => 'datetime',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'new' => 'New',
            'active' => 'Active',
            'follow_up' => 'Follow Up',
            'booked' => 'Booked',
            'archived' => 'Archived',
        ];
    }

    public function scopeAdminOrdered(Builder $query): Builder
    {
        return $query
            ->orderByRaw("
                case status
                    when 'new' then 0
                    when 'active' then 1
                    when 'follow_up' then 2
                    when 'booked' then 3
                    when 'archived' then 4
                    else 5
                end
            ")
            ->orderByRaw('event_date is null')
            ->orderBy('event_date')
            ->latest('created_at');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(InquiryMessage::class)->orderBy('created_at');
    }

    public function questionnaire(): HasOne
    {
        return $this->hasOne(WeddingQuestionnaire::class);
    }

    public function bookedJob(): HasOne
    {
        return $this->hasOne(BookedJob::class);
    }

    public function ensureQuestionnaire(): WeddingQuestionnaire
    {
        return $this->questionnaire()->firstOrCreate([], [
            'token' => Str::random(40),
        ]);
    }
}
