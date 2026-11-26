<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contact extends Model
{
    use BelongsToSite;

    public const TYPES = [
        'couple' => 'Couple',
        'past_couple' => 'Past couple',
        'prospect' => 'Prospect',
        'planner_vendor' => 'Planner / vendor',
        'other' => 'Other',
    ];

    public const STAGES = [
        'lead' => 'Lead',
        'inquiry' => 'Inquiry',
        'consult' => 'Consult',
        'booked' => 'Booked',
        'shot' => 'Shot',
        'delivered' => 'Delivered',
        'review' => 'Review / referral',
        'lost' => 'Lost',
    ];

    protected $fillable = [
        'email', 'name', 'partner_name', 'phone', 'type', 'stage', 'shoot_type', 'event_date',
        'source', 'source_detail', 'notes', 'first_contact_at', 'last_contact_at', 'client_id',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'first_contact_at' => 'datetime',
            'last_contact_at' => 'datetime',
        ];
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = self::normalizeEmail($value);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<Inquiry, $this> */
    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    /** @return HasMany<QuizLead, $this> */
    public function quizLeads(): HasMany
    {
        return $this->hasMany(QuizLead::class);
    }

    /** @return HasOne<Suppression, $this> */
    public function suppression(): HasOne
    {
        return $this->hasOne(Suppression::class);
    }

    public function isSuppressed(): bool
    {
        return Suppression::query()->where('email', $this->email)->exists();
    }

    /** An active client has a portal account or at least one booked inquiry. */
    public function isActiveClient(): bool
    {
        return $this->client_id !== null
            || $this->inquiries()->where('status', 'booked')->exists();
    }
}
