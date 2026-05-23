<?php

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    /**
     * Subdomains that can never be claimed by a tenant.
     *
     * @var list<string>
     */
    public const RESERVED_SUBDOMAINS = ['www', 'admin', 'app', 'api', 'mail', 'ftp', 'cdn', 'assets', 'static'];

    protected $fillable = [
        'name',
        'subdomain',
        'primary_domain',
        'is_default',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->orderBy('id')->first();
    }

    public static function isReservedSubdomain(string $subdomain): bool
    {
        return in_array(strtolower(trim($subdomain)), self::RESERVED_SUBDOMAINS, true);
    }
}
