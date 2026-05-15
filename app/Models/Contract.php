<?php

namespace App\Models;

use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'uuid',
        'number',
        'billable_type',
        'billable_id',
        'booked_job_id',
        'invoice_id',
        'contract_template_id',
        'status',
        'title',
        'body',
        'issue_date',
        'expires_at',
        'signer_name',
        'signer_email',
        'signer_ip',
        'signer_user_agent',
        'internal_notes',
        'sent_at',
        'viewed_at',
        'signed_at',
        'declined_at',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expires_at' => 'date',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'signed_at' => 'datetime',
            'declined_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SENT => 'Sent',
            self::STATUS_SIGNED => 'Signed',
            self::STATUS_DECLINED => 'Declined',
            self::STATUS_VOID => 'Void',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Contract $contract) {
            if (empty($contract->uuid)) {
                $contract->uuid = (string) Str::uuid();
            }
            if (empty($contract->number)) {
                $contract->number = self::generateNumber();
            }
            if (empty($contract->issue_date)) {
                $contract->issue_date = Carbon::today();
            }
        });
    }

    public static function generateNumber(): string
    {
        $prefix = config('contracts.number_prefix', 'CTR');
        $year = Carbon::now()->year;

        $latest = static::query()
            ->where('number', 'like', "{$prefix}-{$year}-%")
            ->orderByDesc('id')
            ->value('number');

        $sequence = 1;
        if ($latest) {
            $tail = (int) substr($latest, strrpos($latest, '-') + 1);
            $sequence = $tail + 1;
        }

        return sprintf('%s-%d-%04d', $prefix, $year, $sequence);
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function bookedJob(): BelongsTo
    {
        return $this->belongsTo(BookedJob::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    public function billableEmail(): ?string
    {
        $billable = $this->billable;

        if ($billable instanceof Client) {
            return $billable->email;
        }

        if ($billable instanceof Venue) {
            return $billable->billing_email;
        }

        return null;
    }

    public function billableName(): string
    {
        $billable = $this->billable;

        if ($billable && method_exists($billable, 'displayName')) {
            return (string) $billable->displayName();
        }

        return (string) ($billable->name ?? '');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isProposal(): bool
    {
        return $this->invoice_id !== null;
    }

    public function isSigned(): bool
    {
        return $this->status === self::STATUS_SIGNED;
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function isAwaitingSignature(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->isPast()
            && ! $this->isSigned();
    }

    public function scopeAwaitingSignature(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }
}
