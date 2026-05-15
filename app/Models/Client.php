<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Client extends Model implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable;

    /** @use HasFactory<ClientFactory> */
    use CanResetPassword;

    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'partner_first_name',
        'partner_last_name',
        'email',
        'phone',
        'company',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'notes',
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
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            if (empty($client->uuid)) {
                $client->uuid = (string) Str::uuid();
            }
        });
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'billable');
    }

    public function contracts(): MorphMany
    {
        return $this->morphMany(Contract::class, 'billable');
    }

    public function bookedJobs(): HasManyThrough
    {
        return $this->hasManyThrough(BookedJob::class, Inquiry::class);
    }

    /**
     * The booking to surface in the client portal: the soonest non-cancelled
     * job that is undated or still upcoming, across all of the client's
     * inquiries.
     */
    public function currentBookedJob(): ?BookedJob
    {
        return $this->bookedJobs()
            ->where('booked_jobs.status', '!=', 'cancelled')
            ->where(function ($query) {
                $query->whereNull('booked_jobs.event_date')
                    ->orWhere('booked_jobs.event_date', '>=', today());
            })
            ->orderByRaw('booked_jobs.event_date is null')
            ->orderBy('booked_jobs.event_date')
            ->first();
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function portalGreeting(): string
    {
        return $this->first_name ?: $this->displayName();
    }

    public function displayName(): string
    {
        $primary = $this->fullName();

        if ($this->partner_first_name) {
            $partner = trim($this->partner_first_name.' '.$this->partner_last_name);

            return trim($primary.' & '.$partner);
        }

        return $primary;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('partner_first_name', 'like', $like)
                ->orWhere('partner_last_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('company', 'like', $like);
        });
    }
}
