<?php

namespace App\Models\Concerns;

use App\Support\PicTimeContent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

trait InteractsWithPicTime
{
    public function picTimeDetailMode(): string
    {
        if (! $this->isPicTimeSource()) {
            return 'standard';
        }

        if (! $this->hasHydratedPicTimeGallery()) {
            return 'thin_fallback';
        }

        return $this->hasPicTimeNarrativeOrBody()
            ? 'rich_local_gallery'
            : 'gallery_first';
    }

    protected function manualPicTimeMarkup(): ?string
    {
        $slug = trim((string) ($this->slug ?? ''));

        if ($slug === '') {
            return null;
        }

        $manualPath = storage_path('app/private/manual-pictime/'.$slug.'.html');

        if (! File::exists($manualPath)) {
            return null;
        }

        $markup = trim((string) File::get($manualPath));

        return $markup !== '' ? $markup : null;
    }

    protected function preferredPicTimeMarkup(array $candidates): ?string
    {
        $candidates = array_merge([$this->manualPicTimeMarkup()], $candidates);

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate === '') {
                continue;
            }

            if (
                PicTimeContent::containsEmbed($candidate)
                || PicTimeContent::narrativeBlocks($candidate) !== []
                || PicTimeContent::galleryUrl($candidate) !== null
            ) {
                return $candidate;
            }
        }

        return null;
    }

    public function picTimeHydratedMediaCount(): int
    {
        return method_exists($this, 'media')
            ? ($this->relationLoaded('media') ? $this->media->count() : $this->media()->count())
            : 0;
    }

    public function picTimeNativeGalleryCount(): int
    {
        if (! $this->isPicTimeSource()) {
            return 0;
        }

        $count = $this->picTimeHydratedMediaCount();

        if ($count === 0 || ! method_exists($this, 'media')) {
            return $count;
        }

        $heroId = (int) ($this->hero_media_id ?? 0);

        if ($heroId <= 0) {
            return $count;
        }

        $hasHeroInGallery = $this->relationLoaded('media')
            ? $this->media->contains('id', $heroId)
            : $this->media()->whereKey($heroId)->exists();

        return $hasHeroInGallery ? max(0, $count - 1) : $count;
    }

    public function hasPicTimeNarrativeOrBody(): bool
    {
        $hasExcerpt = trim((string) ($this->excerpt ?? '')) !== '';
        $hasBody = trim(strip_tags((string) ($this->sanitizedBody() ?? ''))) !== '';
        $hasNarrative = $this->picTimeNarrativeBlocks() !== [];

        return $hasExcerpt || $hasBody || $hasNarrative;
    }

    public function hasHydratedPicTimeGallery(): bool
    {
        if (! $this->isPicTimeSource()) {
            return false;
        }

        $nativeCount = $this->picTimeNativeGalleryCount();

        if ($nativeCount >= 2) {
            return true;
        }

        return $nativeCount >= 1 && $this->hasPicTimeNarrativeOrBody();
    }

    public function shouldRenderNativePicTimeGallery(): bool
    {
        if (! $this->isPicTimeSource()) {
            return true;
        }

        return in_array($this->picTimeDetailMode(), ['rich_local_gallery', 'gallery_first'], true);
    }

    /**
     * Return the canonical URL safe to emit as <link rel="canonical"> in HTML.
     * If canonical_url is a pic-time.com domain it is a PicTime import tracking
     * key, not an SEO signal — return null so the caller falls back to the
     * page's own URL.
     */
    public function seoCanonicalUrl(): ?string
    {
        if (! $this->canonical_url) {
            return null;
        }

        $host = parse_url($this->canonical_url, PHP_URL_HOST);

        if (is_string($host) && str_contains(Str::lower($host), 'pic-time.com')) {
            return null;
        }

        return $this->canonical_url;
    }

    public function isPicTimeSource(): bool
    {
        $sourceUrl = $this->externalGalleryUrl();
        $host = parse_url((string) $sourceUrl, PHP_URL_HOST);

        return is_string($host) && str_contains(Str::lower($host), 'pic-time.com');
    }

    public function externalGalleryUrl(): ?string
    {
        return PicTimeContent::galleryUrl(
            method_exists($this, 'picTimeSourceMarkup') ? $this->picTimeSourceMarkup() : null,
            $this->canonical_url ?: $this->original_wp_url
        );
    }

    public function externalGallerySummary(): string
    {
        return 'This Pic-Time gallery is still being finished locally. Open the full gallery in the meantime.';
    }

    public function picTimeEmbedMarkup(): ?string
    {
        if (! $this->isPicTimeSource()) {
            return null;
        }

        return PicTimeContent::normalizedEmbedMarkup($this->picTimeSourceMarkup(), $this->externalGalleryUrl());
    }

    public function picTimeNarrativeBlocks(): array
    {
        return PicTimeContent::narrativeBlocks($this->picTimeSourceMarkup());
    }

    public function needsExternalGalleryFallback(): bool
    {
        return $this->picTimeDetailMode() === 'thin_fallback';
    }

    protected function featuredImageUrlFromBody(): ?string
    {
        $body = (string) ($this->sanitizedBody() ?? '');

        if ($body !== '' && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $body, $matches) === 1) {
            $src = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return $this->localLegacyWordPressAssetUrl($src) ?: $src;
        }

        return null;
    }

    protected function sanitizeImportedHtml(string $html): ?string
    {
        $html = trim($html);

        if ($html === '') {
            return null;
        }

        $previousState = libxml_use_internal_errors(true);

        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $loaded = $dom->loadHTML(
                '<?xml encoding="utf-8" ?><div id="sanitized-import-root">'.$html.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );

            if (! $loaded) {
                return $html;
            }

            $root = $dom->getElementById('sanitized-import-root') ?: $dom->getElementsByTagName('div')->item(0);

            if (! $root instanceof \DOMElement) {
                return $html;
            }

            foreach (iterator_to_array($root->getElementsByTagName('a')) as $anchor) {
                if (! $anchor instanceof \DOMElement || ! $anchor->hasAttribute('href')) {
                    continue;
                }

                $href = html_entity_decode($anchor->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $localHref = $this->localLegacyWordPressAssetUrl($href);

                if ($localHref !== null) {
                    $anchor->setAttribute('href', $localHref);
                }
            }

            foreach (iterator_to_array($root->getElementsByTagName('img')) as $image) {
                if (! $image instanceof \DOMElement) {
                    continue;
                }

                $src = html_entity_decode($image->getAttribute('src'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                if (! $this->canRenderImportedImageUrl($src)) {
                    $this->removeNodeAndEmptyAncestors($image, $root);

                    continue;
                }

                $localSrc = $this->localLegacyWordPressAssetUrl($src);

                if ($localSrc !== null) {
                    $image->setAttribute('src', $localSrc);
                    $image->removeAttribute('srcset');
                    $image->removeAttribute('sizes');
                }

                if (! $image->hasAttribute('loading')) {
                    $image->setAttribute('loading', 'lazy');
                }

                if (! $image->hasAttribute('decoding')) {
                    $image->setAttribute('decoding', 'async');
                }
            }

            $this->unwrapLegacyWixWrappers($root);
            $this->removeBlankParagraphs($root);
            $this->stripLegacyWixClassAttributes($root);

            $sanitized = Collection::make(iterator_to_array($root->childNodes))
                ->map(fn (\DOMNode $node) => $dom->saveHTML($node) ?: '')
                ->implode('');
            $sanitized = trim($sanitized);

            return $sanitized !== '' ? $sanitized : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousState);
        }
    }

    protected function canRenderImportedImageUrl(?string $url): bool
    {
        $url = trim((string) $url);

        if ($url === '') {
            return false;
        }

        if (Str::startsWith($url, 'data:')) {
            return true;
        }

        if ($this->isLegacyWordPressUploadUrl($url)) {
            $legacyPath = (string) (parse_url($url, PHP_URL_PATH) ?: $url);

            if ($this->isJetpackCdnUrl($url)) {
                $legacyPath = (string) preg_replace('#^/[^/]+#', '', $legacyPath);
            }

            return $this->hasLocalPublicAsset($legacyPath);
        }

        if (Str::startsWith($url, '/storage/')) {
            return true;
        }

        if (! preg_match('#^https?://#i', $url)) {
            return false;
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($host === '') {
            return false;
        }

        return ! $this->isCurrentAppHost($host) || Str::startsWith($path, '/storage/');
    }

    protected function isLegacyWordPressUploadUrl(string $url): bool
    {
        return str_contains(Str::lower($url), '/wp-content/uploads/');
    }

    protected function isJetpackCdnUrl(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        return (bool) preg_match('/^i\d+\.wp\.com$/i', $host);
    }

    protected function localLegacyWordPressAssetUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || ! $this->isLegacyWordPressUploadUrl($url)) {
            return null;
        }

        $rawPath = (string) (parse_url($url, PHP_URL_PATH) ?: $url);

        if ($this->isJetpackCdnUrl($url)) {
            $rawPath = (string) preg_replace('#^/[^/]+#', '', $rawPath);
        }

        $path = '/'.ltrim($rawPath, '/');

        if (! $this->hasLocalPublicAsset($path)) {
            return null;
        }

        return $path;
    }

    protected function isCurrentAppHost(string $host): bool
    {
        $host = Str::lower(trim($host));
        $appHost = Str::lower(trim((string) parse_url((string) config('app.url'), PHP_URL_HOST)));

        if ($host === '' || $appHost === '') {
            return false;
        }

        $appBase = preg_replace('/^www\./', '', $appHost) ?? $appHost;
        $hostBase = preg_replace('/^www\./', '', $host) ?? $host;

        return $appBase === $hostBase;
    }

    protected function hasLocalPublicAsset(?string $path): bool
    {
        $path = '/'.ltrim((string) $path, '/');

        if ($path === '/') {
            return false;
        }

        return File::exists(public_path(ltrim($path, '/')));
    }

    protected function unwrapLegacyWixWrappers(\DOMElement $root): void
    {
        $divs = iterator_to_array($root->getElementsByTagName('div'));

        foreach ($divs as $wrapper) {
            if (! $wrapper instanceof \DOMElement || ! $this->hasLegacyWixClass($wrapper)) {
                continue;
            }

            $parent = $wrapper->parentNode;

            if (! $parent instanceof \DOMNode) {
                continue;
            }

            while ($wrapper->firstChild !== null) {
                $parent->insertBefore($wrapper->firstChild, $wrapper);
            }

            $parent->removeChild($wrapper);
        }
    }

    protected function hasLegacyWixClass(\DOMElement $element): bool
    {
        $classAttribute = trim((string) $element->getAttribute('class'));

        if ($classAttribute === '') {
            return false;
        }

        foreach (preg_split('/\s+/', $classAttribute) ?: [] as $className) {
            $className = (string) $className;

            if ($className === '') {
                continue;
            }

            if (Str::startsWith($className, ['s_', 'wix'])) {
                return true;
            }
        }

        return false;
    }

    protected function stripLegacyWixClassAttributes(\DOMElement $root): void
    {
        $elements = iterator_to_array($root->getElementsByTagName('*'));

        foreach ($elements as $element) {
            if (! $element instanceof \DOMElement || ! $element->hasAttribute('class')) {
                continue;
            }

            $classAttribute = trim((string) $element->getAttribute('class'));

            if ($classAttribute === '') {
                $element->removeAttribute('class');

                continue;
            }

            $remaining = [];

            foreach (preg_split('/\s+/', $classAttribute) ?: [] as $className) {
                $className = (string) $className;

                if ($className === '') {
                    continue;
                }

                if ($this->isLegacyWixClassName($className)) {
                    continue;
                }

                $remaining[] = $className;
            }

            if ($remaining === []) {
                $element->removeAttribute('class');
            } else {
                $element->setAttribute('class', implode(' ', $remaining));
            }
        }
    }

    protected function isLegacyWixClassName(string $className): bool
    {
        return Str::startsWith($className, ['s_', 'wix', 'font_', 'color_']);
    }

    protected function removeBlankParagraphs(\DOMElement $root): void
    {
        $paragraphs = iterator_to_array($root->getElementsByTagName('p'));

        foreach ($paragraphs as $paragraph) {
            if (! $paragraph instanceof \DOMElement || ! $paragraph->parentNode instanceof \DOMNode) {
                continue;
            }

            if ($this->isEmptyImportedWrapper($paragraph)) {
                $paragraph->parentNode->removeChild($paragraph);
            }
        }
    }

    protected function removeNodeAndEmptyAncestors(\DOMNode $node, \DOMElement $root): void
    {
        $current = $node;

        while ($current->parentNode instanceof \DOMNode) {
            $parent = $current->parentNode;
            $parent->removeChild($current);

            if (! $parent instanceof \DOMElement || $parent === $root || ! $this->isEmptyImportedWrapper($parent)) {
                break;
            }

            $current = $parent;
        }
    }

    protected function isEmptyImportedWrapper(\DOMElement $element): bool
    {
        if (! in_array(strtolower($element->tagName), ['figure', 'picture', 'a', 'p', 'div', 'span'], true)) {
            return false;
        }

        foreach (['img', 'video', 'iframe', 'source'] as $tag) {
            if ($element->getElementsByTagName($tag)->length > 0) {
                return false;
            }
        }

        $text = html_entity_decode($element->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\xc2\xa0/u', ' ', $text ?? '') ?? $text;
        $text = trim(preg_replace('/\s+/u', ' ', $text ?? '') ?? '');

        return $text === '';
    }

    protected function stripLeadingDuplicateParagraph(?string $html, ?string $lead): ?string
    {
        $html = is_string($html) ? trim($html) : null;
        $lead = is_string($lead) ? trim($lead) : null;

        if ($html === null || $html === '' || $lead === null || $lead === '') {
            return $html !== '' ? $html : null;
        }

        $normalize = function (?string $value): string {
            return Str::of(strip_tags((string) $value))
                ->squish()
                ->lower()
                ->toString();
        };

        $normalizedLead = $normalize($lead);

        if ($normalizedLead === '') {
            return $html;
        }

        $previousState = libxml_use_internal_errors(true);

        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $loaded = $dom->loadHTML(
                '<?xml encoding="utf-8" ?><div id="detail-body-root">'.$html.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );

            if (! $loaded) {
                return $html;
            }

            $root = $dom->getElementById('detail-body-root') ?: $dom->getElementsByTagName('div')->item(0);

            if (! $root instanceof \DOMElement) {
                return $html;
            }

            $nodes = collect(iterator_to_array($root->childNodes))
                ->filter(fn ($node) => ! ($node instanceof \DOMText && trim($node->textContent) === ''))
                ->values();

            $firstNode = $nodes->first();

            if (! $firstNode instanceof \DOMNode) {
                return $html;
            }

            $firstText = $normalize($firstNode->textContent);

            if (
                $firstText === ''
                || (
                    ! Str::startsWith($firstText, $normalizedLead)
                    && ! Str::startsWith($normalizedLead, $firstText)
                )
            ) {
                return $html;
            }

            $root->removeChild($firstNode);

            $remaining = Collection::make(iterator_to_array($root->childNodes))
                ->map(fn ($node) => $dom->saveHTML($node) ?: '')
                ->implode('');
            $remaining = trim($remaining);

            return $remaining !== '' ? $remaining : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousState);
        }
    }
}
