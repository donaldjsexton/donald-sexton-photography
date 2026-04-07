<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomepageSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'hero_heading',
        'hero_subheading',
        'hero_media_id',
        'featured_story_ids_json',
        'featured_testimonial_ids_json',
        'featured_journal_post_ids_json',
        'investment_teaser',
        'final_cta_heading',
        'final_cta_body',
    ];

    protected function casts(): array
    {
        return [
            'featured_story_ids_json' => 'array',
            'featured_testimonial_ids_json' => 'array',
            'featured_journal_post_ids_json' => 'array',
        ];
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_id');
    }
}
