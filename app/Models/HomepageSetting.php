<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use App\Models\Concerns\HasBlocks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomepageSetting extends Model
{
    use BelongsToSite;
    use HasBlocks;
    use HasFactory;

    protected $fillable = [
        'site_id',
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
