<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use BelongsToSite;
    use HasFactory;

    protected $fillable = [
        'quote',
        'author_name',
        'author_context',
        'event_date',
        'is_featured',
        'source',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'is_featured' => 'boolean',
        ];
    }
}
