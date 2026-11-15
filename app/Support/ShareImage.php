<?php

namespace App\Support;

/**
 * Resolves public share-image URLs, including the fallback used when a page
 * has no image of its own and the copies of legacy WordPress uploads that
 * now live on the public media disk.
 */
class ShareImage
{
    public static function resolve(?string $candidate): string
    {
        $candidate = trim((string) $candidate);

        if ($candidate === '') {
            $candidate = trim((string) config('seo.default_og_image', ''));
        }

        if ($candidate === '') {
            return '';
        }

        return self::replacementFor($candidate) ?? $candidate;
    }

    public static function replacementFor(string $pathOrUrl): ?string
    {
        $path = parse_url($pathOrUrl, PHP_URL_PATH);
        $normalized = '/'.ltrim(is_string($path) && $path !== '' ? $path : $pathOrUrl, '/');

        foreach (config('seo.migrated_share_images', []) as $from => $to) {
            if ($normalized === '/'.ltrim((string) $from, '/')) {
                return (string) $to;
            }
        }

        return null;
    }
}
