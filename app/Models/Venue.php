<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Notifications\Notifiable;

class Venue extends Model implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable;
    use BelongsToSite;
    use CanResetPassword;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'business_name',
        'slug',
        'city',
        'state',
        'region',
        'headline',
        'summary',
        'body',
        'hero_media_id',
        'website_url',
        'google_places_id',
        'referral_emails',
        'referral_contact_name',
        'is_featured',
        'seo_title',
        'seo_description',
        'billing_email',
        'billing_contact_name',
        'billing_address_line_1',
        'billing_address_line_2',
        'billing_city',
        'billing_state',
        'billing_postal_code',
        'billing_country',
        'net_payment_terms',
        'password',
        'email_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'referral_emails' => 'array',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_id');
    }

    public function weddingStories(): HasMany
    {
        return $this->hasMany(WeddingStory::class);
    }

    public function journalPosts(): BelongsToMany
    {
        return $this->belongsToMany(JournalPost::class)->withTimestamps();
    }

    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable')
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps()
            ->orderBy('mediables.sort_order');
    }

    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'billable');
    }

    public function contracts(): MorphMany
    {
        return $this->morphMany(Contract::class, 'billable');
    }

    public function billingName(): string
    {
        return $this->business_name ?: $this->name;
    }

    public function displayName(): string
    {
        return $this->billingName();
    }

    public function portalGreeting(): string
    {
        return $this->billing_contact_name ?: $this->billingName();
    }

    public function getEmailForPasswordReset(): string
    {
        return (string) $this->billing_email;
    }

    public function isBillable(): bool
    {
        return filled($this->billing_email);
    }
}
