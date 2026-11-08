<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CmsMcpCredential extends Model
{
    use BelongsToSite;

    public const READ = 'stories:read';

    public const DRAFT = 'stories:draft';

    public const UPLOAD = 'media:upload';

    public const PUBLISH = 'stories:publish';

    /**
     * @var list<string>
     */
    public const ABILITIES = [
        self::READ,
        self::DRAFT,
        self::UPLOAD,
        self::PUBLISH,
    ];

    /**
     * Draft and upload access. Publishing is opt-in.
     *
     * @var list<string>
     */
    public const DEFAULT_ABILITIES = [
        self::READ,
        self::DRAFT,
        self::UPLOAD,
    ];

    protected $fillable = [
        'site_id',
        'name',
        'token_hash',
        'abilities',
        'expires_at',
        'revoked_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<CmsMcpUploadReceipt, $this>
     */
    public function uploadReceipts(): HasMany
    {
        return $this->hasMany(CmsMcpUploadReceipt::class);
    }

    public function allows(string $ability): bool
    {
        return in_array($ability, $this->abilities ?? [], true);
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @param  list<string>  $abilities
     * @return array{credential: self, token: string}
     */
    public static function issue(string $name, array $abilities, ?Carbon $expiresAt = null): array
    {
        $token = 'dsp_mcp_'.Str::random(40);

        $credential = static::query()->create([
            'name' => $name,
            'token_hash' => static::hashToken($token),
            'abilities' => array_values(array_unique($abilities)),
            'expires_at' => $expiresAt,
        ]);

        return [
            'credential' => $credential,
            'token' => $token,
        ];
    }
}
