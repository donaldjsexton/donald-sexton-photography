<?php

namespace App\Models\Concerns;

/**
 * Splits imported (WordPress) body HTML into its lead paragraph, gallery, and
 * remaining prose so a detail page can render each part once.
 */
trait PresentsImportedContent
{
    /**
     * @return array{hero_copy: ?string, gallery_html: ?string, body_html: ?string}
     */
    protected function importedPresentationContent(
        string $body,
        ?string $defaultHeroCopy,
        bool $extractLead = true,
    ): array {
        $default = [
            'hero_copy' => $defaultHeroCopy,
            'gallery_html' => null,
            'body_html' => $body !== '' ? $body : null,
        ];

        if (! $this->original_wp_post_id || $body === '') {
            return $default;
        }

        $segments = $this->parseImportedPresentationContent($body, $extractLead);

        if ($segments === null) {
            return $default;
        }

        $bodyHtml = trim($segments['body_html']);

        return [
            'hero_copy' => $segments['lead_text'] ?: $default['hero_copy'],
            'gallery_html' => $segments['gallery_html'] ?: null,
            'body_html' => $bodyHtml !== '' ? $bodyHtml : null,
        ];
    }

    /**
     * @return array{lead_text: ?string, gallery_html: string, body_html: string}|null
     */
    private function parseImportedPresentationContent(string $body, bool $extractLead = true): ?array
    {
        $previousState = libxml_use_internal_errors(true);

        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $loaded = $dom->loadHTML(
                '<?xml encoding="utf-8" ?><div id="imported-story-root">'.$body.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );

            if (! $loaded) {
                return null;
            }

            $root = $dom->getElementById('imported-story-root') ?: $dom->getElementsByTagName('div')->item(0);

            if (! $root instanceof \DOMElement) {
                return null;
            }

            foreach ($root->getElementsByTagName('img') as $image) {
                if (! $image->hasAttribute('loading')) {
                    $image->setAttribute('loading', 'lazy');
                }

                if (! $image->hasAttribute('decoding')) {
                    $image->setAttribute('decoding', 'async');
                }
            }

            $leadText = null;
            $galleryParts = [];
            $bodyParts = [];

            foreach ($root->childNodes as $child) {
                $childHtml = trim($dom->saveHTML($child) ?: '');

                if ($childHtml === '' || $this->isEmptyTextNode($child)) {
                    continue;
                }

                if ($extractLead && $leadText === null && $this->isLeadParagraph($child)) {
                    $leadText = $this->normalizeImportedText($childHtml);

                    continue;
                }

                if ($this->isImportedGallery($child)) {
                    $galleryParts[] = $childHtml;

                    continue;
                }

                $bodyParts[] = $childHtml;
            }

            return [
                'lead_text' => $leadText,
                'gallery_html' => trim(implode("\n\n", $galleryParts)),
                'body_html' => trim(implode("\n\n", $bodyParts)),
            ];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousState);
        }
    }

    private function isLeadParagraph(\DOMNode $node): bool
    {
        return $node instanceof \DOMElement
            && strtolower($node->tagName) === 'p'
            && $this->normalizeImportedText($node->textContent) !== '';
    }

    private function isImportedGallery(\DOMNode $node): bool
    {
        if (! $node instanceof \DOMElement) {
            return false;
        }

        $classList = ' '.$node->getAttribute('class').' ';

        return strtolower($node->tagName) === 'figure'
            && (
                str_contains($classList, ' imported-gallery ')
                || str_contains($classList, ' wp-import-gallery ')
            );
    }

    private function isEmptyTextNode(\DOMNode $node): bool
    {
        return $node instanceof \DOMText
            && trim($node->textContent) === '';
    }

    protected function normalizeImportedText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/<\s*br\s*\/?>/i', ' ', $value) ?? $value;
        $value = strip_tags($value);

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
