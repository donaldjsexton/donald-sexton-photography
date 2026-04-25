<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Venue extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'city',
        'state',
        'region',
        'headline',
        'summary',
        'body',
        'hero_media_id',
        'website_url',
        'google_places_id',
        'referral_emails',
        'referral_contact_name',
        'is_featured',
        'seo_title',
        'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'referral_emails' => 'array',
        ];
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_id');
    }

    public function weddingStories(): HasMany
    {
        return $this->hasMany(WeddingStory::class);
    }

    public function journalPosts(): BelongsToMany
    {
        return $this->belongsToMany(JournalPost::class)->withTimestamps();
    }

    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable')
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps()
            ->orderBy('mediables.sort_order');
    }
}
