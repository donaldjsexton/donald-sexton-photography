<?php

namespace App\Models;

use App\Models\Concerns\HasBlocks;
use App\Models\Concerns\InteractsWithPicTime;
use App\Support\PicTimeContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class JournalPost extends Model
{
    use HasBlocks;
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

    /**
     * Resolve up to $limit published journal posts conceptually related
     * to $post. Match priority is shared tags, then shared venues, with
     * recent published posts padding any remaining slots so every
     * journal detail page has outgoing internal links.
     *
     * @return Collection<int,self>
     */
    public static function relatedTo(self $post, int $limit = 3): Collection
    {
        if ($limit <= 0) {
            return new Collection;
        }

        $tagIds = $post->relationLoaded('tags')
            ? $post->tags->pluck('id')->all()
            : $post->tags()->pluck('tags.id')->all();

        $venueIds = $post->relationLoaded('venues')
            ? $post->venues->pluck('id')->all()
            : $post->venues()->pluck('venues.id')->all();

        $base = fn () => self::published()
            ->where('id', '!=', $post->id)
            ->with(['heroMedia', 'categories']);

        $results = new Collection;

        if ($tagIds !== []) {
            $tagMatches = $base()
                ->whereHas('tags', fn ($query) => $query->whereIn('tags.id', $tagIds))
                ->latest('published_at')
                ->limit($limit)
                ->get();

            $results = $results->concat($tagMatches);
        }

        $remaining = $limit - $results->count();

        if ($remaining > 0 && $venueIds !== []) {
            $excluded = $results->pluck('id')->all();

            $venueMatches = $base()
                ->when($excluded !== [], fn ($query) => $query->whereNotIn('id', $excluded))
                ->whereHas('venues', fn ($query) => $query->whereIn('venues.id', $venueIds))
                ->latest('published_at')
                ->limit($remaining)
                ->get();

            $results = $results->concat($venueMatches);
        }

        $remaining = $limit - $results->count();

        if ($remaining > 0) {
            $excluded = $results->pluck('id')->all();

            $padding = $base()
                ->when($excluded !== [], fn ($query) => $query->whereNotIn('id', $excluded))
                ->latest('published_at')
                ->limit($remaining)
                ->get();

            $results = $results->concat($padding);
        }

        return $results->values();
    }

    /**
     * Resolve up to $limit published wedding stories related to $post.
     * Venue match takes priority over tag match. No padding — an empty
     * collection means the section should not render.
     *
     * @return Collection<int,WeddingStory>
     */
    public static function relatedStoriesTo(self $post, int $limit = 3): Collection
    {
        if ($limit <= 0) {
            return new Collection;
        }

        $venueIds = $post->relationLoaded('venues')
            ? $post->venues->pluck('id')->all()
            : $post->venues()->pluck('venues.id')->all();

        $tagIds = $post->relationLoaded('tags')
            ? $post->tags->pluck('id')->all()
            : $post->tags()->pluck('tags.id')->all();

        $base = fn () => WeddingStory::published()
            ->with(['heroMedia', 'venue']);

        $results = new Collection;

        if ($venueIds !== []) {
            $venueMatches = $base()
                ->whereIn('venue_id', $venueIds)
                ->latest('published_at')
                ->limit($limit)
                ->get();

            $results = $results->concat($venueMatches);
        }

        $remaining = $limit - $results->count();

        if ($remaining > 0 && $tagIds !== []) {
            $excluded = $results->pluck('id')->all();

            $tagMatches = $base()
                ->when($excluded !== [], fn ($q) => $q->whereNotIn('id', $excluded))
                ->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds))
                ->latest('published_at')
                ->limit($remaining)
                ->get();

            $results = $results->concat($tagMatches);
        }

        return $results->values();
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
        $bodyFallback = trim((string) preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(
                strip_tags((string) ($this->sanitizedBody() ?? '')),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )
        ));

        $copy = $this->excerpt
            ?: PicTimeContent::excerpt($this->picTimeSourceMarkup())
            ?: $bodyFallback;

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
