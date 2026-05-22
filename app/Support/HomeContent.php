<?php

namespace App\Support;

use App\Models\Collection;
use App\Models\HomepageSetting;
use App\Models\JournalPost;
use App\Models\Testimonial;
use App\Models\WeddingStory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Request-scoped resolver for the homepage's curated content. Holds the
 * featured-or-fallback logic that used to live in HomeController so both the
 * classic layout and the block components draw from one memoised source.
 */
class HomeContent
{
    private const FEATURED_LIMIT = 3;

    private const HOME_STORY_POOL_LIMIT = 5;

    /** @var array<string, mixed> */
    private array $memo = [];

    public function __construct(private ?HomepageSetting $settings) {}

    public function settings(): ?HomepageSetting
    {
        return $this->settings;
    }

    public function featuredStories(): EloquentCollection
    {
        return $this->remember('featuredStories', fn (): EloquentCollection => $this->resolveStories($this->settings?->featured_story_ids_json ?? []));
    }

    public function homeStories(): EloquentCollection
    {
        return $this->remember('homeStories', fn (): EloquentCollection => $this->resolveStories($this->settings?->featured_story_ids_json ?? [], self::HOME_STORY_POOL_LIMIT));
    }

    public function collections(): EloquentCollection
    {
        return $this->remember('collections', fn (): EloquentCollection => Collection::published()->orderBy('display_order')->get());
    }

    public function featuredTestimonials(): EloquentCollection
    {
        return $this->remember('featuredTestimonials', fn (): EloquentCollection => $this->resolveTestimonials($this->settings?->featured_testimonial_ids_json ?? []));
    }

    public function featuredJournalPosts(): EloquentCollection
    {
        return $this->remember('featuredJournalPosts', fn (): EloquentCollection => $this->resolveJournalPosts($this->settings?->featured_journal_post_ids_json ?? []));
    }

    public function visualStoryPool(): EloquentCollection
    {
        return $this->remember('visualStoryPool', fn (): EloquentCollection => $this->homeStories()
            ->concat($this->featuredStories())
            ->unique('id')
            ->filter(fn ($story) => filled($story?->featuredImageUrl()))
            ->values());
    }

    public function leadStory(): ?WeddingStory
    {
        return $this->visualStoryPool()->get(0) ?? $this->homeStories()->get(0) ?? $this->featuredStories()->get(0);
    }

    public function secondStory(): ?WeddingStory
    {
        return $this->visualStoryPool()->get(1) ?? $this->homeStories()->get(1) ?? $this->featuredStories()->get(1) ?? $this->leadStory();
    }

    public function thirdStory(): ?WeddingStory
    {
        return $this->visualStoryPool()->get(2) ?? $this->homeStories()->get(2) ?? $this->featuredStories()->get(2) ?? $this->secondStory();
    }

    public function discoverLeftStory(): ?WeddingStory
    {
        return $this->visualStoryPool()->get(3) ?? $this->homeStories()->get(3) ?? $this->visualStoryPool()->get(1);
    }

    public function discoverRightStory(): ?WeddingStory
    {
        return $this->visualStoryPool()->get(4) ?? $this->homeStories()->get(4) ?? $this->visualStoryPool()->get(2);
    }

    public function portfolioStories(): EloquentCollection
    {
        $pool = $this->visualStoryPool();

        return $pool->take(3)->count() === 3
            ? $pool->take(3)->values()
            : $this->featuredStories()->take(3)->values();
    }

    public function journalPosts(): EloquentCollection
    {
        return $this->featuredJournalPosts()->take(3)->values();
    }

    public function quote(): ?Testimonial
    {
        return $this->featuredTestimonials()->first();
    }

    public function leadImage(): ?string
    {
        return $this->leadStory()?->featuredImageUrl();
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    private function resolveStories(array $ids, int $limit = self::FEATURED_LIMIT): EloquentCollection
    {
        $selectedIds = $this->sanitizeIds($ids);

        $stories = WeddingStory::published()
            ->with(['heroMedia', 'venue'])
            ->when($selectedIds !== [], fn ($query) => $query->whereIn('id', $selectedIds))
            ->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        return $this->fillRemainingItems(
            $stories,
            WeddingStory::published()
                ->with(['heroMedia', 'venue'])
                ->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('published_at')
                ->orderByDesc('id'),
            $limit,
        );
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    private function resolveTestimonials(array $ids): EloquentCollection
    {
        $selectedIds = $this->sanitizeIds($ids);

        $testimonials = Testimonial::query()
            ->when($selectedIds !== [], fn ($query) => $query->whereIn('id', $selectedIds))
            ->when($selectedIds === [], fn ($query) => $query->where('is_featured', true)->orderBy('sort_order'))
            ->get();

        $testimonials = $this->sortBySelectedIds($testimonials, $selectedIds);

        return $this->fillRemainingItems(
            $testimonials,
            Testimonial::query()
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->latest('created_at'),
        );
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    private function resolveJournalPosts(array $ids): EloquentCollection
    {
        $selectedIds = $this->sanitizeIds($ids);

        $posts = JournalPost::published()
            ->with('heroMedia')
            ->when($selectedIds !== [], fn ($query) => $query->whereIn('id', $selectedIds))
            ->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        return $this->fillRemainingItems(
            $posts,
            JournalPost::published()
                ->with('heroMedia')
                ->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('published_at')
                ->orderByDesc('id'),
        );
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    private function sortBySelectedIds(EloquentCollection $items, array $ids): EloquentCollection
    {
        if ($ids === []) {
            return $items->take(self::FEATURED_LIMIT)->values();
        }

        $positions = array_flip(array_map('intval', $ids));

        return $items
            ->sortBy(fn ($item) => $positions[$item->id] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, int>
     */
    private function sanitizeIds(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function fillRemainingItems(EloquentCollection $items, $fallbackQuery, int $limit = self::FEATURED_LIMIT): EloquentCollection
    {
        $items = $items->take($limit)->values();

        if ($items->count() >= $limit) {
            return $items;
        }

        $fallbackItems = $fallbackQuery
            ->whereNotIn('id', $items->modelKeys())
            ->limit($limit - $items->count())
            ->get();

        return $items
            ->concat($fallbackItems)
            ->take($limit)
            ->values();
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $resolver
     * @return TValue
     */
    private function remember(string $key, callable $resolver)
    {
        return $this->memo[$key] ??= $resolver();
    }
}
