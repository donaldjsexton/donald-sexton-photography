<?php

namespace App\Models;

use Database\Factories\InvoiceInstallmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceInstallment extends Model
{
    /** @use HasFactory<InvoiceInstallmentFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'invoice_id',
        'sequence',
        'label',
        'due_date',
        'amount_cents',
        'amount_paid_cents',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'amount_cents' => 'integer',
            'amount_paid_cents' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function amountDueCents(): int
    {
        return max(0, $this->amount_cents - $this->amount_paid_cents);
    }

    public function syncStatusFromPayments(bool $persist = true): self
    {
        $paid = (int) $this->payments()
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount_cents');

        $this->amount_paid_cents = $paid;

        if ($this->status === self::STATUS_VOID) {
            if ($persist) {
                $this->save();
            }

            return $this;
        }

        if ($paid <= 0) {
            $this->status = $this->isOverdue() ? self::STATUS_OVERDUE : self::STATUS_PENDING;
        } elseif ($paid >= $this->amount_cents) {
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

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && $this->amountDueCents() > 0;
    }
}
