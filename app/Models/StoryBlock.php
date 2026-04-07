<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'wedding_story_id',
        'block_type',
        'heading',
        'body',
        'settings_json',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'settings_json' => 'array',
        ];
    }

    public function weddingStory(): BelongsTo
    {
        return $this->belongsTo(WeddingStory::class);
    }
}
