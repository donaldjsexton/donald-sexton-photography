<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Templated copy used when scaffolding a new "Wedding photographer in [City]"
 * landing page from the admin. Output is deliberately plain so the photographer
 * can tighten the voice before publishing.
 */
class LocationPageDraft
{
    public function __construct(
        public readonly string $city,
        public readonly ?string $state,
        public readonly ?string $region,
        public readonly string $title,
        public readonly string $slug,
        public readonly string $excerpt,
        public readonly string $body,
        public readonly string $seoTitle,
        public readonly string $seoDescription,
    ) {}

    public static function build(string $city, ?string $state = null, ?string $region = null): self
    {
        $city = trim($city);
        $state = $state !== null ? trim($state) : null;
        $region = $region !== null ? trim($region) : null;

        $locationLabel = self::locationLabel($city, $state);
        $title = $city.' Wedding Photographer';
        $slug = Str::slug($city);

        $excerpt = 'Calm, documentary wedding photography in '.$locationLabel.'. Engagement sessions, full-day coverage, and elopements led with a steady hand.';

        $body = self::body($city, $locationLabel, $region);

        $seoTitle = $city.' Wedding Photographer | Donald Sexton';
        $seoDescription = self::clamp(
            'Donald Sexton is a wedding photographer covering '.$locationLabel.'. See recent stories, planning notes, and the venues couples are choosing in the area.',
            160,
        );

        return new self(
            city: $city,
            state: $state,
            region: $region,
            title: $title,
            slug: $slug,
            excerpt: $excerpt,
            body: $body,
            seoTitle: $seoTitle,
            seoDescription: $seoDescription,
        );
    }

    private static function locationLabel(string $city, ?string $state): string
    {
        if ($state === null || $state === '') {
            return $city;
        }

        return $city.', '.$state;
    }

    private static function body(string $city, string $locationLabel, ?string $region): string
    {
        $regionLine = $region !== null && $region !== ''
            ? '<p>Whether the day is staying close to '.$city.' or spilling out into the broader '.$region.' area, the work is calm, observational, and built to last.</p>'
            : '<p>Whether the day is staying close to '.$city.' or spilling out along the coast, the work is calm, observational, and built to last.</p>';

        return <<<HTML
<p>I am Donald Sexton, a wedding photographer covering {$locationLabel}. Most days begin near the water and end somewhere quiet — and the photographs are made to feel exactly that way.</p>
{$regionLine}
<p>You can browse recent weddings and engagement sessions photographed in and around {$city} below, then send your date and venue when you are ready to talk.</p>
HTML;
    }

    private static function clamp(string $value, int $limit): string
    {
        $trimmed = trim($value);

        if (mb_strlen($trimmed) <= $limit) {
            return $trimmed;
        }

        return rtrim(mb_substr($trimmed, 0, $limit - 1)).'…';
    }
}
