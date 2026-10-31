<?php

namespace App\Support;

use App\Models\Collection;
use App\Models\JournalPost;
use App\Models\Venue;
use App\Models\WeddingStory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Plain-text landing copy for venue searches the portfolio already covers.
 * Used when a venue row has no body of its own, and for the Hilton Clearwater
 * Beach page when no venue row exists yet. Every sentence is drawn from
 * published site copy or a real wedding/journal record.
 */
class VenueLandingCopy
{
    public const HONEYMOON_SLUG = 'romantic-honeymoon-island-weddings';

    public const HILTON_SLUG = 'hilton-clearwater-beach-resort-and-spa';

    public const KRYSINSKY_SLUG = 'krysinsky-wedding';

    public const LAUREN_SLUG = 'lauren-jonathans-engagement';

    /**
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        return [self::HONEYMOON_SLUG, self::HILTON_SLUG];
    }

    public static function isEditorial(string $slug): bool
    {
        return in_array($slug, self::slugs(), true);
    }

    public static function make(string $slug): ?Venue
    {
        $attributes = match ($slug) {
            self::HONEYMOON_SLUG => [
                'name' => 'Romantic Honeymoon Island Weddings',
                'slug' => self::HONEYMOON_SLUG,
                'city' => 'Dunedin',
                'state' => 'FL',
                'region' => 'Tampa Bay',
            ],
            self::HILTON_SLUG => [
                'name' => 'Hilton Clearwater Beach Resort & Spa',
                'slug' => self::HILTON_SLUG,
                'city' => 'Clearwater Beach',
                'state' => 'FL',
                'region' => 'Tampa Bay',
            ],
            default => null,
        };

        if ($attributes === null) {
            return null;
        }

        return new Venue($attributes);
    }

    public static function applyFallbacks(Venue $venue): void
    {
        if (! self::isEditorial($venue->slug)) {
            return;
        }

        if (! filled($venue->summary)) {
            $venue->summary = self::summary($venue);
        }

        if (! filled($venue->body)) {
            $venue->body = self::body($venue);
        }

        if ($venue->structuredFaqs() === []) {
            $faqs = self::faqs($venue);

            if ($faqs !== []) {
                $venue->faqs = $faqs;
            }
        }

        if (! filled($venue->seo_title)) {
            $venue->seo_title = self::seoTitle($venue);
        }

        if (! filled($venue->seo_description)) {
            $venue->seo_description = self::seoDescription($venue);
        }
    }

    public static function summary(Venue $venue): ?string
    {
        $price = self::startingPriceLabel();

        return match ($venue->slug) {
            self::HONEYMOON_SLUG => 'Elopements and small weddings on Honeymoon Island in Dunedin, photographed from Clearwater. Collections start at '.$price.'.',
            self::HILTON_SLUG => self::krysinskyStory()
                ? 'The Krysinsky wedding at Hilton Clearwater Beach Resort & Spa, on Clearwater Beach. Collections start at '.$price.'.'
                : 'Wedding photography at Hilton Clearwater Beach Resort & Spa on Clearwater Beach. Collections start at '.$price.'.',
            default => null,
        };
    }

    public static function seoTitle(Venue $venue): ?string
    {
        return match ($venue->slug) {
            self::HONEYMOON_SLUG => 'Honeymoon Island Elopement Photographer',
            self::HILTON_SLUG => 'Hilton Clearwater Beach Wedding Photographer',
            default => null,
        };
    }

    public static function seoDescription(Venue $venue): ?string
    {
        return self::summary($venue);
    }

    public static function body(Venue $venue): ?string
    {
        $price = e(self::startingPriceLabel());
        $inquiry = e(route('inquiry.create'));
        $collections = e(route('collections.index'));

        if ($venue->slug === self::HONEYMOON_SLUG) {
            $lauren = self::laurenPost();
            $laurenSentence = '';

            if ($lauren) {
                $laurenSentence = '<p>One session already in the journal is <a href="'.e(route('journal.show', $lauren->slug)).'">'.e($lauren->title).'</a>, photographed on Honeymoon Island.</p>';
            }

            return <<<HTML
<p>I photograph elopements and small weddings on Honeymoon Island. The island is in Dunedin, a short drive from Clearwater and Clearwater Beach, and it sits inside the Tampa Bay coast where most of my work already happens. Shorter coverage collections are built for days like this.</p>
<p>Wedding photography collections start at {$price}. Coverage is built on hours — keep the shorter option for an elopement, then add what the day actually needs. The current options are on the <a href="{$collections}">collections</a> page.</p>
{$laurenSentence}
<p>If Honeymoon Island is the plan, send the date through the <a href="{$inquiry}">inquiry form</a>. I reply within 24 hours.</p>
HTML;
        }

        if ($venue->slug === self::HILTON_SLUG) {
            $story = self::krysinskyStory();
            $storySentence = '<p>Hilton Clearwater Beach Resort &amp; Spa is on Clearwater Beach, in the Tampa Bay area I photograph from my base in Clearwater.</p>';

            if ($story) {
                $storySentence .= '<p>The <a href="'.e(route('weddings.show', $story->slug)).'">'.e($story->title).'</a> was photographed at Hilton Clearwater Beach Resort &amp; Spa. The gallery from that day is on the wedding story.</p>';
            }

            $journal = self::krysinskyJournalPost();
            $journalSentence = '';

            if ($journal && ($story === null || $journal->slug !== $story->slug)) {
                $journalSentence = '<p>There is also <a href="'.e(route('journal.show', $journal->slug)).'">a journal note</a> for this wedding.</p>';
            }

            return <<<HTML
{$storySentence}
{$journalSentence}
<p>Wedding photography collections start at {$price}. Elopements and full wedding days are both welcome. Send the date through the <a href="{$inquiry}">inquiry form</a> and I will reply within 24 hours. Coverage hours and add-ons are listed on <a href="{$collections}">collections</a>.</p>
HTML;
        }

        return null;
    }

    /**
     * @return array<int, array{question: string, answer: string}>
     */
    public static function faqs(Venue $venue): array
    {
        $price = self::startingPriceLabel();

        if ($venue->slug === self::HONEYMOON_SLUG) {
            $faqs = [
                [
                    'question' => 'Do you photograph elopements on Honeymoon Island?',
                    'answer' => 'Yes. Elopements and small weddings on Honeymoon Island, in Dunedin, are welcome. I am based in Clearwater, and the shorter coverage collections are built for that kind of day.',
                ],
                [
                    'question' => 'What does a Honeymoon Island elopement start at?',
                    'answer' => 'Wedding photography collections start at '.$price.'. Coverage is built on hours. Send your date and plans through the inquiry form and I will reply within 24 hours.',
                ],
            ];

            $lauren = self::laurenPost();

            if ($lauren) {
                $faqs[] = [
                    'question' => 'Have you photographed on Honeymoon Island before?',
                    'answer' => 'Yes. '.$lauren->title.' is in the journal, from an engagement session on Honeymoon Island.',
                ];
            }

            return $faqs;
        }

        if ($venue->slug === self::HILTON_SLUG) {
            $story = self::krysinskyStory();

            return [
                [
                    'question' => 'Have you photographed a wedding at Hilton Clearwater Beach?',
                    'answer' => $story
                        ? 'Yes. '.$story->title.' was photographed at Hilton Clearwater Beach Resort & Spa on Clearwater Beach.'
                        : 'Hilton Clearwater Beach Resort & Spa is on Clearwater Beach, inside the Tampa Bay area I photograph from Clearwater. Send the date through the inquiry form to ask about that resort.',
                ],
                [
                    'question' => 'What does wedding photography at Hilton Clearwater Beach start at?',
                    'answer' => 'Collections start at '.$price.'. Send your date and venue through the inquiry form. I reply within 24 hours.',
                ],
            ];
        }

        return [];
    }

    /**
     * @return EloquentCollection<int, WeddingStory>
     */
    public static function relatedStories(Venue $venue): EloquentCollection
    {
        $query = WeddingStory::published()->with(['heroMedia', 'venue']);

        if ($venue->slug === self::HONEYMOON_SLUG) {
            return $query
                ->where(function ($inner): void {
                    $inner->where('title', 'like', '%Honeymoon Island%')
                        ->orWhere('location_name', 'like', '%Honeymoon Island%')
                        ->orWhere('slug', 'like', '%honeymoon-island%');
                })
                ->latest('published_at')
                ->limit(6)
                ->get();
        }

        if ($venue->slug === self::HILTON_SLUG) {
            return $query
                ->where(function ($inner): void {
                    $inner->where('slug', self::KRYSINSKY_SLUG)
                        ->orWhere('title', 'like', '%Krysinsky%')
                        ->orWhere('location_name', 'like', '%Hilton Clearwater%');
                })
                ->latest('published_at')
                ->limit(6)
                ->get();
        }

        return new EloquentCollection;
    }

    /**
     * @return EloquentCollection<int, JournalPost>
     */
    public static function relatedPosts(Venue $venue): EloquentCollection
    {
        $query = JournalPost::published()->withoutArchivedSlugDuplicates()->with('heroMedia');

        if ($venue->slug === self::HONEYMOON_SLUG) {
            return $query
                ->where(function ($inner): void {
                    $inner->where('slug', self::LAUREN_SLUG)
                        ->orWhere('title', 'like', '%Honeymoon Island%')
                        ->orWhere('excerpt', 'like', '%Honeymoon Island%');
                })
                ->latest('published_at')
                ->limit(6)
                ->get();
        }

        if ($venue->slug === self::HILTON_SLUG) {
            return $query
                ->where(function ($inner): void {
                    $inner->where('canonical_url', 'like', '%krysinski%')
                        ->orWhere('title', 'like', '%Krysinsky%')
                        ->orWhere('title', 'like', '%Hilton Clearwater%')
                        ->orWhere('seo_title', 'like', '%Hilton Clearwater%');
                })
                ->latest('published_at')
                ->limit(6)
                ->get();
        }

        return new EloquentCollection;
    }

    /**
     * A short venue section for the Krysinsky wedding story page.
     *
     * @return array{title: string, html: string}|null
     */
    public static function storyVenueNote(WeddingStory $story): ?array
    {
        if (! self::storyIsHilton($story)) {
            return null;
        }

        $price = e(self::startingPriceLabel());
        $venueUrl = e(route('venues.show', self::HILTON_SLUG));

        return [
            'title' => 'Hilton Clearwater Beach Resort & Spa',
            'html' => '<p>This day was photographed at Hilton Clearwater Beach Resort &amp; Spa on Clearwater Beach. Wedding photography collections start at '.$price.'. <a href="'.$venueUrl.'">Hilton Clearwater Beach weddings</a> has a little more about the resort and this wedding.</p>',
        ];
    }

    public static function startingPriceLabel(): string
    {
        $price = Collection::published()->min('starting_price');
        $amount = $price !== null ? (float) $price : 2100;

        return '$'.number_format($amount, 0);
    }

    private static function laurenPost(): ?JournalPost
    {
        return JournalPost::published()
            ->withoutArchivedSlugDuplicates()
            ->where('slug', self::LAUREN_SLUG)
            ->first();
    }

    private static function krysinskyStory(): ?WeddingStory
    {
        return WeddingStory::published()
            ->where(function ($query): void {
                $query->where('slug', self::KRYSINSKY_SLUG)
                    ->orWhere('title', 'like', '%Krysinsky%')
                    ->orWhere('location_name', 'like', '%Hilton Clearwater%');
            })
            ->latest('published_at')
            ->first();
    }

    private static function krysinskyJournalPost(): ?JournalPost
    {
        return self::relatedPosts(new Venue(['slug' => self::HILTON_SLUG]))->first();
    }

    private static function storyIsHilton(WeddingStory $story): bool
    {
        if ($story->slug === self::KRYSINSKY_SLUG) {
            return true;
        }

        $place = strtolower(trim(($story->venue?->name ?? '').' '.($story->location_name ?? '')));

        return str_contains($place, 'hilton clearwater');
    }
}
