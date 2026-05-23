<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    use BelongsToSite;
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'headline',
        'summary',
        'description',
        'starting_price',
        'price_label',
        'coverage_hours_min',
        'coverage_hours_max',
        'display_order',
        'is_featured',
        'status',
        'seo_title',
        'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'starting_price' => 'decimal:2',
            'is_featured' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
