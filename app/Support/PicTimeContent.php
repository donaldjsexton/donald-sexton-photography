<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PicTimeContent
{
    public static function containsEmbed(?string $markup): bool
    {
        $markup = trim((string) $markup);

        return $markup !== '' && Str::contains($markup, ['data-pt-type=', 'slideswebcomponentembed.js', 'searchread_']);
    }

    public static function narrativeBlocks(?string $markup): array
    {
        $markup = trim((string) $markup);

        if ($markup === '') {
            return [];
        }

        if (preg_match('/searchread_[a-z0-9]+\s*=\s*`(.+?)`;/is', $markup, $matches) === 1) {
            $raw = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $raw = str_replace(["\r\n", "\r"], "\n", $raw);
            $raw = trim($raw);

            if ($raw === '') {
                return [];
            }

            $sections = preg_split("/\n{2,}/", $raw) ?: [];

            if ($sections === []) {
                return [];
            }

            $firstLines = array_values(array_filter(array_map('trim', explode("\n", array_shift($sections)))));
            $remainder = count($firstLines) >= 4
                ? implode("\n", array_slice($firstLines, 3))
                : '';

            if ($remainder !== '') {
                array_unshift($sections, $remainder);
            }

            return collect($sections)
                ->map(function (string $section): ?string {
                    $lines = array_values(array_filter(array_map('trim', explode("\n", $section))));

                    if ($lines === []) {
                        return null;
                    }

                    $text = trim(preg_replace('/\s+/', ' ', implode(' ', $lines)) ?? '');

                    if ($text === '' || Str::lower($text) === 'view full gallery') {
                        return null;
                    }

                    return self::normalizeText($text);
                })
                ->filter()
                ->values()
                ->all();
        }

        return self::narrativeBlocksFromSavedMarkup($markup);
    }

    public static function excerpt(?string $markup): ?string
    {
        return self::narrativeBlocks($markup)[0] ?? null;
    }

    public static function bodyHtml(?string $markup): ?string
    {
        $blocks = self::narrativeBlocks($markup);

        if ($blocks === []) {
            return null;
        }

        return Collection::make($blocks)
            ->map(fn (string $block) => '<p>'.e($block).'</p>')
            ->implode("\n");
    }

    public static function normalizedEmbedMarkup(?string $markup, ?string $galleryUrl): ?string
    {
        $markup = trim((string) $markup);

        if ($markup === '') {
            return null;
        }

        $host = parse_url((string) $galleryUrl, PHP_URL_HOST);

        if (! is_string($host) || ! str_contains(Str::lower($host), 'pic-time.com')) {
            if (preg_match('~https?://gallery\.([a-z0-9.-]+)/~i', $markup, $matches) === 1) {
                $host = $matches[1].'.pic-time.com';
            } elseif (preg_match('~https?://([a-z0-9.-]+\.pic-time\.com)/~i', $markup, $matches) === 1) {
                $host = $matches[1];
            } else {
                $host = null;
            }
        }

        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace_callback(
            '/(<script\b[^>]*\bsrc=["\'])([^"\']+)(["\'][^>]*><\/script>)/i',
            function (array $matches) use ($host): string {
                $src = $matches[2];

                if (preg_match('~https?://[^/]+(/[^?#"\']+/slideswebcomponentembed\.js/[a-z0-9]+(?:\?[^"\']*)?)~i', $src, $srcMatches) === 1) {
                    $src = 'https://'.$host.$srcMatches[1];
                } elseif (preg_match('~^(/[^?#"\']+/slideswebcomponentembed\.js/[a-z0-9]+(?:\?[^"\']*)?)$~i', $src, $srcMatches) === 1) {
                    $src = 'https://'.$host.$srcMatches[1];
                }

                return $matches[1].$src.$matches[3];
            },
            $markup
        ) ?: $markup;
    }

    public static function galleryUrl(?string $markup, ?string $fallbackUrl = null): ?string
    {
        $fallbackUrl = trim((string) $fallbackUrl);

        if ($fallbackUrl !== '') {
            $host = parse_url($fallbackUrl, PHP_URL_HOST);

            if (is_string($host) && str_contains(Str::lower($host), 'pic-time.com')) {
                return $fallbackUrl;
            }
        }

        $markup = trim((string) $markup);

        if ($markup === '') {
            return null;
        }

        if (preg_match('~https?://gallery\.([a-z0-9.-]+)/([^/"\'?#]+)/slideswebcomponentembed\.js/[a-z0-9]+~i', $markup, $matches) === 1) {
            return 'https://'.$matches[1].'.pic-time.com/'.$matches[2];
        }

        if (preg_match('~(https?://[a-z0-9.-]+\.pic-time\.com/[^/"\'?#]+)/slideswebcomponentembed\.js/[a-z0-9]+~i', $markup, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private static function narrativeBlocksFromSavedMarkup(string $markup): array
    {
        if (
            preg_match_all(
                '/<(h[1-6]|div|p)\b[^>]*data-tokenid="text(\d+)"[^>]*>(.*?)<\/\1>/is',
                $markup,
                $matches,
                PREG_SET_ORDER
            ) !== 1
            && empty($matches)
        ) {
            return [];
        }

        $blocks = [];
        $pendingHeading = null;

        foreach ($matches as $match) {
            $tag = Str::lower($match[1]);
            $token = (int) $match[2];
            $parts = self::splitHtmlBlockIntoTextParts($match[3]);

            if ($parts === []) {
                continue;
            }

            if (in_array($token, [1, 2, 3, 9], true)) {
                continue;
            }

            if ($token === 6) {
                $pendingHeading = $parts[0];
                continue;
            }

            if ($token === 5 || in_array($tag, ['h2', 'h3', 'h4'], true)) {
                $pendingHeading = $parts[0];
                continue;
            }

            if ($pendingHeading !== null) {
                $blocks[] = $pendingHeading;
                $pendingHeading = null;
            }

            foreach ($parts as $part) {
                $blocks[] = $part;
            }
        }

        return collect($blocks)
            ->map(fn (string $block) => self::normalizeText($block))
            ->filter(fn (?string $block) => filled($block) && Str::lower($block) !== 'view full gallery')
            ->values()
            ->all();
    }

    private static function splitHtmlBlockIntoTextParts(string $html): array
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n\n", $html) ?? $html;
        $html = strip_tags($html);
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = trim($html);

        if ($html === '') {
            return [];
        }

        return collect(preg_split("/\n{2,}/", $html) ?: [])
            ->map(function (string $part): ?string {
                $part = trim(preg_replace('/[ \t]+/', ' ', $part) ?? '');
                $part = trim(preg_replace('/\n+/', ' ', $part) ?? '');

                return $part !== '' ? $part : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private static function normalizeText(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        $text = preg_replace('/\s*-\s*(Wedding|Engagement|Elopement|Proposal)\b/u', ' - $1', $text) ?? $text;

        return trim($text);
    }
}
