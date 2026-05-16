<?php

namespace App\Models;

use App\Models\Concerns\InteractsWithPicTime;
use App\Support\PicTimeContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WeddingStory extends Model
{
    use HasFactory;
    use InteractsWithPicTime;

    protected $fillable = [
        'title',
        'slug',
        'status',
        'story_type',
        'headline',
        'excerpt',
        'body',
        'source_markup',
        'hero_media_id',
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

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function storyBlocks(): HasMany
    {
        return $this->hasMany(StoryBlock::class)->orderBy('sort_order');
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
     * Resolve up to $limit published wedding stories that are conceptually
     * similar to $story. Match priority is venue first (couples shopping
     * a single venue care most about other weddings there), then tag
     * overlap, with recent published stories padding any remaining slots
     * so every detail page has outgoing internal links.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int,self>
     */
    public static function similarTo(self $story, int $limit = 3): \Illuminate\Database\Eloquent\Collection
    {
        if ($limit <= 0) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        $tagIds = $story->relationLoaded('tags')
            ? $story->tags->pluck('id')->all()
            : $story->tags()->pluck('tags.id')->all();

        $base = fn () => self::published()
            ->where('id', '!=', $story->id)
            ->with(['heroMedia', 'venue']);

        $results = new \Illuminate\Database\Eloquent\Collection;

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
     * @return \Illuminate\Database\Eloquent\Collection<int,JournalPost>
     */
    public static function relatedPostsTo(self $story, int $limit = 3): \Illuminate\Database\Eloquent\Collection
    {
        if ($limit <= 0) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        $tagIds = $story->relationLoaded('tags')
            ? $story->tags->pluck('id')->all()
            : $story->tags()->pluck('tags.id')->all();

        $base = fn () => JournalPost::published()
            ->with(['heroMedia', 'categories']);

        $results = new \Illuminate\Database\Eloquent\Collection;

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

    public function presentationContent(): array
    {
        $body = trim((string) $this->sanitizedBody());
        $picTimeExcerpt = PicTimeContent::excerpt($this->picTimeSourceMarkup());

        $default = [
            'hero_copy' => $this->excerpt ?: $picTimeExcerpt,
            'gallery_html' => null,
            'body_html' => $body !== '' ? $body : null,
        ];

        if (! $this->original_wp_post_id || $body === '') {
            return $default;
        }

        $segments = $this->parseImportedPresentationContent($body);

        if ($segments === null) {
            return $default;
        }

        $bodyHtml = trim($segments['body_html']);

        return [
            'hero_copy' => $segments['lead_text'] ?: $default['hero_copy'],
            'gallery_html' => $segments['gallery_html'] ?: null,
            'body_html' => $bodyHtml !== '' ? $bodyHtml : null,
        ];
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

    private function parseImportedPresentationContent(string $body): ?array
    {
        $previousState = libxml_use_internal_errors(true);

        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $loaded = $dom->loadHTML(
                '<?xml encoding="utf-8" ?><div id="imported-story-root">'.$body.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );

            if (! $loaded) {
                return null;
            }

            $root = $dom->getElementById('imported-story-root') ?: $dom->getElementsByTagName('div')->item(0);

            if (! $root instanceof \DOMElement) {
                return null;
            }

            foreach ($root->getElementsByTagName('img') as $image) {
                if (! $image->hasAttribute('loading')) {
                    $image->setAttribute('loading', 'lazy');
                }

                if (! $image->hasAttribute('decoding')) {
                    $image->setAttribute('decoding', 'async');
                }
            }

            $leadText = null;
            $galleryHtml = null;
            $bodyParts = [];

            foreach ($root->childNodes as $child) {
                $childHtml = trim($dom->saveHTML($child) ?: '');

                if ($childHtml === '' || $this->isEmptyTextNode($child)) {
                    continue;
                }

                if ($leadText === null && $this->isLeadParagraph($child)) {
                    $leadText = $this->normalizeImportedText($childHtml);

                    continue;
                }

                if ($galleryHtml === null && $this->isImportedGallery($child)) {
                    $galleryHtml = $childHtml;

                    continue;
                }

                $bodyParts[] = $childHtml;
            }

            return [
                'lead_text' => $leadText,
                'gallery_html' => trim((string) $galleryHtml),
                'body_html' => trim(implode("\n\n", $bodyParts)),
            ];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousState);
        }
    }

    private function isLeadParagraph(\DOMNode $node): bool
    {
        return $node instanceof \DOMElement
            && strtolower($node->tagName) === 'p'
            && $this->normalizeImportedText($node->textContent) !== '';
    }

    private function isImportedGallery(\DOMNode $node): bool
    {
        return $node instanceof \DOMElement
            && strtolower($node->tagName) === 'figure'
            && (
                str_contains(' '.$node->getAttribute('class').' ', ' imported-gallery ')
                || str_contains(' '.$node->getAttribute('class').' ', ' wp-import-gallery ')
            );
    }

    private function isEmptyTextNode(\DOMNode $node): bool
    {
        return $node instanceof \DOMText
            && trim($node->textContent) === '';
    }

    private function normalizeImportedText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/<\s*br\s*\/?>/i', ' ', $value) ?? $value;
        $value = strip_tags($value);

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
