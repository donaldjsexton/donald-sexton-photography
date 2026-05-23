<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A custom domain pointing at a tenant. Deliberately not tenant-scoped: it is
 * resolved from the request host before the tenant context exists.
 */
class SiteDomain extends Model
{
    /** DNS TXT record host prefix used for ownership verification. */
    public const VERIFICATION_PREFIX = '_dsp-verify';

    protected $fillable = [
        'site_id',
        'host',
        'verification_token',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function verificationRecordName(): string
    {
        return self::VERIFICATION_PREFIX.'.'.$this->host;
    }
}
