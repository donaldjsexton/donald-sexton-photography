<?php

namespace App\Models;

use App\Models\Concerns\InteractsWithPicTime;
use App\Support\PicTimeContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class JournalPost extends Model
{
    use HasFactory;
    use InteractsWithPicTime;

    protected $fillable = [
        'title',
        'slug',
        'status',
        'post_type',
        'excerpt',
        'body',
        'source_markup',
        'hero_media_id',
        'author_name',
        'published_at',
        'original_wp_post_id',
        'original_wp_url',
        'seo_title',
        'seo_description',
        'canonical_url',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    public function venues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class)->withTimestamps();
    }

    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable')
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps()
            ->orderBy('mediables.sort_order');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function getPostTypeLabelAttribute(): string
    {
        return Str::of($this->post_type)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    public function sanitizedBody(): ?string
    {
        $body = (string) $this->body;

        if ($body === '') {
            return null;
        }

        $body = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $body) ?? $body;
        $body = preg_replace('/<template\b[^>]*>.*?<\/template>/is', '', $body) ?? $body;

        return $this->sanitizeImportedHtml($body);
    }

    public function summaryText(?int $words = null): ?string
    {
        $copy = $this->excerpt
            ?: PicTimeContent::excerpt($this->picTimeSourceMarkup())
            ?: trim(strip_tags((string) ($this->sanitizedBody() ?? '')));

        if ($copy === '') {
            return null;
        }

        return $words ? Str::words($copy, $words) : $copy;
    }

    public function detailBodyHtml(): ?string
    {
        return $this->stripLeadingDuplicateParagraph(
            $this->sanitizedBody(),
            $this->excerpt ?: PicTimeContent::excerpt($this->picTimeSourceMarkup())
        );
    }

    public function detailHeroCopy(): ?string
    {
        $copy = $this->summaryText(40);

        if (filled($copy)) {
            return $copy;
        }

        return match ($this->picTimeDetailMode()) {
            'gallery_first' => 'The gallery from this post is shown below.',
            'rich_local_gallery' => 'The full gallery from this post is shown below.',
            'thin_fallback' => $this->externalGallerySummary(),
            default => null,
        };
    }

    public function featuredImageUrl(): ?string
    {
        if ($this->heroMedia?->path) {
            return $this->heroMedia->publicUrl();
        }

        return $this->featuredImageUrlFromBody();
    }

    public function picTimeSourceMarkup(): ?string
    {
        return $this->preferredPicTimeMarkup([
            trim((string) $this->source_markup),
            trim((string) $this->body),
        ]);
    }

    public function picTimeEmbedMarkup(): ?string
    {
        if (! $this->isPicTimeSource()) {
            return null;
        }

        return PicTimeContent::normalizedEmbedMarkup($this->picTimeSourceMarkup(), $this->externalGalleryUrl());
    }

    public function picTimeNarrativeBlocks(): array
    {
        return PicTimeContent::narrativeBlocks($this->picTimeSourceMarkup());
    }
}
