<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\HomepageSetting;
use App\Models\JournalPost;
use App\Models\Testimonial;
use App\Models\WeddingStory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\View\View;

class HomeController extends Controller
{
    private const FEATURED_LIMIT = 3;
    private const HOME_STORY_POOL_LIMIT = 5;

    public function __invoke(): View
    {
        $settings = HomepageSetting::query()->with('heroMedia')->first();

        return view('home.index', [
            'settings' => $settings,
            'featuredStories' => $this->resolveStories($settings?->featured_story_ids_json ?? []),
            'homeStories' => $this->resolveStories($settings?->featured_story_ids_json ?? [], self::HOME_STORY_POOL_LIMIT),
            'collections' => Collection::published()->orderBy('display_order')->get(),
            'featuredTestimonials' => $this->resolveTestimonials($settings?->featured_testimonial_ids_json ?? []),
            'featuredJournalPosts' => $this->resolveJournalPosts($settings?->featured_journal_post_ids_json ?? []),
        ]);
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
}
