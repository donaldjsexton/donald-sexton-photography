<?php

namespace App\Http\Controllers;

use App\Models\JournalPost;
use App\Models\Page;
use App\Models\Venue;
use App\Models\WeddingStory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        $page = Page::published()
            ->with(['heroMedia', 'blocks.media'])
            ->where('slug', 'about')
            ->first();

        return view('pages.show', [
            'page' => $page ?: Page::make([
                'title' => 'About',
                'excerpt' => 'I photograph weddings with a calm, simple approach so you can stay in the day and still come away with images that feel true to you.',
                'body' => <<<'HTML'
<p>I am Donald Sexton, a wedding photographer based in Clearwater and working across Tampa, the Gulf Coast, and beyond.</p>
<p>My goal is simple. I want your photos to feel honest, calm, and full of life. I pay attention to people first, then to light, timing, and the small moments that give the day its shape.</p>
<p>Some couples want big portraits. Some want gentle direction and room to breathe. Most want both. The work is built to hold all of that without making the day feel staged or heavy.</p>
<p>If you want photographs that feel easy to live with for years, the next step is to reach out and share the date, the place, and what matters most to you.</p>
HTML,
            ]),
            'eyebrow' => 'About',
        ]);
    }

    public function privacy(): View
    {
        return view('legal.privacy');
    }

    public function terms(): View
    {
        return view('legal.terms');
    }

    public function location(string $slug): View
    {
        $page = Page::published()
            ->with(['heroMedia', 'blocks.media'])
            ->where('template', 'location')
            ->where('slug', $slug)
            ->firstOrFail();

        $cityTerms = $this->locationSearchTerms($page);

        return view('pages.show', [
            'page' => $page,
            'eyebrow' => 'Location',
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => $page->title, 'url' => ''],
            ],
            'relatedStories' => $this->storiesForLocation($cityTerms),
            'relatedVenues' => $this->venuesForLocation($cityTerms),
            'relatedPosts' => $this->postsForLocation($cityTerms),
        ]);
    }

    /**
     * Derive city/region tokens from the page title and slug so we can
     * find venues and stories tied to that location without an explicit
     * city column on the Page model.
     *
     * @return array<int, string>
     */
    private function locationSearchTerms(Page $page): array
    {
        $candidates = collect([
            $page->title,
            str_replace('-', ' ', $page->slug),
        ])
            ->filter(fn ($value) => filled($value))
            ->map(fn (string $value) => trim(preg_replace('/\b(wedding|weddings|photographer|photography)\b/i', '', $value)))
            ->map(fn (string $value) => trim($value))
            ->filter(fn (string $value) => mb_strlen($value) >= 3)
            ->unique()
            ->values()
            ->all();

        return $candidates;
    }

    /**
     * @param  array<int, string>  $terms
     */
    private function storiesForLocation(array $terms): Collection
    {
        if ($terms === []) {
            return WeddingStory::query()->whereRaw('1 = 0')->get();
        }

        return WeddingStory::published()
            ->with(['heroMedia', 'venue'])
            ->where(fn (Builder $query) => $this->applyLocationFilter($query, $terms, ['city', 'state', 'location_name']))
            ->latest('published_at')
            ->limit(6)
            ->get();
    }

    /**
     * @param  array<int, string>  $terms
     */
    private function venuesForLocation(array $terms): Collection
    {
        if ($terms === []) {
            return Venue::query()->whereRaw('1 = 0')->get();
        }

        return Venue::query()
            ->with('heroMedia')
            ->where(fn (Builder $query) => $this->applyLocationFilter($query, $terms, ['city', 'state', 'region']))
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->limit(8)
            ->get();
    }

    /**
     * @param  array<int, string>  $terms
     */
    private function postsForLocation(array $terms): Collection
    {
        if ($terms === []) {
            return JournalPost::query()->whereRaw('1 = 0')->get();
        }

        return JournalPost::published()
            ->with('heroMedia')
            ->whereHas('venues', fn (Builder $query) => $this->applyLocationFilter($query, $terms, ['city', 'state', 'region']))
            ->latest('published_at')
            ->limit(6)
            ->get();
    }

    /**
     * @param  array<int, string>  $terms
     * @param  array<int, string>  $columns
     */
    private function applyLocationFilter(Builder $query, array $terms, array $columns): Builder
    {
        return $query->where(function (Builder $outer) use ($terms, $columns): void {
            foreach ($terms as $term) {
                $like = '%'.strtolower($term).'%';

                foreach ($columns as $column) {
                    $outer->orWhereRaw('LOWER('.$column.') LIKE ?', [$like]);
                }
            }
        });
    }
}
