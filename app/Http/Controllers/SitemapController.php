<?php

namespace App\Http\Controllers;

use App\Models\HomepageSetting;
use App\Models\JournalPost;
use App\Models\Page;
use App\Models\Venue;
use App\Models\WeddingStory;
use Illuminate\Support\Carbon;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $homeSettings = HomepageSetting::query()->latest('updated_at')->first();
        $collectionsPage = Page::published()->where('slug', 'collections')->latest('updated_at')->first();
        $latestStory = WeddingStory::published()->latest('updated_at')->first();
        $latestJournalPost = JournalPost::published()->latest('updated_at')->first();
        $latestVenue = Venue::query()->latest('updated_at')->first();

        $urls = collect([
            $this->entry(route('home'), $homeSettings?->updated_at),
            $this->entry(route('collections.index'), $collectionsPage?->updated_at ?? $collectionsPage?->published_at),
            $this->entry(route('weddings.index'), $latestStory?->updated_at),
            $this->entry(route('journal.index'), $latestJournalPost?->updated_at),
            $this->entry(route('venues.index'), $latestVenue?->updated_at),
            $this->entry(route('inquiry.create')),
        ])->merge(
            Page::published()
                ->where('template', 'location')
                ->get(['slug', 'updated_at', 'published_at'])
                ->map(fn (Page $page): array => $this->entry(
                    route('pages.location', $page->slug),
                    $page->updated_at ?? $page->published_at,
                ))
        )->merge(
            WeddingStory::published()
                ->get(['slug', 'updated_at', 'published_at'])
                ->map(fn (WeddingStory $story): array => $this->entry(
                    route('weddings.show', $story->slug),
                    $story->updated_at ?? $story->published_at,
                ))
        )->merge(
            JournalPost::published()
                ->get(['slug', 'updated_at', 'published_at'])
                ->map(fn (JournalPost $post): array => $this->entry(
                    route('journal.show', $post->slug),
                    $post->updated_at ?? $post->published_at,
                ))
        )->merge(
            Venue::query()
                ->get(['slug', 'updated_at'])
                ->map(fn (Venue $venue): array => $this->entry(
                    route('venues.show', $venue->slug),
                    $venue->updated_at,
                ))
        )->unique('loc')->values();

        return response()
            ->view('sitemap.xml', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    private function entry(string $loc, ?Carbon $lastmod = null): array
    {
        return [
            'loc' => $loc,
            'lastmod' => $lastmod?->toAtomString(),
        ];
    }
}
