<?php

namespace App\Support;

/**
 * Centralizes how the brand suffix is appended to page `<title>` / og:title
 * values. The per-record SEO generators and location-page scaffolder all emit
 * titles *without* a brand suffix on the understanding that the site appends
 * one in a single place — this is that place.
 *
 * The append is idempotent: a title that already carries the brand (either the
 * full site name or the shorter "Donald Sexton" form) is returned untouched, so
 * pages that hard-code their own suffix never end up doubled.
 */
class MetaTitle
{
    private const SEPARATOR = ' | ';

    public static function format(?string $title, ?string $siteName = null): string
    {
        $title = trim((string) $title);
        $siteName = trim((string) ($siteName ?? config('app.name', 'Donald Sexton Photography')));

        if ($siteName === '') {
            return $title;
        }

        if ($title === '' || strcasecmp($title, $siteName) === 0) {
            return $siteName;
        }

        foreach (self::brandNeedles($siteName) as $needle) {
            if ($needle !== '' && mb_stripos($title, $needle) !== false) {
                return $title;
            }
        }

        return $title.self::SEPARATOR.$siteName;
    }

    /**
     * The brand strings whose presence means a title is already branded: the
     * full site name and the same name with a trailing " Photography" removed
     * (so "… | Donald Sexton" is recognised as already branded).
     *
     * @return array<int, string>
     */
    private static function brandNeedles(string $siteName): array
    {
        $short = preg_replace('/\s+Photography$/i', '', $siteName) ?? $siteName;

        return array_values(array_unique(array_filter([$siteName, $short])));
    }
}
