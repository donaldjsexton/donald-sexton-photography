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

    /**
     * Records are streamed in batches so the whole content catalogue is
     * never held in memory at once. This keeps the render comfortably
     * under the PHP-FPM memory_limit even as the catalogue grows.
     */
    private const CHUNK_SIZE = 50;

    /**
     * The only media columns the sitemap needs. Loading the full model —
     * and especially the parent records' longText body columns — was
     * exhausting the request memory_limit and returning a 500.
     *
     * @var array<int, string>
     */
    private const MEDIA_COLUMNS = ['media.id', 'media.disk', 'media.path', 'media.alt_text', 'media.caption'];

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

        $entries = collect([
            $this->entry(route('home'), $homeSettings?->updated_at),
            $this->entry(route('collections.index'), $collectionsPage?->updated_at ?? $collectionsPage?->published_at),
            $this->entry(route('weddings.index'), $latestStory?->updated_at),
            $this->entry(route('journal.index'), $latestJournalPost?->updated_at),
            $this->entry(route('venues.index'), $latestVenue?->updated_at),
            $this->entry(route('inquiry.create')),
        ]);

        $heroMedia = fn ($query) => $query->select(self::MEDIA_COLUMNS);
        $gallery = fn ($query) => $query->select(self::MEDIA_COLUMNS);

        Page::published()
            ->select(['id', 'slug', 'title', 'template', 'hero_media_id', 'updated_at', 'published_at'])
            ->with(['heroMedia' => $heroMedia])
            ->where('template', 'location')
            ->chunkById(self::CHUNK_SIZE, function ($pages) use ($entries): void {
                foreach ($pages as $page) {
                    $entries->push($this->entry(
                        route('pages.location', $page->slug),
                        $page->updated_at ?? $page->published_at,
                        $this->imagesFor($page->heroMedia ? collect([$page->heroMedia]) : collect(), $page->title),
                    ));
                }
            });

        WeddingStory::published()
            ->select(['id', 'slug', 'title', 'hero_media_id', 'updated_at', 'published_at'])
            ->with(['heroMedia' => $heroMedia, 'media' => $gallery])
            ->chunkById(self::CHUNK_SIZE, function ($stories) use ($entries): void {
                foreach ($stories as $story) {
                    $entries->push($this->entry(
                        route('weddings.show', $story->slug),
                        $story->updated_at ?? $story->published_at,
                        $this->imagesFor($this->collectMedia($story->heroMedia, $story->media), $story->title),
                    ));
                }
            });

        JournalPost::published()
            ->select(['id', 'slug', 'title', 'hero_media_id', 'updated_at', 'published_at'])
            ->with(['heroMedia' => $heroMedia, 'media' => $gallery])
            ->chunkById(self::CHUNK_SIZE, function ($posts) use ($entries): void {
                foreach ($posts as $post) {
                    $entries->push($this->entry(
                        route('journal.show', $post->slug),
                        $post->updated_at ?? $post->published_at,
                        $this->imagesFor($this->collectMedia($post->heroMedia, $post->media), $post->title),
                    ));
                }
            });

        Venue::query()
            ->select(['id', 'slug', 'name', 'hero_media_id', 'updated_at'])
            ->with(['heroMedia' => $heroMedia])
            ->chunkById(self::CHUNK_SIZE, function ($venues) use ($entries): void {
                foreach ($venues as $venue) {
                    $entries->push($this->entry(
                        route('venues.show', $venue->slug),
                        $venue->updated_at,
                        $this->imagesFor($venue->heroMedia ? collect([$venue->heroMedia]) : collect(), $venue->name),
                    ));
                }
            });

        return $entries
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
