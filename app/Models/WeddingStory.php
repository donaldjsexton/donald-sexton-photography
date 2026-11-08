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

        return $this->readingBodyHtml(
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

        if ($this->bodyIsPlainText($body)) {
            $body = $this->plainParagraphsHtml($body);
        }

        return $this->sanitizeImportedHtml($body);
    }

    private function bodyIsPlainText(string $body): bool
    {
        return preg_match('/<\/?[a-z][\w:-]*\b/i', $body) !== 1;
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

    /**
     * Safe HTML for the public reading section.
     *
     * Saved stories are rich text (paragraphs, emphasis, breaks, sometimes one
     * wrapper element) or plain paragraphs. An opening block that only repeats
     * the hero excerpt stays in the hero. Later paragraphs keep their
     * formatting. A story with no body leaves the excerpt as the hero fallback.
     */
    private function readingBodyHtml(?string $html, ?string $heroCopy): ?string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return null;
        }

        if (preg_match('/<[a-z][\w:-]*\b/i', $html) !== 1) {
            $html = $this->plainParagraphsHtml($html);
        }

        $html = trim($this->renderReadingHtml($html, $heroCopy));

        return $html !== '' ? $html : null;
    }

    private function plainParagraphsHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $paragraphs = preg_split("/\n{2,}/", trim($text)) ?: [];

        return collect($paragraphs)
            ->map(function (string $paragraph): ?string {
                $paragraph = trim($paragraph);

                if ($paragraph === '') {
                    return null;
                }

                return '<p>'.nl2br(e($paragraph), false).'</p>';
            })
            ->filter()
            ->implode("\n");
    }

    private function renderReadingHtml(string $html, ?string $heroCopy): string
    {
        $previousState = libxml_use_internal_errors(true);

        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $loaded = $dom->loadHTML(
                '<?xml encoding="utf-8" ?><div id="story-reading-root">'.$html.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );

            if (! $loaded) {
                return $html;
            }

            $root = $dom->getElementById('story-reading-root');

            if (! $root instanceof \DOMElement) {
                return $html;
            }

            $this->hoistReadingWrappers($root);
            $this->stripUnsafeReadingAttributes($root);
            $this->omitDuplicateOpeningBlock($root, $heroCopy);

            return trim(collect(iterator_to_array($root->childNodes))
                ->map(fn (\DOMNode $node): string => $dom->saveHTML($node) ?: '')
                ->implode(''));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousState);
        }
    }

    private function hoistReadingWrappers(\DOMElement $root): void
    {
        for ($guard = 0; $guard < 8; $guard++) {
            $nodes = $this->readingChildNodes($root);

            if (count($nodes) !== 1 || ! $nodes[0] instanceof \DOMElement) {
                return;
            }

            $wrapper = $nodes[0];
            $tag = strtolower($wrapper->tagName);

            if (! in_array($tag, ['div', 'section', 'article'], true) || $this->wrapperLooksLikeGallery($wrapper)) {
                return;
            }

            while ($wrapper->firstChild !== null) {
                $root->insertBefore($wrapper->firstChild, $wrapper);
            }

            $root->removeChild($wrapper);
        }
    }

    private function wrapperLooksLikeGallery(\DOMElement $wrapper): bool
    {
        $class = ' '.strtolower($wrapper->getAttribute('class')).' ';

        return str_contains($class, ' imported-gallery ')
            || str_contains($class, ' wp-import-gallery ');
    }

    private function stripUnsafeReadingAttributes(\DOMElement $root): void
    {
        foreach (iterator_to_array($root->getElementsByTagName('*')) as $element) {
            if (! $element instanceof \DOMElement) {
                continue;
            }

            $remove = [];

            foreach (iterator_to_array($element->attributes) as $attribute) {
                if (! $attribute instanceof \DOMAttr) {
                    continue;
                }

                $name = strtolower($attribute->name);
                $value = trim($attribute->value);

                if (str_starts_with($name, 'on')) {
                    $remove[] = $attribute->name;

                    continue;
                }

                if (in_array($name, ['href', 'src'], true) && preg_match('/^\s*javascript:/i', $value) === 1) {
                    $remove[] = $attribute->name;
                }
            }

            foreach ($remove as $name) {
                $element->removeAttribute($name);
            }
        }
    }

    private function omitDuplicateOpeningBlock(\DOMElement $root, ?string $heroCopy): void
    {
        $lead = $this->normalizeReadingText((string) $heroCopy);

        if ($lead === '') {
            return;
        }

        $nodes = $this->readingChildNodes($root);
        $first = $nodes[0] ?? null;

        if (! $first instanceof \DOMElement || strtolower($first->tagName) === 'figure') {
            return;
        }

        if ($this->normalizeReadingText($first->textContent) !== $lead) {
            return;
        }

        $root->removeChild($first);
    }

    /**
     * @return array<int, \DOMNode>
     */
    private function readingChildNodes(\DOMElement $root): array
    {
        $nodes = [];

        foreach ($root->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->textContent) === '') {
                continue;
            }

            $nodes[] = $child;
        }

        return $nodes;
    }

    private function normalizeReadingText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::of($value)->squish()->lower()->toString();
    }
}
