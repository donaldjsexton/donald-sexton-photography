<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'primary_name',
        'partner_name',
        'email',
        'phone',
        'instagram_handle',
        'event_type',
        'event_date',
        'venue_name',
        'venue_id',
        'location_city',
        'guest_count_range',
        'budget_range',
        'coverage_interest',
        'heard_about',
        'message',
        'status',
        'source',
        'utm_source',
        'utm_medium',
        'utm_campaign',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'coverage_interest' => 'array',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
