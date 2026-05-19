<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_VOID = 'void';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'uuid',
        'number',
        'billable_type',
        'billable_id',
        'booked_job_id',
        'status',
        'currency',
        'issue_date',
        'due_date',
        'subtotal_cents',
        'discount_cents',
        'tax_cents',
        'total_cents',
        'amount_paid_cents',
        'default_tax_rate',
        'net_terms',
        'notes',
        'internal_notes',
        'terms',
        'sent_at',
        'viewed_at',
        'paid_at',
        'voided_at',
        'overdue_reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'overdue_reminder_sent_at' => 'datetime',
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'amount_paid_cents' => 'integer',
            'default_tax_rate' => 'decimal:3',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SENT => 'Sent',
            self::STATUS_PARTIALLY_PAID => 'Partially Paid',
            self::STATUS_PAID => 'Paid',
            self::STATUS_OVERDUE => 'Overdue',
            self::STATUS_VOID => 'Void',
            self::STATUS_REFUNDED => 'Refunded',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            if (empty($invoice->uuid)) {
                $invoice->uuid = (string) Str::uuid();
            }
            if (empty($invoice->number)) {
                $invoice->number = self::generateNumber();
            }
            if (empty($invoice->issue_date)) {
                $invoice->issue_date = Carbon::today();
            }
        });
    }

    public static function generateNumber(): string
    {
        $prefix = config('payments.invoice_number_prefix', 'INV');
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

    public function client(): MorphTo
    {
        return $this->billable();
    }

    public function bookedJob(): BelongsTo
    {
        return $this->belongsTo(BookedJob::class);
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

        return $billable->name ?? '';
    }

    public function isVendorInvoice(): bool
    {
        return $this->billable instanceof Venue;
    }

    public function isClientInvoice(): bool
    {
        return $this->billable instanceof Client;
    }

    public function canPayOnline(): bool
    {
        return $this->isClientInvoice()
            && $this->amountDueCents() > 0
            && $this->status !== self::STATUS_VOID;
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class)->orderBy('sort_order');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(InvoiceInstallment::class)->orderBy('sequence');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function amountDueCents(): int
    {
        return max(0, $this->total_cents - $this->amount_paid_cents);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID || $this->amountDueCents() === 0;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT], true);
    }

    public function recalculateTotals(bool $persist = true): self
    {
        $this->loadMissing('lineItems');

        $subtotal = 0;
        $tax = 0;

        foreach ($this->lineItems as $item) {
            $subtotal += $item->subtotal_cents;
            $tax += $item->tax_cents;
        }

        $discount = (int) $this->discount_cents;
        $total = max(0, $subtotal - $discount + $tax);

        $this->subtotal_cents = $subtotal;
        $this->tax_cents = $tax;
        $this->total_cents = $total;

        if ($persist) {
            $this->save();
        }

        return $this;
    }

    public function syncStatusFromPayments(bool $persist = true): self
    {
        $paid = (int) $this->payments()
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount_cents');

        $this->amount_paid_cents = $paid;

        if ($this->status === self::STATUS_VOID || $this->status === self::STATUS_DRAFT) {
            if ($persist) {
                $this->save();
            }

            return $this;
        }

        if ($paid <= 0) {
            $this->status = $this->sent_at ? self::STATUS_SENT : self::STATUS_DRAFT;
        } elseif ($paid >= $this->total_cents && $this->total_cents > 0) {
            $this->status = self::STATUS_PAID;
            $this->paid_at = $this->paid_at ?? now();
        } else {
            $this->status = self::STATUS_PARTIALLY_PAID;
        }

        if ($persist) {
            $this->save();
        }

        return $this;
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_SENT,
            self::STATUS_PARTIALLY_PAID,
            self::STATUS_OVERDUE,
        ]);
    }
}
