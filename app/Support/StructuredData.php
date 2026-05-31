<?php

namespace App\Support;

use App\Models\Collection as CollectionModel;
use App\Models\JournalPost;
use App\Models\Media;
use App\Models\SiteSetting;
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

        $settings = SiteSetting::current();
        $phone = trim((string) ($settings->business_phone ?? ''));
        $email = trim((string) ($settings->business_email ?? ''));
        $priceRange = trim((string) ($settings->business_price_range ?? '')) ?: '$$$';

        $address = self::compact([
            '@type' => 'PostalAddress',
            'streetAddress' => trim((string) ($settings->business_street_address ?? '')) ?: null,
            'addressLocality' => trim((string) ($settings->business_locality ?? '')) ?: 'Clearwater',
            'addressRegion' => trim((string) ($settings->business_region ?? '')) ?: 'FL',
            'postalCode' => trim((string) ($settings->business_postal_code ?? '')) ?: null,
            'addressCountry' => trim((string) ($settings->business_country ?? '')) ?: 'US',
        ]);

        $geo = self::geoCoordinates($settings);
        $hoursSpec = self::openingHoursSpecification($settings);

        return self::compact([
            '@context' => 'https://schema.org',
            '@type' => 'WeddingPhotographer',
            '@id' => self::organizationId(),
            'name' => self::siteName(),
            'url' => $siteUrl,
            'description' => 'Calm wedding photography for Clearwater, Tampa, and beyond.',
            'image' => $logoUrl,
            'logo' => $logoUrl,
            'priceRange' => $priceRange,
            'telephone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
            'address' => $address,
            'geo' => $geo,
            'openingHoursSpecification' => $hoursSpec,
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
                [
                    '@type' => 'Offer',
                    'itemOffered' => [
                        '@type' => 'Service',
                        'name' => 'Wedding Photography',
                        'serviceType' => 'Wedding Photography',
                        'areaServed' => 'Florida',
                        'provider' => ['@id' => self::organizationId()],
                    ],
                ],
                [
                    '@type' => 'Offer',
                    'itemOffered' => [
                        '@type' => 'Service',
                        'name' => 'Engagement Photography',
                        'serviceType' => 'Engagement Photography',
                        'areaServed' => 'Florida',
                        'provider' => ['@id' => self::organizationId()],
                    ],
                ],
                [
                    '@type' => 'Offer',
                    'itemOffered' => [
                        '@type' => 'Service',
                        'name' => 'Elopement Photography',
                        'serviceType' => 'Elopement Photography',
                        'areaServed' => 'Florida',
                        'provider' => ['@id' => self::organizationId()],
                    ],
                ],
            ],
            'sameAs' => self::organizationSameAs(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function geoCoordinates(SiteSetting $settings): ?array
    {
        $lat = $settings->business_latitude;
        $lng = $settings->business_longitude;

        if ($lat === null || $lng === null) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat === 0.0 && $lng === 0.0) {
            return null;
        }

        return [
            '@type' => 'GeoCoordinates',
            'latitude' => $lat,
            'longitude' => $lng,
        ];
    }

    /**
     * Wedding photography is by-appointment, so we model availability as a
     * `Mon-Sun 09:00-18:00` window unless the admin overrides the note.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private static function openingHoursSpecification(SiteSetting $settings): ?array
    {
        $note = trim((string) ($settings->business_hours_note ?? ''));

        if ($note === '') {
            return null;
        }

        return [[
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'opens' => '09:00',
            'closes' => '18:00',
            'description' => $note,
        ]];
    }

    /**
     * @return array<int, string>|null
     */
    private static function organizationSameAs(): ?array
    {
        $urls = SiteSetting::current()->socialProfileUrls();

        return $urls === [] ? null : $urls;
    }

    /**
     * @param  array<int, array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    public static function breadcrumbList(array $items): array
    {
        $elements = [];

        foreach (array_values($items) as $index => $item) {
            $name = trim((string) ($item['name'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));

            if ($name === '' || $url === '') {
                continue;
            }

            $elements[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $name,
                'item' => $url,
            ];
        }

        if ($elements === []) {
            return [];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function journalPost(JournalPost $post): array
    {
        $canonical = $post->canonical_url ?: route('journal.show', $post->slug);
        $featuredImage = method_exists($post, 'featuredImageUrl') ? $post->featuredImageUrl() : null;

        $author = filled($post->author_name)
            ? ['@type' => 'Person', 'name' => $post->author_name]
            : ['@id' => self::organizationId()];

        return self::compact([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post->title,
            'description' => $post->seo_description ?: $post->excerpt ?: $post->summaryText(40),
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            'image' => $featuredImage,
            'thumbnailUrl' => $featuredImage,
            'url' => $canonical,
            'author' => $author,
            'publisher' => ['@id' => self::organizationId()],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $canonical,
            ],
            'isPartOf' => [
                '@type' => 'Blog',
                '@id' => route('journal.index').'#journal',
                'name' => 'Journal',
                'url' => route('journal.index'),
            ],
        ]);
    }

    /**
     * Build Photograph entries for the supplied media collection so AI
     * crawlers receive caption/alt text alongside the gallery URLs.
     *
     * @param  Collection<int, Media>  $media
     * @return array<int, array<string, mixed>>
     */
    public static function photographs(Collection $media, ?string $altFallback = null): array
    {
        return $media
            ->map(function (Media $item) use ($altFallback): ?array {
                $url = $item->publicUrl();

                if (! $url) {
                    return null;
                }

                return self::compact([
                    '@type' => 'Photograph',
                    'contentUrl' => $url,
                    'url' => $url,
                    'name' => $item->caption ?: $item->alt_text ?: $altFallback,
                    'description' => $item->alt_text ?: $altFallback,
                    'caption' => $item->caption ?: null,
                    'width' => $item->width ? (int) $item->width : null,
                    'height' => $item->height ? (int) $item->height : null,
                    'creditText' => $item->credit ?: null,
                    'creator' => ['@id' => self::organizationId()],
                    'copyrightHolder' => ['@id' => self::organizationId()],
                ]);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, CollectionModel>  $collections
     * @return array<string, mixed>
     */
    public static function collectionOfferCatalog(Collection $collections): array
    {
        $offers = $collections
            ->map(function (CollectionModel $collection): ?array {
                $name = trim((string) $collection->name);

                if ($name === '') {
                    return null;
                }

                $price = $collection->starting_price !== null
                    ? number_format((float) $collection->starting_price, 2, '.', '')
                    : null;

                return self::compact([
                    '@type' => 'Offer',
                    'name' => $name,
                    'description' => $collection->summary ?: $collection->headline ?: null,
                    'price' => $price,
                    'priceCurrency' => $price ? 'USD' : null,
                    'priceSpecification' => $price ? [
                        '@type' => 'PriceSpecification',
                        'price' => $price,
                        'priceCurrency' => 'USD',
                        'valueAddedTaxIncluded' => false,
                    ] : null,
                    'availability' => 'https://schema.org/InStock',
                    'category' => 'Wedding Photography',
                    'itemOffered' => self::compact([
                        '@type' => 'Service',
                        'name' => $name,
                        'serviceType' => 'Wedding Photography',
                        'description' => $collection->summary ?: $collection->headline ?: null,
                        'provider' => ['@id' => self::organizationId()],
                        'areaServed' => 'Florida',
                    ]),
                ]);
            })
            ->filter()
            ->values()
            ->all();

        if ($offers === []) {
            return [];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'OfferCatalog',
            'name' => 'Wedding Photography Collections',
            'url' => route('collections.index'),
            'provider' => ['@id' => self::organizationId()],
            'itemListElement' => $offers,
        ];
    }

    /**
     * @param  array<int, array{question: string, answer: string}>  $items
     * @return array<string, mixed>
     */
    public static function faqPage(array $items): array
    {
        $entities = [];

        foreach ($items as $item) {
            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));

            if ($question === '' || $answer === '') {
                continue;
            }

            $entities[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }

        if ($entities === []) {
            return [];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
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

        $photographs = self::photographs($galleryMedia, $story->title);

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
            'associatedMedia' => $photographs ?: null,
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
