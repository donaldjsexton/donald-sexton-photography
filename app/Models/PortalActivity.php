<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Database\Factories\PortalActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

class PortalActivity extends Model
{
    use BelongsToSite;

    /** @use HasFactory<PortalActivityFactory> */
    use HasFactory;

    public const TYPE_LOGIN = 'login';

    public const TYPE_CONTRACT_VIEWED = 'contract_viewed';

    public const TYPE_INVOICE_VIEWED = 'invoice_viewed';

    protected $fillable = [
        'actor_type',
        'actor_id',
        'type',
        'subject_type',
        'subject_id',
        'ip_address',
        'user_agent',
    ];

    /**
     * Append a portal activity entry for the given actor, capturing the
     * request's IP address and (truncated) user agent.
     */
    public static function record(Model $actor, string $type, Request $request, ?Model $subject = null): self
    {
        return static::create([
            'actor_type' => $actor->getMorphClass(),
            'actor_id' => $actor->getKey(),
            'type' => $type,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512) ?: null,
        ]);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
