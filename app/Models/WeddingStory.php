<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use App\Models\Concerns\HasBlocks;
use App\Models\Concerns\InteractsWithPicTime;
use App\Models\Concerns\PresentsImportedContent;
use App\Support\PicTimeContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WeddingStory extends Model
{
    use BelongsToSite;
    use HasBlocks;
    use HasFactory;
    use InteractsWithPicTime;
    use PresentsImportedContent;

    protected $fillable = [
        'site_id',
        'title',
        'slug',
        'status',
        'story_type',
        'headline',
        'excerpt',
        'body',
        'source_markup',
        'hero_media_id',
        'gallery_id',
        'event_date',
        'location_name',
        'city',
        'state',
        'venue_id',
        'client_names',
        'is_featured',
        'display_order',
        'seo_title',
        'seo_description',
        'canonical_url',
        'original_wp_post_id',
        'original_wp_url',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'client_names' => 'array',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_id');
    }

    /**
     * The native client gallery delivering this story's photos, if linked.
     *
     * @return BelongsTo<Gallery, $this>
     */
    public function clientGallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class, 'gallery_id');
    }

    public function hasClientGallery(): bool
    {
        return $this->gallery_id !== null;
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
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
     * Order stories newest first, falling back to the creation date when a
     * story has no explicit publish date so recent entries are never buried
     * under older imported archives.
     */
    public function scopeRecentFirst(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->orderByRaw("COALESCE({$table}.published_at, {$table}.created_at) DESC")
            ->orderByDesc($table.'.id');
    }

    /**
     * Resolve up to $limit published wedding stories that are conceptually
     * similar to $story. Match priority is venue first (couples shopping
     * a single venue care most about other weddings there), then tag
     * overlap, with recent published stories padding any remaining slots
     * so every detail page has outgoing internal links.
     *
     * @return Collection<int,self>
     */
    public static function similarTo(self $story, int $limit = 3): Collection
    {
        if ($limit <= 0) {
            return new Collection;
        }

        $tagIds = $story->relationLoaded('tags')
            ? $story->tags->pluck('id')->all()
            : $story->tags()->pluck('tags.id')->all();

        $base = fn () => self::published()
            ->where('id', '!=', $story->id)
            ->with(['heroMedia', 'venue']);

        $results = new Collection;

        if ($story->venue_id) {
            $venueMatches = $base()
                ->where('venue_id', $story->venue_id)
                ->latest('published_at')
                ->limit($limit)
                ->get();

            $results = $results->concat($venueMatches);
        }

        $remaining = $limit - $results->count();

        if ($remaining > 0 && $tagIds !== []) {
            $excluded = $results->pluck('id')->all();

            $tagMatches = $base()
                ->when($excluded !== [], fn ($query) => $query->whereNotIn('id', $excluded))
                ->whereHas('tags', fn ($query) => $query->whereIn('tags.id', $tagIds))
                ->latest('published_at')
                ->limit($remaining)
                ->get();

            $results = $results->concat($tagMatches);
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
     * Resolve up to $limit published journal posts related to $story.
     * Venue match takes priority over tag match. No padding — an empty
     * collection means the section should not render.
     *
     * @return Collection<int,JournalPost>
     */
    public static function relatedPostsTo(self $story, int $limit = 3): Collection
    {
        if ($limit <= 0) {
            return new Collection;
        }

        $tagIds = $story->relationLoaded('tags')
            ? $story->tags->pluck('id')->all()
            : $story->tags()->pluck('tags.id')->all();

        $base = fn () => JournalPost::published()
            ->with(['heroMedia', 'categories']);

        $results = new Collection;

        if ($story->venue_id) {
            $venueMatches = $base()
                ->whereHas('venues', fn ($q) => $q->where('venues.id', $story->venue_id))
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

    public function getStoryTypeLabelAttribute(): string
    {
        return Str::of($this->story_type)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    /**
     * @return array{hero_copy: ?string, gallery_html: ?string, body_html: ?string}
     */
    public function presentationContent(): array
    {
        return $this->importedPresentationContent(
            trim((string) $this->sanitizedBody()),
            $this->excerpt ?: PicTimeContent::excerpt($this->picTimeSourceMarkup())
        );
    }

    public function summaryText(?int $words = null): ?string
    {
        $fallback = $this->normalizeImportedText($this->sanitizedBody() ?? '');
        $copy = $this->presentationContent()['hero_copy']
            ?: $this->excerpt
            ?: PicTimeContent::excerpt($this->picTimeSourceMarkup())
            ?: $fallback;

        if ($copy === '') {
            return null;
        }

        return $words ? Str::words($copy, $words) : $copy;
    }

    public function detailBodyHtml(): ?string
    {
        $presentation = $this->presentationContent();

        return $this->stripLeadingDuplicateParagraph(
            $presentation['body_html'] ?? null,
            $presentation['hero_copy'] ?? $this->excerpt
        );
    }

    public function detailHeroCopy(): ?string
    {
        $presentation = $this->presentationContent();
        $copy = $presentation['hero_copy'];

        if (filled($copy)) {
            return $copy;
        }

        return match ($this->picTimeDetailMode()) {
            'gallery_first' => 'The gallery from this wedding is shown below.',
            'rich_local_gallery' => 'The full gallery from this wedding is shown below.',
            'thin_fallback' => $this->externalGallerySummary(),
            default => null,
        };
    }

    public function featuredImageUrl(): ?string
    {
        if ($this->heroMedia?->path) {
            return $this->heroMedia->publicUrl();
        }

        if ($this->relationLoaded('media')) {
            $firstAttached = $this->media->first(fn (Media $media) => filled($media->path));

            if ($firstAttached) {
                return $firstAttached->publicUrl();
            }
        }

        return $this->featuredImageUrlFromBody();
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

    private function rawPicTimeEmbedMarkup(): ?string
    {
        $candidates = [
            trim((string) $this->source_markup),
            trim((string) $this->body),
        ];

        if (Schema::hasTable('journal_posts')) {
            if ($this->original_wp_post_id) {
                $candidates[] = trim((string) JournalPost::query()
                    ->where('original_wp_post_id', $this->original_wp_post_id)
                    ->orderByDesc('id')
                    ->value('body'));
            }

            if ($this->canonical_url) {
                $candidates[] = trim((string) JournalPost::query()
                    ->where('canonical_url', $this->canonical_url)
                    ->orderByDesc('id')
                    ->value('source_markup'));

                $candidates[] = trim((string) JournalPost::query()
                    ->where('canonical_url', $this->canonical_url)
                    ->orderByDesc('id')
                    ->value('body'));
            }

            $candidates[] = trim((string) JournalPost::query()
                ->where('slug', $this->slug)
                ->orderByDesc('id')
                ->value('body'));
        }

        return $this->preferredPicTimeMarkup($candidates);
    }

    public function picTimeSourceMarkup(): ?string
    {
        return $this->rawPicTimeEmbedMarkup();
    }
}
