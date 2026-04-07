<?php

namespace App\Services\PicTime;

use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\JournalPost;
use App\Models\Media;
use App\Models\WeddingStory;
use App\Support\PicTimeContent;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class PicTimeImporter
{
    public function importSources(array $sources, string $target = 'auto'): ImportRun
    {
        $resolvedSources = $this->resolveSources($sources);

        $run = ImportRun::query()->create([
            'source_type' => 'pictime',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $summary = [
            'sources_seen' => 0,
            'items_imported' => 0,
            'items_updated' => 0,
            'items_skipped' => 0,
            'media_imported' => 0,
            'media_reused' => 0,
            'failures' => 0,
        ];

        $errors = [];

        foreach ($resolvedSources as $source) {
            $summary['sources_seen']++;

            try {
                $result = DB::transaction(function () use ($run, $source, $target): array {
                    $loaded = $this->loadSource($source);
                    $payload = $this->parseHtml($loaded['html'], $loaded['source_url']);
                    $payload = $this->mergeContextIntoPayload($payload, $loaded['context'] ?? []);

                    if ($payload['title'] === null && $payload['image_urls'] === []) {
                        return ['status' => 'skipped', 'media_imported' => 0, 'media_reused' => 0];
                    }

                    $targetType = $this->resolveTarget($payload, $target);
                    $record = $this->upsertTargetRecord($run, $loaded, $payload, $targetType);
                    $mediaSummary = $this->syncMedia($run, $record, $payload, $loaded['stable_source_id']);

                    return [
                        'status' => $record->wasRecentlyCreated ? 'imported' : 'updated',
                        'media_imported' => $mediaSummary['imported'],
                        'media_reused' => $mediaSummary['reused'],
                    ];
                });

                match ($result['status']) {
                    'imported' => $summary['items_imported']++,
                    'updated' => $summary['items_updated']++,
                    default => $summary['items_skipped']++,
                };

                $summary['media_imported'] += $result['media_imported'];
                $summary['media_reused'] += $result['media_reused'];
            } catch (Throwable $throwable) {
                $summary['failures']++;
                $sourceLabel = is_array($source)
                    ? ($source['source_url'] ?? $source['source'] ?? 'pictime-source')
                    : (string) $source;
                $errors[] = $sourceLabel.': '.$throwable->getMessage();
            }
        }

        $run->update([
            'status' => $summary['failures'] > 0 ? 'failed' : 'completed',
            'finished_at' => now(),
            'summary_json' => $summary,
            'error_log' => $errors === [] ? null : implode("\n", $errors),
        ]);

        return $run->fresh();
    }

    private function resolveSources(array $sources): array
    {
        return collect($sources)
            ->map(fn ($source) => trim((string) $source))
            ->filter()
            ->flatMap(function (string $source) {
                if (Str::startsWith($source, ['http://', 'https://'])) {
                    $normalizedUrl = $this->normalizePicTimeUrl($source);

                    return [[
                        'source' => $source,
                        'source_url' => $normalizedUrl,
                        'stable_source_id' => $this->stableIdFor($normalizedUrl),
                        'html' => null,
                    ]];
                }

                if (! File::exists($source)) {
                    throw new \RuntimeException("Source not found: {$source}");
                }

                if (File::isDirectory($source)) {
                    return $this->discoverFromDirectory($source);
                }

                if (Str::endsWith(Str::lower($source), '.xml')) {
                    return $this->discoverFromWordPressXml($source);
                }

                return [[
                    'source' => $source,
                    'source_url' => null,
                    'stable_source_id' => $this->stableIdFor(realpath($source) ?: $source),
                    'html' => null,
                ]];
            })
            ->unique(fn (array $source) => $source['stable_source_id'].'|'.($source['source'] ?? ''))
            ->values()
            ->all();
    }

    private function discoverFromDirectory(string $directory): array
    {
        return collect(File::allFiles($directory))
            ->filter(fn (\SplFileInfo $file) => in_array(Str::lower($file->getExtension()), ['html', 'htm', 'xml'], true))
            ->flatMap(function (\SplFileInfo $file) {
                $path = $file->getRealPath() ?: $file->getPathname();

                if (Str::endsWith(Str::lower($path), '.xml')) {
                    return $this->discoverFromWordPressXml($path);
                }

                return [[
                    'source' => $path,
                    'source_url' => null,
                    'stable_source_id' => $this->stableIdFor($path),
                    'html' => null,
                ]];
            })
            ->values()
            ->all();
    }

    private function discoverFromWordPressXml(string $path): array
    {
        $contents = File::get($path);
        $xml = simplexml_load_string($contents ?: '', \SimpleXMLElement::class, LIBXML_NOCDATA);

        if (! $xml instanceof \SimpleXMLElement) {
            throw new \RuntimeException("The XML source could not be parsed: {$path}");
        }

        $namespaces = $xml->getNamespaces(true);
        $items = $xml->channel?->item ?? [];
        $attachmentsByParent = $this->attachmentMapFromItems($items, $namespaces);
        $sources = [];

        foreach ($items as $index => $item) {
            $wp = $item->children($namespaces['wp'] ?? null);

            if ((string) $wp->post_type !== 'post') {
                continue;
            }

            $content = (string) $item->children($namespaces['content'] ?? null)->encoded;
            $link = trim((string) $item->link);
            $picTimeUrl = $this->firstPicTimeUrl([$link, $content]);
            $isCandidate = $picTimeUrl !== null || $this->looksLikePicTimeHtml($content);

            if (! $isCandidate) {
                continue;
            }

            $postId = (int) ($wp->post_id ?? 0);
            $itemKey = (string) ($postId ?: $index + 1);
            $sourceRef = $path.'#'.$itemKey;
            $sourceUrl = $picTimeUrl ?: ($link !== '' ? $link : null);
            $html = trim($content) !== '' && (! $this->looksLikePicTimeEmbedOnlyHtml($content) || $this->hasEmbeddedSearchRead($content))
                ? $content
                : null;
            $categories = collect($item->category ?? [])
                ->map(fn ($term) => [
                    'domain' => (string) $term['domain'],
                    'nicename' => trim((string) $term['nicename']),
                    'name' => $this->normalizeText((string) $term),
                ])
                ->filter(fn (array $term) => ($term['name'] ?? null) !== null)
                ->values()
                ->all();
            $attachments = $attachmentsByParent[$postId] ?? [];

            $sources[] = [
                'source' => $sourceRef,
                'source_url' => $sourceUrl,
                'stable_source_id' => $this->stableIdFor($sourceUrl ?: $sourceRef),
                'html' => $html,
                'context' => [
                    'title' => $this->normalizeText((string) $item->title),
                    'slug' => trim((string) $wp->post_name) ?: null,
                    'original_url' => $link !== '' ? $link : null,
                    'excerpt' => $this->normalizeText((string) $item->children($namespaces['excerpt'] ?? null)->encoded),
                    'embed_html' => trim($content) !== '' && $this->looksLikePicTimeEmbedOnlyHtml($content)
                        ? trim($content)
                        : null,
                    'published_at' => $this->resolvePublishedAt((string) $wp->post_date_gmt ?: (string) $wp->post_date ?: (string) $item->pubDate),
                    'post_type' => $this->resolveXmlPostType($categories),
                    'story_type' => $this->resolveXmlStoryType($categories),
                    'thumbnail_url' => $attachments[0]['url'] ?? null,
                    'image_urls' => collect($attachments)->pluck('url')->filter()->values()->all(),
                ],
            ];
        }

        return $sources;
    }

    private function loadSource(array|string $source): array
    {
        $sourceRecord = is_array($source) ? $source : null;

        if (is_array($source)) {
            if (($source['html'] ?? null) !== null) {
                $html = (string) $source['html'];
                $context = $source['context'] ?? null;
                $originalUrl = is_array($context) ? ($context['original_url'] ?? null) : null;

                if (! $this->hasEmbeddedSearchRead($html) && is_string($originalUrl) && Str::startsWith($originalUrl, ['http://', 'https://'])) {
                    try {
                        $response = Http::timeout(30)
                            ->withHeaders(['User-Agent' => 'DonaldSextonImporter/1.0'])
                            ->get($originalUrl);

                        $response->throw();

                        if ($this->hasEmbeddedSearchRead($response->body())) {
                            $source['html'] = $response->body();
                        }
                    } catch (Throwable) {
                    }
                }

                return $source;
            }

            $source = (string) ($source['source_url'] ?? $source['source']);
        }

        if (Str::startsWith($source, ['http://', 'https://'])) {
            $source = $this->normalizePicTimeUrl($source);
            $originalUrl = is_array($sourceRecord['context'] ?? null) ? ($sourceRecord['context']['original_url'] ?? null) : null;

            if (is_string($originalUrl) && Str::startsWith($originalUrl, ['http://', 'https://'])) {
                try {
                    $response = Http::timeout(30)
                        ->withHeaders(['User-Agent' => 'DonaldSextonImporter/1.0'])
                        ->get($originalUrl);

                    $response->throw();

                    if ($this->hasEmbeddedSearchRead($response->body())) {
                        return [
                            'source' => $source,
                            'source_url' => $source,
                            'stable_source_id' => $this->stableIdFor($source),
                            'html' => $response->body(),
                            'context' => $sourceRecord['context'] ?? null,
                        ];
                    }
                } catch (Throwable) {
                }
            }

            try {
                $response = Http::timeout(30)
                    ->withHeaders(['User-Agent' => 'DonaldSextonImporter/1.0'])
                    ->get($source);

                $response->throw();
            } catch (Throwable $throwable) {
                if (($sourceRecord['context'] ?? null) !== null) {
                    return [
                        'source' => $source,
                        'source_url' => $source,
                        'stable_source_id' => $this->stableIdFor($source),
                        'html' => '<html><body></body></html>',
                        'context' => $sourceRecord['context'],
                    ];
                }

                throw $throwable;
            }

            return [
                'source' => $source,
                'source_url' => $source,
                'stable_source_id' => $this->stableIdFor($source),
                'html' => $response->body(),
                'context' => $sourceRecord['context'] ?? null,
            ];
        }

        if (! File::exists($source)) {
            throw new \RuntimeException("Source not found: {$source}");
        }

        return [
            'source' => $source,
            'source_url' => null,
            'stable_source_id' => $this->stableIdFor(realpath($source) ?: $source),
            'html' => File::get($source),
            'context' => $sourceRecord['context'] ?? null,
        ];
    }

    private function mergeContextIntoPayload(array $payload, array $context): array
    {
        if ($context === []) {
            return $payload;
        }

        $payload['title'] = $context['title'] ?? $payload['title'];
        $payload['slug'] = Str::slug($context['slug'] ?? $payload['slug']) ?: ($payload['slug'] ?: 'pictime-story');
        $payload['published_at'] = $context['published_at'] ?? $payload['published_at'];
        $payload['excerpt'] = $context['excerpt'] ?: $payload['excerpt'];
        $payload['post_type'] = $context['post_type'] ?? $payload['post_type'];
        $payload['story_type'] = $context['story_type'] ?? $payload['story_type'];
        $payload['source_markup'] = $context['embed_html'] ?? ($payload['source_markup'] ?? null);

        if (blank($payload['excerpt']) && filled($payload['source_markup'])) {
            $payload['excerpt'] = PicTimeContent::excerpt($payload['source_markup']);
        }

        if (blank($payload['body_html']) && filled($payload['source_markup'])) {
            $payload['body_html'] = PicTimeContent::bodyHtml($payload['source_markup']);
        }

        $contextImages = collect($context['image_urls'] ?? [])
            ->prepend($context['thumbnail_url'] ?? null)
            ->filter()
            ->values()
            ->all();

        if ($contextImages !== []) {
            $payload['image_urls'] = array_values(array_unique(array_merge($contextImages, $payload['image_urls'] ?? [])));
        }

        if (($payload['image_urls'] ?? []) === [] && filled($payload['source_markup'])) {
            $payload['image_urls'] = $this->extractGalleryImageUrlsFromEmbedMarkup($payload['source_markup']);
        }

        return $payload;
    }

    private function attachmentMapFromItems(iterable $items, array $namespaces): array
    {
        $attachments = [];

        foreach ($items as $item) {
            $wp = $item->children($namespaces['wp'] ?? null);

            if ((string) $wp->post_type !== 'attachment') {
                continue;
            }

            $parentId = (int) $wp->post_parent;
            $attachmentUrl = trim((string) $wp->attachment_url);

            if ($parentId === 0 || $attachmentUrl === '') {
                continue;
            }

            $attachments[$parentId] ??= [];
            $attachments[$parentId][] = [
                'id' => (int) $wp->post_id,
                'url' => $attachmentUrl,
                'title' => $this->normalizeText((string) $item->title),
            ];
        }

        return $attachments;
    }

    private function resolveXmlPostType(array $categories): string
    {
        $terms = collect($categories)
            ->pluck('nicename')
            ->map(fn ($value) => Str::slug((string) $value))
            ->filter()
            ->values();

        return match (true) {
            $terms->contains(fn ($term) => str_contains($term, 'engagement')) => 'engagement',
            $terms->contains(fn ($term) => str_contains($term, 'venue')) => 'venue',
            $terms->contains(fn ($term) => in_array($term, ['real-wedding', 'real-weddings', 'wedding', 'weddings'], true)) => 'real_wedding',
            default => 'advice',
        };
    }

    private function resolveXmlStoryType(array $categories): string
    {
        return match ($this->resolveXmlPostType($categories)) {
            'engagement' => 'engagement',
            default => 'wedding',
        };
    }

    private function parseHtml(string $html, ?string $sourceUrl): array
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previousState = libxml_use_internal_errors(true);

        try {
            $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

            if (! $loaded) {
                throw new \RuntimeException('Pic-Time HTML could not be parsed.');
            }

            $xpath = new \DOMXPath($dom);
            $title = $this->firstContent($xpath, [
                "//meta[@property='og:title']/@content",
                "//meta[@name='twitter:title']/@content",
                '//article//*[self::h1 or self::h2][1]',
                '//main//*[self::h1 or self::h2][1]',
                '//h1[1]',
                '//title[1]',
            ]);

            $publishedAt = $this->resolvePublishedAt($this->firstContent($xpath, [
                "//meta[@property='article:published_time']/@content",
                "//meta[@name='article:published_time']/@content",
                '//time[1]/@datetime',
                "//meta[@property='og:updated_time']/@content",
            ])) ?? $this->extractProjectDate($html);
            $embedded = $this->extractEmbeddedSearchReadPayload($html);

            $textBlocks = [];
            $imageUrls = $this->extractMetaImageUrls($xpath, $sourceUrl);

            try {
                $container = $this->resolveContentContainer($xpath);
            } catch (\RuntimeException) {
                $container = null;
            }

            if ($container instanceof \DOMNode) {
                $textBlocks = $this->extractTextBlocks($container, $dom);
                $imageUrls = array_values(array_unique(array_merge(
                    $imageUrls,
                    $this->extractImageUrls($container, $sourceUrl),
                )));
            }
            $bodyHtml = $this->buildBodyHtml($textBlocks);
            $excerpt = $this->resolveExcerpt($textBlocks);
            $storyType = $this->resolveStoryType($title, $textBlocks);
            $postType = $this->resolvePostType($title, $textBlocks, $imageUrls);

            if ($embedded !== null) {
                $title = $embedded['title'] ?? $title;
                $publishedAt = $embedded['published_at'] ?? $publishedAt;
                $excerpt = $embedded['excerpt'] ?? $excerpt;
                $bodyHtml = $embedded['body_html'] ?? $bodyHtml;
                $storyType = $embedded['story_type'] ?? $storyType;
                $postType = $embedded['post_type'] ?? $postType;
            }

            $slug = Str::slug($title ?: basename(parse_url($sourceUrl ?? 'pictime-story', PHP_URL_PATH) ?: 'pictime-story'));

            return [
                'title' => $this->normalizeText($title ?: null),
                'slug' => $slug !== '' ? $slug : 'pictime-story',
                'published_at' => $publishedAt,
                'excerpt' => $excerpt,
                'body_html' => $bodyHtml,
                'source_markup' => $this->extractSourceMarkup($html),
                'image_urls' => $imageUrls,
                'story_type' => $storyType,
                'post_type' => $postType,
            ];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousState);
        }
    }

    private function resolveContentContainer(\DOMXPath $xpath): \DOMNode
    {
        $queries = [
            "//article[1]",
            "//*[contains(@class, 'blog-post')][1]",
            "//*[contains(@class, 'post-content')][1]",
            "//*[contains(@class, 'entry-content')][1]",
            "//main[1]",
            '//body[1]',
        ];

        foreach ($queries as $query) {
            $node = $xpath->query($query)?->item(0);

            if ($node instanceof \DOMNode) {
                return $node;
            }
        }

        throw new \RuntimeException('Pic-Time content container could not be located.');
    }

    private function extractTextBlocks(\DOMNode $container, \DOMDocument $dom): array
    {
        $allowed = ['h2', 'h3', 'p', 'blockquote', 'ul', 'ol'];
        $blocks = [];

        foreach ($container->childNodes as $child) {
            if (! $child instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (! in_array($tag, $allowed, true)) {
                continue;
            }

            $html = trim($dom->saveHTML($child) ?: '');
            $text = $this->normalizeText($child->textContent);

            if ($html === '' || $text === '') {
                continue;
            }

            $blocks[] = [
                'tag' => $tag,
                'html' => $html,
                'text' => $text,
            ];
        }

        if ($blocks !== []) {
            return $blocks;
        }

        $paragraphs = [];
        foreach ($container->getElementsByTagName('p') as $paragraph) {
            $html = trim($dom->saveHTML($paragraph) ?: '');
            $text = $this->normalizeText($paragraph->textContent);

            if ($html === '' || $text === '') {
                continue;
            }

            $paragraphs[] = [
                'tag' => 'p',
                'html' => $html,
                'text' => $text,
            ];
        }

        return $paragraphs;
    }

    private function extractImageUrls(\DOMNode $container, ?string $sourceUrl): array
    {
        $urls = [];

        if ($container instanceof \DOMElement || $container instanceof \DOMDocument) {
            foreach ($container->getElementsByTagName('img') as $image) {
                $src = trim((string) $image->getAttribute('src'));

                if ($src === '' || Str::startsWith($src, ['data:', 'blob:'])) {
                    continue;
                }

                $resolved = $this->resolveUrl($src, $sourceUrl);

                if ($resolved === null || $this->looksDecorative($resolved)) {
                    continue;
                }

                $urls[] = $resolved;
            }
        }

        return array_values(array_unique($urls));
    }

    private function extractMetaImageUrls(\DOMXPath $xpath, ?string $sourceUrl): array
    {
        $queries = [
            "//meta[@property='og:image']/@content",
            "//meta[@name='twitter:image']/@content",
            "//meta[@property='og:image:url']/@content",
        ];

        $urls = [];

        foreach ($queries as $query) {
            foreach ($xpath->query($query) ?? [] as $result) {
                if (! $result instanceof \DOMAttr) {
                    continue;
                }

                $resolved = $this->resolveUrl(trim($result->value), $sourceUrl);

                if ($resolved === null || $this->looksDecorative($resolved)) {
                    continue;
                }

                $urls[] = $resolved;
            }
        }

        return array_values(array_unique($urls));
    }

    private function resolveUrl(string $url, ?string $sourceUrl): ?string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        if ($sourceUrl === null) {
            return null;
        }

        $base = parse_url($sourceUrl);

        if (! is_array($base) || empty($base['scheme']) || empty($base['host'])) {
            return null;
        }

        if (Str::startsWith($url, '//')) {
            return $base['scheme'].':'.$url;
        }

        if (Str::startsWith($url, '/')) {
            return $base['scheme'].'://'.$base['host'].$url;
        }

        $path = $base['path'] ?? '/';
        $directory = Str::beforeLast($path, '/');

        return rtrim($base['scheme'].'://'.$base['host'].$directory, '/').'/'.$url;
    }

    private function looksDecorative(string $url): bool
    {
        $needle = Str::lower($url);

        return Str::contains($needle, ['logo', 'avatar', 'icon', 'favicon', 'spacer']);
    }

    private function firstPicTimeUrl(array $values): ?string
    {
        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            if (preg_match('/https?:\/\/[^\s"\']+\/[^\/\s"\']+\/slideswebcomponentembed\.js\/[a-z0-9]+(?:\?[^\s"\']*)?/i', $value, $matches) === 1) {
                return $this->normalizePicTimeUrl(html_entity_decode($matches[0], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            if (preg_match('/https?:\/\/[^\s"\']*(?:pic-time|pictime)[^\s"\']*/i', $value, $matches) === 1) {
                return $this->normalizePicTimeUrl(html_entity_decode($matches[0], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            if (preg_match('/https?:\/\/gallery\.[^\s"\']+\/[^\s"\']*slideswebcomponentembed\.js\/[^\s"\']+/i', $value, $matches) === 1) {
                return $this->normalizePicTimeUrl(html_entity_decode($matches[0], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }

        return null;
    }

    private function looksLikePicTimeHtml(string $html): bool
    {
        $needle = Str::lower($html);

        return Str::contains($needle, ['pic-time', 'pictime', 'data-pt-type=', 'slideswebcomponentembed.js', 'gallery.']);
    }

    private function looksLikePicTimeEmbedOnlyHtml(string $html): bool
    {
        $needle = Str::lower($html);

        return Str::contains($needle, ['data-pt-type=', 'slideswebcomponentembed.js'])
            && ! Str::contains($needle, ['<article', '<main', '<p', '<img']);
    }

    private function hasEmbeddedSearchRead(string $html): bool
    {
        return preg_match('/searchread_[a-z0-9_]+\s*=\s*`/i', $html) === 1;
    }

    private function normalizePicTimeUrl(string $url): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match('~^https?://gallery\.([a-z0-9.-]+)/([^/?#]+)/slideswebcomponentembed\.js/[a-z0-9]+~i', $url, $matches) === 1) {
            $accountHost = preg_replace('/\.com$/i', '', $matches[1]) ?: $matches[1];
            $slug = $matches[2];

            return 'https://'.$accountHost.'.pic-time.com/'.$slug;
        }

        if (preg_match('~^(https?://[^/]+/[^/?#]+)/slideswebcomponentembed\.js/[a-z0-9]+(?:\?.*)?$~i', $url, $matches) === 1) {
            return $matches[1];
        }

        return $url;
    }

    private function normalizePicTimeEmbedScriptUrl(string $url): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match('~^https?://gallery\.([a-z0-9.-]+)/([^/?#]+)/slideswebcomponentembed\.js/([a-z0-9]+)(\?[^"\']*)?$~i', $url, $matches) === 1) {
            $accountHost = preg_replace('/\.com$/i', '', $matches[1]) ?: $matches[1];
            $slug = $matches[2];
            $slideshowId = $matches[3];
            $query = $matches[4] ?? '';

            return 'https://'.$accountHost.'.pic-time.com/'.$slug.'/slideswebcomponentembed.js/'.$slideshowId.$query;
        }

        return $url;
    }

    private function resolveExcerpt(array $textBlocks): ?string
    {
        $lead = $textBlocks[0]['text'] ?? null;

        if (! $lead) {
            return null;
        }

        return Str::words($lead, 40);
    }

    private function buildBodyHtml(array $textBlocks): ?string
    {
        $html = collect($textBlocks)
            ->map(fn (array $block) => trim($block['html']))
            ->filter()
            ->implode("\n\n");

        return $html !== '' ? $html : null;
    }

    private function resolveStoryType(?string $title, array $textBlocks): string
    {
        $text = Str::lower(trim(($title ?? '').' '.collect($textBlocks)->pluck('text')->implode(' ')));

        return match (true) {
            Str::contains($text, 'engagement') => 'engagement',
            Str::contains($text, 'elopement') => 'elopement',
            Str::contains($text, 'editorial') => 'editorial',
            default => 'wedding',
        };
    }

    private function resolvePostType(?string $title, array $textBlocks, array $imageUrls): string
    {
        $text = Str::lower(trim(($title ?? '').' '.collect($textBlocks)->pluck('text')->implode(' ')));

        return match (true) {
            Str::contains($text, 'engagement') => 'engagement',
            Str::contains($text, ['venue', 'hotel', 'estate']) => 'venue',
            count($imageUrls) >= 6 || Str::contains($text, ['wedding', 'ceremony', 'reception']) => 'real_wedding',
            default => 'advice',
        };
    }

    private function resolveTarget(array $payload, string $target): string
    {
        return match ($target) {
            'journal' => 'journal',
            'weddings' => 'weddings',
            default => in_array($payload['post_type'], ['real_wedding', 'engagement'], true) || count($payload['image_urls']) >= 6
                ? 'weddings'
                : 'journal',
        };
    }

    private function upsertTargetRecord(ImportRun $run, array $loaded, array $payload, string $targetType): JournalPost|WeddingStory
    {
        $mapping = ImportMapping::query()
            ->where('source_table', 'pictime_posts')
            ->where('source_id', $loaded['stable_source_id'])
            ->latest('id')
            ->first();

        if ($targetType === 'weddings') {
            $story = $mapping?->target instanceof WeddingStory
                ? $mapping->target
                : (WeddingStory::query()->where('slug', $payload['slug'])->first()
                    ?? WeddingStory::query()->firstOrNew(['slug' => $this->uniqueStorySlug($payload['slug'], $mapping?->target_id)]));

            $incomingExcerpt = $this->resolveIncomingText($payload['excerpt'] ?? null, $story->excerpt);
            $incomingBody = $this->resolveIncomingBody(
                $payload['body_html'] ?? null,
                $story->body,
                null
            );
            $incomingMarkup = $this->resolveIncomingBody(
                $payload['source_markup'] ?? null,
                $story->source_markup,
                $loaded['context']['embed_html'] ?? null
            );

            $story->fill([
                'title' => $payload['title'] ?? 'Untitled Pic-Time Story',
                'slug' => $this->uniqueStorySlug($payload['slug'], $story->id),
                'status' => 'published',
                'story_type' => $payload['story_type'],
                'headline' => $payload['title'],
                'excerpt' => $incomingExcerpt,
                'body' => $incomingBody,
                'source_markup' => $incomingMarkup,
                'published_at' => $payload['published_at'],
                'canonical_url' => $loaded['source_url'],
            ]);
            $story->save();

            ImportMapping::query()->updateOrCreate(
                [
                    'import_run_id' => $run->id,
                    'source_table' => 'pictime_posts',
                    'source_id' => $loaded['stable_source_id'],
                ],
                [
                    'source_url' => $loaded['source_url'],
                    'target_type' => $story->getMorphClass(),
                    'target_id' => $story->id,
                ]
            );

            return $story;
        }

        $post = $mapping?->target instanceof JournalPost
            ? $mapping->target
            : (JournalPost::query()->where('slug', $payload['slug'])->first()
                ?? JournalPost::query()->firstOrNew(['slug' => $this->uniqueJournalSlug($payload['slug'], $mapping?->target_id)]));

        $incomingExcerpt = $this->resolveIncomingText($payload['excerpt'] ?? null, $post->excerpt);
        $incomingBody = $this->resolveIncomingBody(
            $payload['body_html'] ?? null,
            $post->body,
            null
        );
        $incomingMarkup = $this->resolveIncomingBody(
            $payload['source_markup'] ?? null,
            $post->source_markup,
            $loaded['context']['embed_html'] ?? null
        );

        $post->fill([
            'title' => $payload['title'] ?? 'Untitled Pic-Time Post',
            'slug' => $this->uniqueJournalSlug($payload['slug'], $post->id),
            'status' => 'published',
            'post_type' => $payload['post_type'],
            'excerpt' => $incomingExcerpt,
            'body' => $incomingBody,
            'source_markup' => $incomingMarkup,
            'published_at' => $payload['published_at'],
            'original_wp_url' => $loaded['source_url'],
            'canonical_url' => $loaded['source_url'],
        ]);
        $post->save();

        ImportMapping::query()->updateOrCreate(
            [
                'import_run_id' => $run->id,
                'source_table' => 'pictime_posts',
                'source_id' => $loaded['stable_source_id'],
            ],
            [
                'source_url' => $loaded['source_url'],
                'target_type' => $post->getMorphClass(),
                'target_id' => $post->id,
            ]
        );

        return $post;
    }

    private function resolveIncomingText(?string $incoming, ?string $existing): ?string
    {
        $incoming = is_string($incoming) ? trim($incoming) : null;

        if ($incoming !== null && $incoming !== '') {
            return $incoming;
        }

        $existing = is_string($existing) ? trim($existing) : null;

        return $existing !== '' ? $existing : null;
    }

    private function resolveIncomingBody(?string $incoming, ?string $existing, ?string $embedFallback): ?string
    {
        $incoming = is_string($incoming) ? trim($incoming) : null;

        if ($incoming !== null && $incoming !== '') {
            return $incoming;
        }

        $existing = is_string($existing) ? trim($existing) : null;

        if ($existing !== null && $existing !== '') {
            return $existing;
        }

        $embedFallback = is_string($embedFallback) ? trim($embedFallback) : null;

        return $embedFallback !== '' ? $embedFallback : null;
    }

    private function extractSourceMarkup(string $html): ?string
    {
        if (! PicTimeContent::containsEmbed($html)) {
            return null;
        }

        preg_match_all('/<script\b[^>]*>.*?<\/script>|<template\b[^>]*>.*?<\/template>/is', $html, $matches);

        $parts = collect($matches[0] ?? [])
            ->filter(fn (string $part) => PicTimeContent::containsEmbed($part))
            ->values();

        if ($parts->isNotEmpty()) {
            return $parts->implode('');
        }

        return trim($html) !== '' ? trim($html) : null;
    }

    private function syncMedia(ImportRun $run, JournalPost|WeddingStory $record, array $payload, int $stableSourceId): array
    {
        $imported = 0;
        $reused = 0;
        $mediaIds = [];

        foreach ($payload['image_urls'] as $index => $imageUrl) {
            try {
                $mapping = ImportMapping::query()
                    ->where('source_table', 'pictime_assets')
                    ->where('source_url', $imageUrl)
                    ->latest('id')
                    ->first();

                if ($mapping?->target instanceof Media) {
                    $media = $mapping->target;
                    $reused++;
                } elseif ($existing = $this->existingImportedMedia($imageUrl, $record->slug, $index + 1)) {
                    $media = $existing;
                    $reused++;
                } else {
                    $media = $this->downloadMedia($imageUrl, $record->slug, $index + 1, $stableSourceId);
                    $imported++;
                }

                $mediaIds[$media->id] = [
                    'role' => 'gallery',
                    'sort_order' => $index,
                ];

                ImportMapping::query()->updateOrCreate(
                    [
                        'import_run_id' => $run->id,
                        'source_table' => 'pictime_assets',
                        'source_id' => $this->stableIdFor($imageUrl),
                    ],
                    [
                        'source_url' => $imageUrl,
                        'target_type' => $media->getMorphClass(),
                        'target_id' => $media->id,
                    ]
                );
            } catch (Throwable) {
                continue;
            }
        }

        if ($mediaIds !== []) {
            $record->media()->sync($mediaIds);
            $record->hero_media_id = array_key_first($mediaIds);
            $record->save();
        }

        return ['imported' => $imported, 'reused' => $reused];
    }

    public function hydrateMediaForRecord(JournalPost|WeddingStory $record): array
    {
        $payload = $this->repairPayloadForRecord($record);
        $imageUrls = $payload['image_urls'];

        $this->applyRepairPayloadToRecord($record, $payload);

        if ($imageUrls === []) {
            return ['found' => 0, 'imported' => 0, 'reused' => 0];
        }

        $imported = 0;
        $reused = 0;
        $mediaIds = [];
        $stableSourceId = $this->stableIdFor((string) ($record->canonical_url ?: $record->original_wp_url ?: $record->slug));

        foreach ($imageUrls as $index => $imageUrl) {
            try {
                $mapping = ImportMapping::query()
                    ->where('source_table', 'pictime_assets')
                    ->where('source_url', $imageUrl)
                    ->latest('id')
                    ->first();

                if ($mapping?->target instanceof Media) {
                    $media = $mapping->target;
                    $reused++;
                } elseif ($existing = $this->existingImportedMedia($imageUrl, $record->slug, $index + 1)) {
                    $media = $existing;
                    $reused++;
                } else {
                    $media = $this->downloadMedia($imageUrl, $record->slug, $index + 1, $stableSourceId);
                    $imported++;
                }

                $mediaIds[$media->id] = [
                    'role' => 'gallery',
                    'sort_order' => $index,
                ];
            } catch (Throwable) {
                continue;
            }
        }

        if ($mediaIds !== []) {
            $record->media()->sync($mediaIds);
            $record->hero_media_id = array_key_first($mediaIds);
            $record->save();
        }

        return ['found' => count($imageUrls), 'imported' => $imported, 'reused' => $reused];
    }

    private function repairPayloadForRecord(JournalPost|WeddingStory $record): array
    {
        $manualPath = storage_path('app/private/manual-pictime/'.$record->slug.'.html');
        $sourceMarkup = File::exists($manualPath)
            ? trim((string) File::get($manualPath))
            : trim((string) ($record->source_markup ?? ''));

        if ($sourceMarkup === '' && PicTimeContent::containsEmbed((string) ($record->body ?? ''))) {
            $sourceMarkup = trim((string) $record->body);
        }

        $payload = [
            'source_markup' => $sourceMarkup !== '' ? $sourceMarkup : null,
            'excerpt' => $sourceMarkup !== '' ? PicTimeContent::excerpt($sourceMarkup) : null,
            'body_html' => $sourceMarkup !== '' ? PicTimeContent::bodyHtml($sourceMarkup) : null,
            'published_at' => null,
            'image_urls' => $sourceMarkup !== '' ? $this->extractGalleryImageUrlsFromEmbedMarkup($sourceMarkup) : [],
        ];

        $sourceUrl = $this->repairSourceUrlForRecord($record);

        if (
            $sourceUrl !== null
            && (
                $payload['image_urls'] === []
                || blank($payload['source_markup'])
                || blank($payload['excerpt'])
                || blank($payload['body_html'])
            )
        ) {
            $html = $this->fetchRemoteBody($sourceUrl);

            if ($html !== null) {
                $parsed = $this->parseHtml($html, $sourceUrl);

                if (blank($payload['source_markup']) && filled($parsed['source_markup'] ?? null)) {
                    $payload['source_markup'] = $parsed['source_markup'];
                }

                if (blank($payload['excerpt']) && filled($parsed['excerpt'] ?? null)) {
                    $payload['excerpt'] = $parsed['excerpt'];
                }

                if (blank($payload['body_html']) && filled($parsed['body_html'] ?? null)) {
                    $payload['body_html'] = $parsed['body_html'];
                }

                if ($payload['published_at'] === null && ($parsed['published_at'] ?? null) instanceof Carbon) {
                    $payload['published_at'] = $parsed['published_at'];
                }

                if ($payload['image_urls'] === []) {
                    $payload['image_urls'] = $parsed['image_urls'] ?? [];
                }
            }
        }

        $payload['image_urls'] = collect($payload['image_urls'])
            ->map(fn ($url) => is_string($url) ? trim($url) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $payload;
    }

    private function applyRepairPayloadToRecord(JournalPost|WeddingStory $record, array $payload): void
    {
        $dirty = false;

        if (blank($record->source_markup) && filled($payload['source_markup'] ?? null)) {
            $record->source_markup = $payload['source_markup'];
            $dirty = true;
        }

        if (blank($record->excerpt) && filled($payload['excerpt'] ?? null)) {
            $record->excerpt = $payload['excerpt'];
            $dirty = true;
        }

        if (
            (blank($record->body) || PicTimeContent::containsEmbed((string) ($record->body ?? '')))
            && filled($payload['body_html'] ?? null)
        ) {
            $record->body = $payload['body_html'];
            $dirty = true;
        }

        if ($record->published_at === null && ($payload['published_at'] ?? null) instanceof Carbon) {
            $record->published_at = $payload['published_at'];
            $dirty = true;
        }

        if ($dirty) {
            $record->save();
        }
    }

    private function repairSourceUrlForRecord(JournalPost|WeddingStory $record): ?string
    {
        $sourceUrl = trim((string) ($record->canonical_url ?: $record->original_wp_url ?: ''));

        if ($sourceUrl === '') {
            return null;
        }

        return $this->normalizePicTimeUrl($sourceUrl);
    }

    private function downloadMedia(string $imageUrl, string $slug, int $position, int $stableSourceId): Media
    {
        if ($existing = $this->existingImportedMedia($imageUrl, $slug, $position)) {
            return $existing;
        }

        $filename = pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?: '', PATHINFO_FILENAME);
        $filename = Str::slug($filename ?: "{$slug}-{$position}") ?: "{$slug}-{$position}";
        $assetKey = substr(md5($imageUrl), 0, 8);
        $pathPrefix = "imports/pictime/{$slug}/".str_pad((string) $position, 2, '0', STR_PAD_LEFT)."-{$filename}";

        $temporaryPath = tempnam(sys_get_temp_dir(), 'pictime-');

        if ($temporaryPath === false) {
            throw new \RuntimeException('A temporary file could not be created for media download.');
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders(['User-Agent' => 'DonaldSextonImporter/1.0'])
                ->withOptions(['sink' => $temporaryPath])
                ->get($imageUrl);

            $response->throw();

            $extension = $this->resolveExtension($imageUrl, $response);
            $path = "{$pathPrefix}-{$assetKey}.{$extension}";

            $existing = Media::query()
                ->where('disk', 'public')
                ->where('path', $path)
                ->first();

            if ($existing instanceof Media) {
                return $existing;
            }

            $stream = fopen($temporaryPath, 'rb');

            if ($stream === false) {
                throw new \RuntimeException('The downloaded media file could not be opened.');
            }

            try {
                Storage::disk('public')->writeStream($path, $stream);
            } finally {
                fclose($stream);
            }

            return Media::query()->create([
                'disk' => 'public',
                'path' => $path,
                'filename' => basename($path),
                'mime_type' => $response->header('Content-Type'),
                'caption' => null,
                'credit' => 'Pic-Time import',
                'original_wp_attachment_id' => null,
            ]);
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function existingImportedMedia(string $imageUrl, string $slug, int $position): ?Media
    {
        $filename = pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?: '', PATHINFO_FILENAME);
        $filename = Str::slug($filename ?: "{$slug}-{$position}") ?: "{$slug}-{$position}";
        $assetKey = substr(md5($imageUrl), 0, 8);
        $pathPrefix = "imports/pictime/{$slug}/".str_pad((string) $position, 2, '0', STR_PAD_LEFT)."-{$filename}";

        return Media::query()
            ->where('disk', 'public')
            ->where(function ($query) use ($pathPrefix, $assetKey) {
                $query->where('path', 'like', "{$pathPrefix}%")
                    ->orWhere('path', 'like', "%{$assetKey}%");
            })
            ->first();
    }

    private function resolveExtension(string $imageUrl, Response $response): string
    {
        $pathExtension = strtolower(pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        if ($pathExtension !== '') {
            return $pathExtension;
        }

        return match (Str::before((string) $response->header('Content-Type'), ';')) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };
    }

    private function firstContent(\DOMXPath $xpath, array $queries): ?string
    {
        foreach ($queries as $query) {
            $result = $xpath->query($query)?->item(0);

            if (! $result instanceof \DOMNode) {
                continue;
            }

            $content = $result instanceof \DOMAttr ? $result->value : $result->textContent;
            $content = $this->normalizeText($content);

            if ($content !== null && $content !== '') {
                return $content;
            }
        }

        return null;
    }

    private function resolvePublishedAt(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function extractProjectDate(string $html): ?Carbon
    {
        if (preg_match('/"projectDate":"([^"]+)"/', $html, $matches) !== 1) {
            return null;
        }

        return $this->resolvePublishedAt($matches[1]);
    }

    private function extractEmbeddedSearchReadPayload(string $html): ?array
    {
        if (preg_match('/searchread_[a-z0-9_]+\s*=\s*`(?P<content>.*?)`;/is', $html, $matches) !== 1) {
            return null;
        }

        $content = html_entity_decode((string) ($matches['content'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = trim($content);

        if ($content === '') {
            return null;
        }

        $rawLines = collect(explode("\n", $content))
            ->map(fn ($line) => rtrim((string) $line))
            ->values();

        $headerLines = $rawLines
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values();

        if ($headerLines->count() < 2) {
            return null;
        }

        $label = $this->normalizeText($headerLines->get(0));
        $title = $this->normalizeText($headerLines->get(1));
        $publishedAt = $this->resolvePublishedAt($headerLines->get(2));
        $bodySource = $content;
        $headerLineCount = $publishedAt ? 3 : 2;

        for ($i = 0; $i < $headerLineCount; $i++) {
            $bodySource = preg_replace('/^[^\n]*\n?/', '', $bodySource, 1) ?? $bodySource;
        }

        $bodySource = ltrim($bodySource);
        $paragraphs = collect(preg_split("/\n\s*\n/", $bodySource) ?: [])
            ->map(fn ($paragraph) => $this->normalizeText($paragraph))
            ->filter()
            ->reject(fn ($paragraph) => Str::lower($paragraph) === 'view full gallery')
            ->values();

        $bodyHtml = $paragraphs
            ->map(function (string $paragraph): string {
                if (preg_match('/^[A-Za-z][A-Za-z\s]+:?$/', $paragraph) === 1 && str_word_count($paragraph) <= 4) {
                    return '<h3>'.e(rtrim($paragraph, ':')).'</h3>';
                }

                return '<p>'.e($paragraph).'</p>';
            })
            ->implode("\n\n");

        $excerpt = $paragraphs
            ->first(fn ($paragraph) => str_word_count($paragraph) >= 12 && ! Str::contains(Str::lower($paragraph), ['photographer', 'location:']));

        $postType = match (Str::slug((string) $label)) {
            'engagement' => 'engagement',
            'venue' => 'venue',
            'real-wedding', 'real-weddings', 'wedding' => 'real_wedding',
            default => 'advice',
        };

        return [
            'title' => $title,
            'published_at' => $publishedAt,
            'excerpt' => $excerpt ? Str::words($excerpt, 40) : null,
            'body_html' => $bodyHtml !== '' ? $bodyHtml : null,
            'story_type' => $postType === 'engagement' ? 'engagement' : 'wedding',
            'post_type' => $postType,
        ];
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = preg_replace('/\s+/u', ' ', $decoded) ?? $decoded;
        $decoded = trim($decoded);

        if ($decoded === '') {
            return null;
        }

        $decoded = preg_replace('/\s+[|\-–]\s+Pic-Time$/i', '', $decoded) ?? $decoded;
        $decoded = preg_replace('/\s*-\s*(Wedding|Engagement|Elopement|Proposal)\b/u', ' - $1', $decoded) ?? $decoded;

        return trim($decoded);
    }

    private function stableIdFor(string $value): int
    {
        return (int) sprintf('%u', crc32($value));
    }

    private function extractGalleryImageUrlsFromEmbedMarkup(string $markup): array
    {
        preg_match_all('/https:\/\/[^\s"\',]+\/images\/[^\s"\',]+/i', $markup, $matches);
        $urls = collect($matches[0] ?? [])
            ->map(fn (string $url) => html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ->filter(fn (string $url) => Str::contains($url, '/slideshows/'))
            ->reject(fn (string $url) => Str::contains($url, '/images/_pt('))
            ->unique()
            ->values()
            ->all();

        if ($urls !== []) {
            return $urls;
        }

        if (preg_match('/<script\b[^>]*\bsrc=["\']([^"\']+slideswebcomponentembed\.js[^"\']*)["\']/i', $markup, $scriptMatch) !== 1) {
            return [];
        }

        $scriptUrl = $this->normalizePicTimeEmbedScriptUrl($scriptMatch[1]);

        $body = $this->fetchRemoteBody($scriptUrl);

        if ($body === null) {
            return [];
        }

        preg_match_all('/https:\/\/[^\s"\',]+\/images\/[^\s"\',]+/i', $body, $remoteMatches);

        return collect($remoteMatches[0] ?? [])
            ->map(fn (string $url) => html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ->filter(fn (string $url) => Str::contains($url, '/slideshows/'))
            ->reject(fn (string $url) => Str::contains($url, '/images/_pt('))
            ->unique()
            ->values()
            ->all();
    }

    private function fetchRemoteBody(string $url): ?string
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders(['User-Agent' => 'DonaldSextonImporter/1.0'])
                ->get($url);

            $response->throw();

            return $response->body();
        } catch (Throwable) {
            try {
                $process = new Process([
                    'curl',
                    '-sL',
                    '-A',
                    'Mozilla/5.0',
                    $url,
                ]);
                $process->setTimeout(60);
                $process->run();

                if (! $process->isSuccessful()) {
                    return null;
                }

                $output = trim($process->getOutput());

                return $output !== '' ? $output : null;
            } catch (Throwable) {
                return null;
            }
        }
    }

    private function uniqueStorySlug(string $slug, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug) ?: 'pictime-story';
        $candidate = $base;
        $suffix = 2;

        while (
            WeddingStory::query()
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function uniqueJournalSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug) ?: 'pictime-post';
        $candidate = $base;
        $suffix = 2;

        while (
            JournalPost::query()
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
