<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Database\Factories\BookedJobFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class BookedJob extends Model
{
    use BelongsToSite;

    /** @use HasFactory<BookedJobFactory> */
    use HasFactory;

    protected $fillable = [
        'inquiry_id',
        'google_event_id',
        'summary',
        'couple_names',
        'event_date',
        'previous_event_date',
        'rescheduled_at',
        'reschedule_reason',
        'event_time',
        'location',
        'coordinator',
        'ceremony_notes',
        'status',
        'raw_description',
        'synced_at',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'previous_event_date' => 'date',
            'rescheduled_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'confirmed' => 'Confirmed',
            'cancelled' => 'Cancelled',
            'completed' => 'Completed',
        ];
    }

    /**
     * Jobs that exist but have no agreed date yet. These are real bookings —
     * they carry contracts and invoices — they simply are not on the calendar.
     */
    public function scopeAwaitingDate(Builder $query): Builder
    {
        return $query->whereNull('event_date')
            ->where('status', '!=', 'cancelled');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereNotNull('event_date')
            ->where('event_date', '>=', today())
            ->where('status', 'confirmed')
            ->orderBy('event_date');
    }

    public function scopeInMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('event_date', $year)
            ->whereMonth('event_date', $month);
    }

    /**
     * Nothing is final until the contract is signed and the retainer is paid.
     * The tier is derived from the contracts and invoices attached to this
     * job rather than stored, so it can never drift from those records.
     *
     * @return 'tentative'|'held'|'confirmed'
     */
    public function confirmationStage(): string
    {
        if (! $this->hasSignedContract()) {
            return 'tentative';
        }

        return $this->retainerPaid() ? 'confirmed' : 'held';
    }

    public function confirmationLabel(): string
    {
        return match ($this->confirmationStage()) {
            'confirmed' => 'Confirmed',
            'held' => 'Held — awaiting retainer',
            default => 'Tentative — no signed contract',
        };
    }

    public function hasSignedContract(): bool
    {
        return $this->contracts()
            ->where('status', Contract::STATUS_SIGNED)
            ->exists();
    }

    /**
     * True once the client's money has actually landed: the first installment
     * of an attached client invoice is paid, or an invoice carrying no
     * installments is paid in full. Vendor invoices are not a retainer.
     */
    public function retainerPaid(): bool
    {
        return $this->invoices()
            ->where('status', '!=', Invoice::STATUS_VOID)
            ->with('installments')
            ->get()
            ->filter(fn (Invoice $invoice): bool => $invoice->isClientInvoice())
            ->contains(function (Invoice $invoice): bool {
                $retainer = $invoice->installments
                    ->where('status', '!=', InvoiceInstallment::STATUS_VOID)
                    ->sortBy('sequence')
                    ->first();

                return $retainer
                    ? $retainer->status === InvoiceInstallment::STATUS_PAID
                    : $invoice->isPaid();
            });
    }

    /**
     * Once a contract is signed the event date is quoted verbatim in an
     * executed document, so it may only change through an explicit
     * reschedule that records why.
     */
    public function isDateLocked(): bool
    {
        return $this->hasSignedContract();
    }

    public function isAwaitingDate(): bool
    {
        return $this->event_date === null;
    }

    /**
     * Signed contracts whose rendered body still quotes the date this job
     * was moved away from, and therefore need an amendment.
     */
    public function contractsNeedingAmendment(): Collection
    {
        if ($this->rescheduled_at === null) {
            return new Collection;
        }

        return $this->contracts()
            ->where('status', Contract::STATUS_SIGNED)
            ->where('signed_at', '<', $this->rescheduled_at)
            ->get();
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function portalStage(): string
    {
        if ($this->status === 'cancelled') {
            return 'Cancelled';
        }

        if ($this->status === 'completed') {
            return 'Completed';
        }

        $stage = match ($this->confirmationStage()) {
            'confirmed' => 'Confirmed',
            'held' => 'Date Held',
            default => 'Tentative',
        };

        $eventDate = $this->event_date;
        if ($eventDate === null) {
            return $stage;
        }

        $today = today();
        if ($eventDate->lt($today)) {
            return 'Completed';
        }

        if ($eventDate->isSameDay($today)) {
            return 'Today';
        }

        if ($today->diffInDays($eventDate, false) <= 30) {
            return 'Upcoming';
        }

        return $stage;
    }
}
