<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Inquiry extends Model
{
    use BelongsToSite;
    use HasFactory;

    protected $fillable = [
        'client_id',
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

    /**
     * The kinds of work the studio takes. Kept as a plain string column
     * rather than an enum so inquiries captured before this vocabulary
     * existed keep whatever they were given.
     *
     * @return array<string, string>
     */
    public static function eventTypeOptions(): array
    {
        return [
            'wedding' => 'Wedding',
            'elopement' => 'Elopement',
            'engagement' => 'Engagement',
            'family' => 'Family',
            'portrait' => 'Portrait',
            'event' => 'Event',
            'commercial' => 'Commercial',
            'other' => 'Other',
        ];
    }

    /**
     * A human label for the stored type, falling back to a tidied version of
     * whatever free text an older inquiry carries.
     */
    public function eventTypeLabel(): string
    {
        $type = (string) $this->event_type;

        return self::eventTypeOptions()[$type]
            ?? (Str::of($type)->replace('_', ' ')->headline()->toString() ?: 'Not provided');
    }

    /**
     * Only weddings get the wedding questionnaire — its schema asks about
     * ceremonies, receptions and first looks.
     */
    public function isWedding(): bool
    {
        return in_array((string) $this->event_type, ['wedding', 'elopement'], true);
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The date the studio actually works to: the booked job owns the agreed
     * date, while the inquiry's own date stays as the prospect requested it.
     */
    public function effectiveEventDate(): ?CarbonInterface
    {
        return $this->bookedJob?->event_date ?? $this->event_date;
    }

    public function ensureQuestionnaire(): WeddingQuestionnaire
    {
        return $this->questionnaire()->firstOrCreate([], [
            'token' => Str::random(40),
            'client_id' => $this->client_id,
        ]);
    }
}
