<?php

namespace App\Http\Controllers;

use App\Models\HomepageSetting;
use App\Models\JournalPost;
use App\Models\Media;
use App\Models\Page;
use App\Models\Venue;
use App\Models\WeddingStory;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    private const CACHE_KEY = 'sitemap.xml.body';

    private const CACHE_TTL_SECONDS = 900;

    /**
     * Image entries per URL are capped well below Google's 1,000 limit
     * to keep the document small and cacheable.
     */
    private const IMAGES_PER_URL = 40;

    public function __invoke(): Response
    {
        $body = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn () => $this->renderXml());

        return response($body)
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=900');
    }

    private function renderXml(): string
    {
        $urls = $this->buildUrlEntries();

        return view('sitemap.xml', ['urls' => $urls])->render();
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: ?string, images: array<int, array{loc: string, title: ?string, caption: ?string}>}>
     */
    private function buildUrlEntries(): Collection
    {
        $homeSettings = HomepageSetting::query()->latest('updated_at')->first();
        $collectionsPage = Page::published()->where('slug', 'collections')->latest('updated_at')->first();
        $latestStory = WeddingStory::published()->latest('updated_at')->first();
        $latestJournalPost = JournalPost::published()->latest('updated_at')->first();
        $latestVenue = Venue::query()->latest('updated_at')->first();

        $rootEntries = collect([
            $this->entry(route('home'), $homeSettings?->updated_at),
            $this->entry(route('collections.index'), $collectionsPage?->updated_at ?? $collectionsPage?->published_at),
            $this->entry(route('weddings.index'), $latestStory?->updated_at),
            $this->entry(route('journal.index'), $latestJournalPost?->updated_at),
            $this->entry(route('venues.index'), $latestVenue?->updated_at),
            $this->entry(route('inquiry.create')),
        ]);

        $locationPages = Page::published()
            ->with('heroMedia')
            ->where('template', 'location')
            ->get()
            ->map(fn (Page $page): array => $this->entry(
                route('pages.location', $page->slug),
                $page->updated_at ?? $page->published_at,
                $this->imagesFor($page->heroMedia ? collect([$page->heroMedia]) : collect(), $page->title),
            ));

        $weddingStories = WeddingStory::published()
            ->with(['heroMedia', 'media'])
            ->get()
            ->map(fn (WeddingStory $story): array => $this->entry(
                route('weddings.show', $story->slug),
                $story->updated_at ?? $story->published_at,
                $this->imagesFor($this->collectMedia($story->heroMedia, $story->media), $story->title),
            ));

        $journalPosts = JournalPost::published()
            ->with(['heroMedia', 'media'])
            ->get()
            ->map(fn (JournalPost $post): array => $this->entry(
                route('journal.show', $post->slug),
                $post->updated_at ?? $post->published_at,
                $this->imagesFor($this->collectMedia($post->heroMedia, $post->media), $post->title),
            ));

        $venues = Venue::query()
            ->with('heroMedia')
            ->get()
            ->map(fn (Venue $venue): array => $this->entry(
                route('venues.show', $venue->slug),
                $venue->updated_at,
                $this->imagesFor($venue->heroMedia ? collect([$venue->heroMedia]) : collect(), $venue->name),
            ));

        return $rootEntries
            ->merge($locationPages)
            ->merge($weddingStories)
            ->merge($journalPosts)
            ->merge($venues)
            ->unique('loc')
            ->values();
    }

    /**
     * @param  Collection<int, Media>|null  $gallery
     * @return Collection<int, Media>
     */
    private function collectMedia(?Media $hero, ?Collection $gallery): Collection
    {
        $items = collect();

        if ($hero) {
            $items->push($hero);
        }

        if ($gallery) {
            $items = $items->concat($gallery);
        }

        return $items->unique('id')->values();
    }

    /**
     * @param  Collection<int, Media>  $media
     * @return array<int, array{loc: string, title: ?string, caption: ?string}>
     */
    private function imagesFor(Collection $media, ?string $titleFallback = null): array
    {
        return $media
            ->take(self::IMAGES_PER_URL)
            ->map(function (Media $item) use ($titleFallback): ?array {
                $relative = $item->publicUrl();

                if ($relative === null) {
                    return null;
                }

                return [
                    'loc' => $this->absoluteUrl($relative),
                    'title' => $item->alt_text ?: $item->caption ?: $titleFallback ?: null,
                    'caption' => $item->caption ?: null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function absoluteUrl(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return url($path);
    }

    /**
     * @param  array<int, array{loc: string, title: ?string, caption: ?string}>  $images
     * @return array{loc: string, lastmod: ?string, images: array<int, array{loc: string, title: ?string, caption: ?string}>}
     */
    private function entry(string $loc, ?Carbon $lastmod = null, array $images = []): array
    {
        return [
            'loc' => $loc,
            'lastmod' => $lastmod?->toAtomString(),
            'images' => $images,
        ];
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
