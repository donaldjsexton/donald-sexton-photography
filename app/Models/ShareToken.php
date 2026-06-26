<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Database\Factories\ShareTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class ShareToken extends Model
{
    use BelongsToSite;

    /** @use HasFactory<ShareTokenFactory> */
    use HasFactory;

    protected $fillable = [
        'site_id',
        'token',
        'shareable_type',
        'shareable_id',
        'expires_at',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ShareToken $shareToken): void {
            if (empty($shareToken->token)) {
                $shareToken->token = Str::random(48);
            }
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isPasswordProtected(): bool
    {
        return $this->password !== null;
    }
}
