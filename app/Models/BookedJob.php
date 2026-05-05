<?php

namespace App\Models;

use Database\Factories\BookedJobFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookedJob extends Model
{
    /** @use HasFactory<BookedJobFactory> */
    use HasFactory;

    protected $fillable = [
        'inquiry_id',
        'google_event_id',
        'summary',
        'couple_names',
        'event_date',
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

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
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

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('event_date', '>=', today())
            ->where('status', 'confirmed')
            ->orderBy('event_date');
    }

    public function scopeInMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('event_date', $year)
            ->whereMonth('event_date', $month);
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
