<?php

namespace App\Support;

use App\Models\Media;
use App\Models\Venue;
use App\Models\WeddingStory;
use Illuminate\Support\Collection;

class StructuredData
{
    public static function siteUrl(): string
    {
        return rtrim((string) config('app.url', url('/')), '/');
    }

    public static function siteName(): string
    {
        return (string) config('app.name', 'Donald Sexton Photography');
    }

    public static function organizationId(): string
    {
        return self::siteUrl().'/#organization';
    }

    public static function venueId(Venue $venue): string
    {
        return route('venues.show', $venue->slug).'#place';
    }

    /**
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        $siteUrl = self::siteUrl();
        $logo = (string) config('seo.default_og_image', '');
        $logoUrl = $logo === '' ? null : (preg_match('#^https?://#i', $logo) ? $logo : url($logo));

        return self::compact([
            '@context' => 'https://schema.org',
            '@type' => 'WeddingPhotographer',
            '@id' => self::organizationId(),
            'name' => self::siteName(),
            'url' => $siteUrl,
            'description' => 'Calm wedding photography for Clearwater, Tampa, and beyond.',
            'image' => $logoUrl,
            'logo' => $logoUrl,
            'priceRange' => '$$$',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Clearwater',
                'addressRegion' => 'FL',
                'addressCountry' => 'US',
            ],
            'areaServed' => [
                ['@type' => 'City', 'name' => 'Clearwater'],
                ['@type' => 'City', 'name' => 'Tampa'],
                ['@type' => 'City', 'name' => 'St. Petersburg'],
                ['@type' => 'City', 'name' => 'Sarasota'],
                ['@type' => 'State', 'name' => 'Florida'],
            ],
            'knowsAbout' => [
                'Wedding Photography',
                'Engagement Photography',
                'Elopement Photography',
                'Documentary Wedding Photography',
            ],
            'makesOffer' => [
                '@type' => 'Offer',
                'itemOffered' => [
                    '@type' => 'Service',
                    'name' => 'Wedding Photography',
                    'serviceType' => 'Wedding Photography',
                    'areaServed' => 'Florida',
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function place(Venue $venue): array
    {
        $heroUrl = $venue->heroMedia?->publicUrl();
        $sameAs = array_values(array_filter([
            $venue->website_url ?: null,
            $venue->google_places_id
                ? 'https://www.google.com/maps/place/?q=place_id:'.$venue->google_places_id
                : null,
        ]));

        return self::compact([
            '@context' => 'https://schema.org',
            '@type' => 'Place',
            '@id' => self::venueId($venue),
            'name' => $venue->name,
            'url' => route('venues.show', $venue->slug),
            'description' => $venue->summary ?: $venue->headline ?: null,
            'image' => $heroUrl,
            'address' => self::compact([
                '@type' => 'PostalAddress',
                'addressLocality' => $venue->city ?: null,
                'addressRegion' => $venue->state ?: null,
                'addressCountry' => 'US',
            ]),
            'sameAs' => $sameAs ?: null,
        ]);
    }

    /**
     * Resolve the media collection that should appear in JSON-LD for a story.
     * Only the images the page actually renders are included so the markup
     * stays in sync with the user-visible gallery.
     */
    public static function galleryMediaForStory(WeddingStory $story, PicTimeDetailPresentation $pictime): Collection
    {
        if ($pictime->showNativeGallery()) {
            $native = $pictime->nativeGallery();

            if ($native->isNotEmpty()) {
                return $native;
            }
        }

        return collect(array_filter([$story->heroMedia]));
    }

    /**
     * @param  Collection<int, Media>  $galleryMedia
     * @return array<string, mixed>
     */
    public static function weddingStory(WeddingStory $story, Collection $galleryMedia): array
    {
        $canonical = $story->canonical_url ?: route('weddings.show', $story->slug);
        $featuredImage = $story->featuredImageUrl();

        $images = $galleryMedia
            ->map(fn ($media) => $media->publicUrl())
            ->filter()
            ->values()
            ->all();

        $couple = is_array($story->client_names)
            ? trim(implode(' & ', array_filter($story->client_names)))
            : null;

        $headline = filled($couple)
            ? $couple.' — Wedding at '.($story->venue?->name ?: $story->location_name ?: 'Florida')
            : $story->title;

        return self::compact([
            '@context' => 'https://schema.org',
            '@type' => 'ImageGallery',
            'name' => $headline,
            'headline' => $story->title,
            'description' => $story->seo_description ?: $story->summaryText(40),
            'url' => $canonical,
            'datePublished' => $story->published_at?->toIso8601String(),
            'dateModified' => $story->updated_at?->toIso8601String(),
            'image' => $images ?: ($featuredImage ? [$featuredImage] : null),
            'thumbnailUrl' => $featuredImage,
            'contentLocation' => $story->venue
                ? ['@id' => self::venueId($story->venue)]
                : ($story->location_name
                    ? [
                        '@type' => 'Place',
                        'name' => $story->location_name,
                        'address' => self::compact([
                            '@type' => 'PostalAddress',
                            'addressLocality' => $story->city ?: null,
                            'addressRegion' => $story->state ?: null,
                            'addressCountry' => 'US',
                        ]),
                    ]
                    : null),
            'about' => self::compact([
                '@type' => 'Event',
                'name' => $headline,
                'startDate' => $story->event_date?->toDateString(),
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'location' => $story->venue
                    ? ['@id' => self::venueId($story->venue)]
                    : null,
            ]),
            'creator' => ['@id' => self::organizationId()],
            'copyrightHolder' => ['@id' => self::organizationId()],
        ]);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private static function compact(array $value): array
    {
        $filtered = [];

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $cleaned = self::compact($item);

                if ($cleaned !== []) {
                    $filtered[$key] = $cleaned;
                }

                continue;
            }

            if ($item === null || $item === '') {
                continue;
            }

            $filtered[$key] = $item;
        }

        return $filtered;
    }
}
