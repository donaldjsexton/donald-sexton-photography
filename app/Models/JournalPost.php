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
use Illuminate\Support\Str;

class JournalPost extends Model
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
        'post_type',
        'excerpt',
        'body',
        'source_markup',
        'hero_media_id',
        'gallery_id',
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

    /**
     * The native client gallery delivering this post's photos, if linked.
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
     * Imported duplicates keep the original slug plus an `-archived-{id}`
     * suffix and were left published. They should not be listed, sitemapped,
     * or offered to crawlers as their own URL.
     */
    public function scopeWithoutArchivedSlugDuplicates(Builder $query): Builder
    {
        return $query->where('slug', 'not like', '%-archived-%');
    }

    public function isArchivedSlugDuplicate(): bool
    {
        return preg_match('/-archived-\d+$/', (string) $this->slug) === 1;
    }

    /**
     * Order posts newest first, falling back to the creation date when a post
     * has no explicit publish date so recent entries are never buried under
     * older imported archives.
     */
    public function scopeRecentFirst(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->orderByRaw("COALESCE({$table}.published_at, {$table}.created_at) DESC")
            ->orderByDesc($table.'.id');
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

    /**
     * @return array{hero_copy: ?string, gallery_html: ?string, body_html: ?string}
     */
    public function presentationContent(): array
    {
        return $this->importedPresentationContent(
            trim((string) $this->sanitizedBody()),
            $this->excerpt ?: PicTimeContent::excerpt($this->picTimeSourceMarkup()),
            extractLead: false,
        );
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

    /**
     * The on-site URL llms.txt and other crawlers should cite. Pic-Time
     * canonicals are import keys, and a journal row that duplicates a wedding
     * story should point at that story.
     */
    public function listingUrl(): string
    {
        $story = $this->supersedingWeddingStory();

        if ($story) {
            return route('weddings.show', $story->slug);
        }

        return route('journal.show', $this->slug);
    }

    /**
     * Surname-only import titles ("Clipp", "D'Elia") become couple, venue,
     * and type when that detail is already on the post or its wedding story.
     */
    public function listingTitle(): string
    {
        $title = trim((string) $this->title);

        if (! $this->titleIsBareName($title)) {
            return $title;
        }

        $storyTitle = trim((string) ($this->supersedingWeddingStory()?->title ?? ''));

        if ($storyTitle !== '' && ! $this->titleIsBareName($storyTitle)) {
            return $storyTitle;
        }

        $seoTitle = $this->descriptiveSeoTitle();

        if ($seoTitle !== null) {
            return $seoTitle;
        }

        return $this->titleWithVenue($title);
    }

    /**
     * Wedding story that already owns this journal post, matched the same way
     * the journal page redirects.
     */
    public function supersedingWeddingStory(): ?WeddingStory
    {
        if ($this->supersedingResolved) {
            return $this->supersedingStory;
        }

        $this->supersedingResolved = true;

        $bySlug = WeddingStory::published()->where('slug', $this->slug)->first();

        if ($bySlug) {
            return $this->supersedingStory = $bySlug;
        }

        if (filled($this->canonical_url)) {
            $match = WeddingStory::published()
                ->where('canonical_url', $this->canonical_url)
                ->first();

            if ($match) {
                return $this->supersedingStory = $match;
            }
        }

        if (filled($this->original_wp_url)) {
            $match = WeddingStory::published()
                ->where(function (Builder $query): void {
                    $query->where('canonical_url', $this->original_wp_url)
                        ->orWhere('original_wp_url', $this->original_wp_url);
                })
                ->first();

            if ($match) {
                return $this->supersedingStory = $match;
            }
        }

        return $this->supersedingStory = null;
    }

    private bool $supersedingResolved = false;

    private ?WeddingStory $supersedingStory = null;

    private function titleIsBareName(string $title): bool
    {
        $title = trim($title);

        if ($title === '' || mb_strlen($title) > 40) {
            return false;
        }

        return preg_match("/^[\p{L}]+(?:['’-][\p{L}]+)?$/u", $title) === 1;
    }

    private function descriptiveSeoTitle(): ?string
    {
        $seoTitle = trim((string) $this->seo_title);

        if ($seoTitle === '' || $this->titleIsBareName($seoTitle) || mb_strlen($seoTitle) > 110) {
            return null;
        }

        if (preg_match('/\+|&| at |wedding|engagement|elopement/i', $seoTitle) !== 1) {
            return null;
        }

        return $seoTitle;
    }

    private function titleWithVenue(string $title): string
    {
        $venueName = trim((string) ($this->venues->first()?->name ?? ''));

        if ($venueName === '') {
            return $title;
        }

        if (filled($this->post_type)) {
            return $title.' — '.$this->post_type_label.' at '.$venueName;
        }

        return $title.' at '.$venueName;
    }
}
