<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\JournalPost;
use App\Models\Venue;
use App\Models\WeddingStory;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class LlmsTxtController extends Controller
{
    private const RECENT_LIMIT = 10;

    public function __invoke(): Response
    {
        $lines = [
            '# Donald Sexton Photography',
            '',
            '> Calm, documentary wedding photography for Clearwater, Tampa, St. Petersburg, Sarasota, and the rest of Florida.',
            '',
            'Donald Sexton photographs weddings, engagements, and elopements with a quiet, story-first approach. Coverage is built on hours, with collections that scale from six-hour days to full multi-day events. Inquiries are answered personally, usually within a couple of business days.',
            '',
            '## Key pages',
            '',
            '- ['.self::siteName().']('.route('home').'): Homepage with featured wedding stories, journal entries, and a brief introduction.',
            '- [Weddings]('.route('weddings.index').'): Archive of full wedding stories with location, date, and gallery for each.',
            '- [Collections]('.route('collections.index').'): Coverage tiers, hours, starting prices, and add-ons.',
            '- [Journal]('.route('journal.index').'): Recent posts covering real weddings, engagements, planning advice, and venue notes.',
            '- [Venues]('.route('venues.index').'): Florida wedding venues with notes from photographing them.',
            '- [Check Availability]('.route('inquiry.create').'): Inquiry form for date and venue. Includes FAQ on travel, pricing, and style.',
        ];

        $stories = WeddingStory::published()
            ->with('venue')
            ->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get();

        if ($stories->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '## Recent wedding stories';
            $lines[] = '';

            foreach ($stories as $story) {
                $lines[] = self::storyEntry($story);
            }
        }

        $posts = JournalPost::published()
            ->whereNotIn('slug', WeddingStory::published()->select('slug'))
            ->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get();

        if ($posts->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '## Recent journal posts';
            $lines[] = '';

            foreach ($posts as $post) {
                $lines[] = self::postEntry($post);
            }
        }

        $collections = Collection::published()->orderBy('display_order')->get();

        if ($collections->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '## Coverage collections';
            $lines[] = '';

            foreach ($collections as $collection) {
                $lines[] = self::collectionEntry($collection);
            }
        }

        $venues = Venue::query()
            ->orderBy('name')
            ->limit(self::RECENT_LIMIT)
            ->get();

        if ($venues->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '## Featured venues';
            $lines[] = '';

            foreach ($venues as $venue) {
                $lines[] = self::venueEntry($venue);
            }
        }

        $lines[] = '';
        $lines[] = '## Optional';
        $lines[] = '';
        $lines[] = '- [Sitemap]('.route('sitemap').'): XML sitemap of every public URL.';
        $lines[] = '- [Journal feed]('.route('journal.feed').'): Atom feed of journal posts.';
        $lines[] = '- [Privacy policy]('.route('legal.privacy').')';
        $lines[] = '- [Terms of service]('.route('legal.terms').')';
        $lines[] = '';

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    private static function storyEntry(WeddingStory $story): string
    {
        $url = $story->canonical_url ?: route('weddings.show', $story->slug);
        $summary = self::oneLineSummary(
            $story->seo_description ?: $story->summaryText(40)
        );
        $location = $story->venue?->name ?: $story->location_name;
        $context = collect([$location, $story->event_date?->format('F Y')])
            ->filter()
            ->implode(' · ');

        $line = '- ['.$story->title.']('.$url.')';

        if ($context !== '') {
            $line .= ' — '.$context;
        }

        if ($summary !== '') {
            $line .= '. '.$summary;
        }

        return $line;
    }

    private static function postEntry(JournalPost $post): string
    {
        $url = $post->canonical_url ?: route('journal.show', $post->slug);
        $summary = self::oneLineSummary(
            $post->seo_description ?: $post->excerpt ?: $post->summaryText(40)
        );

        $line = '- ['.$post->title.']('.$url.')';

        if ($summary !== '') {
            $line .= ': '.$summary;
        }

        return $line;
    }

    private static function collectionEntry(Collection $collection): string
    {
        $url = route('collections.index').'#'.$collection->slug;
        $summary = self::oneLineSummary($collection->summary ?: $collection->headline);

        $bits = [];

        if ($collection->coverage_hours_min || $collection->coverage_hours_max) {
            $min = $collection->coverage_hours_min;
            $max = $collection->coverage_hours_max;

            if ($min && $max && $min !== $max) {
                $bits[] = $min.'-'.$max.' hours';
            } elseif ($min && ! $max) {
                $bits[] = $min.'+ hours';
            } else {
                $bits[] = ($min ?? $max).' hours';
            }
        }

        if ($collection->starting_price !== null) {
            $bits[] = ($collection->price_label ?: 'Starting at').' $'.number_format(
                (float) $collection->starting_price,
                0
            );
        }

        $line = '- ['.$collection->name.']('.$url.')';

        if ($bits !== []) {
            $line .= ' — '.implode(', ', $bits);
        }

        if ($summary !== '') {
            $line .= '. '.$summary;
        }

        return $line;
    }

    private static function venueEntry(Venue $venue): string
    {
        $summary = self::oneLineSummary($venue->summary ?: $venue->headline);
        $location = collect([$venue->city, $venue->state])->filter()->implode(', ');

        $line = '- ['.$venue->name.']('.route('venues.show', $venue->slug).')';

        if ($location !== '') {
            $line .= ' — '.$location;
        }

        if ($summary !== '') {
            $line .= '. '.$summary;
        }

        return $line;
    }

    private static function oneLineSummary(?string $value, int $words = 28): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/u', ' ', strip_tags($value)) ?? $value;

        return Str::words($value, $words);
    }

    private static function siteName(): string
    {
        return (string) config('app.name', 'Donald Sexton Photography');
    }
}
