<?php

namespace App\Services\WordPress;

use App\Models\Category;
use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\JournalPost;
use App\Models\Media;
use App\Models\Redirect;
use App\Models\Tag;
use App\Models\WeddingStory;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class WordPressJournalImporter
{
    public function __construct(
        private readonly RealWeddingPromoter $realWeddingPromoter,
        private readonly WordPressPostClassifier $postClassifier,
    ) {
    }

    public function import(UploadedFile $file): ImportRun
    {
        return $this->importFromPath($file->getRealPath());
    }

    public function importFromPath(string $path): ImportRun
    {
        $run = ImportRun::create([
            'source_type' => 'wordpress',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $summary = DB::transaction(function () use ($path, $run): array {
                $xml = $this->loadXml($path);
                $namespaces = $xml->getNamespaces(true);
                $items = $xml->channel?->item ?? [];

                $summary = [
                    'posts_seen' => 0,
                    'posts_imported' => 0,
                    'posts_updated' => 0,
                    'posts_skipped' => 0,
                    'categories_synced' => 0,
                    'tags_synced' => 0,
                    'featured_images_imported' => 0,
                    'featured_images_reused' => 0,
                    'body_images_imported' => 0,
                    'body_images_reused' => 0,
                    'redirects_synced' => 0,
                ];
                $attachmentsByParent = $this->attachmentMapFromItems($items, $namespaces);
                $featuredAttachmentIdsByParent = $this->featuredAttachmentMapFromItems($items, $namespaces);

                foreach ($items as $item) {
                    $wp = $item->children($namespaces['wp'] ?? null);

                    if ((string) $wp->post_type !== 'post') {
                        continue;
                    }

                    $summary['posts_seen']++;

                    $wpStatus = (string) $wp->status;
                    if (in_array($wpStatus, ['auto-draft', 'trash', 'inherit'], true)) {
                        $summary['posts_skipped']++;
                        continue;
                    }

                    $postId = (int) $wp->post_id;
                    $title = $this->normalizeText((string) $item->title) ?: 'Untitled Post';
                    $content = $this->normalizeBody((string) $item->children($namespaces['content'] ?? null)->encoded);
                    $excerpt = $this->normalizeExcerpt((string) $item->children($namespaces['excerpt'] ?? null)->encoded, $content);
                    $slug = trim((string) $wp->post_name) ?: Str::slug($title);
                    $publishedAt = $this->resolvePublishedAt((string) $wp->post_date_gmt, (string) $wp->post_date, (string) $item->pubDate);
                    $status = $wpStatus === 'publish' ? 'published' : 'draft';
                    $originalUrl = trim((string) $item->link) ?: null;

                    $post = JournalPost::query()->firstOrNew([
                        'original_wp_post_id' => $postId ?: null,
                    ]);

                    $wasExisting = $post->exists;
                    $resolvedPostType = $this->resolvePostType($item);

                    $post->fill([
                        'title' => $title,
                        'slug' => $this->uniqueSlug($slug, $post->id),
                        'status' => $status,
                        'post_type' => $resolvedPostType,
                        'excerpt' => $excerpt,
                        'body' => $content,
                        'author_name' => $this->normalizeText((string) $item->children($namespaces['dc'] ?? null)->creator) ?: null,
                        'published_at' => $publishedAt,
                        'original_wp_post_id' => $postId ?: null,
                        'original_wp_url' => $originalUrl,
                    ]);
                    $post->save();

                    $taxonomySummary = $this->syncTaxonomies($post, $item);
                    $summary['categories_synced'] += $taxonomySummary['categories'];
                    $summary['tags_synced'] += $taxonomySummary['tags'];
                    $featuredImageSummary = $this->syncFeaturedMedia(
                        $run,
                        $post,
                        $attachmentsByParent[$postId] ?? [],
                        $featuredAttachmentIdsByParent[$postId] ?? null,
                    );
                    $summary['featured_images_imported'] += $featuredImageSummary['imported'];
                    $summary['featured_images_reused'] += $featuredImageSummary['reused'];
                    $bodyImageSummary = $this->repairLegacyMediaForRecord($post);
                    $summary['body_images_imported'] += $bodyImageSummary['imported'];
                    $summary['body_images_reused'] += $bodyImageSummary['reused'];
                    $post->refresh();

                    if ($resolvedPostType === 'real_wedding') {
                        $this->realWeddingPromoter->promote($post->fresh(['tags', 'media']));
                        $summary['redirects_synced']++;
                    } elseif ($originalUrl && $this->syncRedirect($originalUrl, route('journal.show', $post->slug))) {
                        $summary['redirects_synced']++;
                    }

                    ImportMapping::query()->updateOrCreate(
                        [
                            'import_run_id' => $run->id,
                            'source_table' => 'wp_posts',
                            'source_id' => $postId,
                        ],
                        [
                            'source_url' => $originalUrl,
                            'target_type' => $post->getMorphClass(),
                            'target_id' => $post->id,
                        ]
                    );

                    if ($wasExisting) {
                        $summary['posts_updated']++;
                    } else {
                        $summary['posts_imported']++;
                    }
                }

                return $summary;
            });

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'summary_json' => $summary,
                'error_log' => null,
            ]);
        } catch (Throwable $throwable) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_log' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }

        return $run->fresh();
    }

    public function inspectLegacyMediaForRecord(JournalPost|WeddingStory $record): array
    {
        $urls = $this->extractLegacyUploadUrls((string) ($record->body ?? ''));
        $present = 0;
        $missing = [];

        foreach ($urls as $url) {
            $relativePath = $this->legacyUploadRelativePath($url);

            if ($relativePath !== null && File::exists(public_path($relativePath))) {
                $present++;
                continue;
            }

            $missing[] = $url;
        }

        return [
            'found' => count($urls),
            'present' => $present,
            'missing' => count($missing),
            'missing_urls' => $missing,
        ];
    }

    public function repairLegacyMediaForRecord(JournalPost|WeddingStory $record, ?string $sourceDir = null): array
    {
        $urls = $this->extractLegacyUploadUrls((string) ($record->body ?? ''));

        if ($urls === []) {
            return ['found' => 0, 'imported' => 0, 'reused' => 0, 'failed' => 0];
        }

        $imported = 0;
        $reused = 0;
        $failed = 0;

        foreach ($urls as $url) {
            $relativePath = $this->legacyUploadRelativePath($url);

            if ($relativePath === null) {
                $failed++;
                continue;
            }

            if (File::exists(public_path($relativePath))) {
                $reused++;
                continue;
            }

            try {
                $this->downloadLegacyUpload($url, $relativePath, $sourceDir);
                $imported++;
            } catch (Throwable) {
                $failed++;
            }
        }

        return ['found' => count($urls), 'imported' => $imported, 'reused' => $reused, 'failed' => $failed];
    }

    private function loadXml(string $path): \SimpleXMLElement
    {
        $contents = file_get_contents($path);
        $xml = simplexml_load_string($contents ?: '', \SimpleXMLElement::class, LIBXML_NOCDATA);

        if (! $xml instanceof \SimpleXMLElement) {
            throw new \RuntimeException('The WordPress export file could not be parsed.');
        }

        return $xml;
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

    private function featuredAttachmentMapFromItems(iterable $items, array $namespaces): array
    {
        $featuredAttachmentIds = [];

        foreach ($items as $item) {
            $wp = $item->children($namespaces['wp'] ?? null);

            if ((string) $wp->post_type !== 'post') {
                continue;
            }

            $postId = (int) $wp->post_id;

            if ($postId === 0) {
                continue;
            }

            foreach ($wp->postmeta ?? [] as $postMeta) {
                $meta = $postMeta->children($namespaces['wp'] ?? null);
                $metaKey = trim((string) ($meta->meta_key ?: $postMeta->meta_key));

                if ($metaKey !== '_thumbnail_id') {
                    continue;
                }

                $metaValue = (int) trim((string) ($meta->meta_value ?: $postMeta->meta_value));

                if ($metaValue > 0) {
                    $featuredAttachmentIds[$postId] = $metaValue;
                }

                break;
            }
        }

        return $featuredAttachmentIds;
    }

    private function resolvePublishedAt(string $gmt, string $local, string $pubDate): ?Carbon
    {
        foreach ([$gmt, $local, $pubDate] as $candidate) {
            if (trim($candidate) === '') {
                continue;
            }

            try {
                return Carbon::parse($candidate);
            } catch (Throwable) {
            }
        }

        return null;
    }

    private function resolvePostType(\SimpleXMLElement $item): string
    {
        return $this->postClassifier->classifyImportItem($item);
    }

    private function syncTaxonomies(JournalPost $post, \SimpleXMLElement $item): array
    {
        $categoryIds = [];
        $tagIds = [];

        foreach ($item->category ?? [] as $term) {
            $domain = (string) $term['domain'];
            $name = $this->normalizeText((string) $term);
            $slug = trim((string) $term['nicename']) ?: Str::slug($name);

            if ($name === '' || $slug === '') {
                continue;
            }

            if ($domain === 'category') {
                $category = Category::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $name]
                );

                $categoryIds[] = $category->id;
            }

            if ($domain === 'post_tag') {
                $tag = Tag::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $name]
                );

                $tagIds[] = $tag->id;
            }
        }

        $post->categories()->sync(array_values(array_unique($categoryIds)));
        $post->tags()->sync(array_values(array_unique($tagIds)));

        return [
            'categories' => count(array_unique($categoryIds)),
            'tags' => count(array_unique($tagIds)),
        ];
    }

    private function syncFeaturedMedia(ImportRun $run, JournalPost $post, array $attachments, ?int $featuredAttachmentId): array
    {
        $attachment = $this->resolveFeaturedAttachment($attachments, $featuredAttachmentId);

        if ($attachment === null) {
            return ['imported' => 0, 'reused' => 0];
        }

        $mapping = ImportMapping::query()
            ->where('source_table', 'wp_attachments')
            ->where('source_id', $attachment['id'])
            ->latest('id')
            ->first();

        if ($mapping?->target instanceof Media) {
            $media = $mapping->target;
            $imported = 0;
            $reused = 1;
        } elseif ($existing = $this->existingImportedAttachmentMedia($attachment)) {
            $media = $existing;
            $imported = 0;
            $reused = 1;
        } else {
            $media = $this->downloadAttachmentMedia($attachment, $post->slug);
            $imported = 1;
            $reused = 0;
        }

        $post->media()->syncWithoutDetaching([
            $media->id => [
                'role' => 'hero',
                'sort_order' => 0,
            ],
        ]);
        $post->hero_media_id = $media->id;
        $post->save();

        ImportMapping::query()->updateOrCreate(
            [
                'import_run_id' => $run->id,
                'source_table' => 'wp_attachments',
                'source_id' => $attachment['id'],
            ],
            [
                'source_url' => $attachment['url'],
                'target_type' => $media->getMorphClass(),
                'target_id' => $media->id,
            ]
        );

        return ['imported' => $imported, 'reused' => $reused];
    }

    private function resolveFeaturedAttachment(array $attachments, ?int $featuredAttachmentId): ?array
    {
        $attachments = collect($attachments)
            ->filter(fn (array $attachment) => filled($attachment['url'] ?? null))
            ->values();

        if ($attachments->isEmpty()) {
            return null;
        }

        if ($featuredAttachmentId) {
            $matchedAttachment = $attachments->firstWhere('id', $featuredAttachmentId);

            if (is_array($matchedAttachment)) {
                return $matchedAttachment;
            }
        }

        return $attachments->first();
    }

    private function existingImportedAttachmentMedia(array $attachment): ?Media
    {
        return Media::query()
            ->where('original_wp_attachment_id', $attachment['id'] ?? null)
            ->orWhere(function ($query) use ($attachment) {
                $query->where('disk', 'public')
                    ->where('path', 'like', '%'.substr(md5((string) ($attachment['url'] ?? '')), 0, 8).'%');
            })
            ->first();
    }

    private function downloadAttachmentMedia(array $attachment, string $slug): Media
    {
        $imageUrl = trim((string) ($attachment['url'] ?? ''));

        if ($imageUrl === '') {
            throw new \RuntimeException('The WordPress attachment URL is missing.');
        }

        $response = Http::timeout(60)
            ->withHeaders(['User-Agent' => 'DonaldSextonImporter/1.0'])
            ->get($imageUrl);

        $response->throw();

        $filename = pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?: '', PATHINFO_FILENAME);
        $filename = Str::slug($filename ?: "{$slug}-featured") ?: "{$slug}-featured";
        $assetKey = substr(md5($imageUrl), 0, 8);
        $extension = $this->resolveExtension($imageUrl, $response);
        $path = "imports/wordpress/{$slug}/featured-{$filename}-{$assetKey}.{$extension}";

        Storage::disk('public')->put($path, $response->body());

        return Media::query()->create([
            'disk' => 'public',
            'path' => $path,
            'filename' => basename($path),
            'mime_type' => $response->header('Content-Type'),
            'caption' => filled($attachment['title'] ?? null) ? $attachment['title'] : null,
            'credit' => 'WordPress import',
            'original_wp_attachment_id' => $attachment['id'] ?? null,
        ]);
    }

    private function syncRedirect(string $sourceUrl, string $targetUrl): bool
    {
        $fromPath = parse_url($sourceUrl, PHP_URL_PATH);
        $toPath = parse_url($targetUrl, PHP_URL_PATH);

        if (! is_string($fromPath) || ! is_string($toPath) || $fromPath === '' || $fromPath === $toPath) {
            return false;
        }

        Redirect::query()->updateOrCreate(
            ['from_path' => '/'.ltrim($fromPath, '/')],
            [
                'to_path' => '/'.ltrim($toPath, '/'),
                'status_code' => 301,
                'source' => 'wp_import',
            ]
        );

        return true;
    }

    private function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug) ?: 'journal-post';
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

    private function normalizeText(string $value): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $decoded) ?? '');
    }

    private function normalizeExcerpt(string $excerpt, string $content): string
    {
        $excerpt = $this->extractPlainText($excerpt);

        if ($excerpt !== '') {
            return $excerpt;
        }

        return Str::words($this->extractPlainText($content), 40);
    }

    private function normalizeBody(string $content): string
    {
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace('/<!--\s*\/?wp:[^>]*-->/i', '', $content) ?? $content;
        $content = preg_replace('/\s+class="wp-block-gallery[^"]*"/i', ' class="imported-gallery"', $content) ?? $content;
        $content = preg_replace('/\s+class="wp-block-image([^"]*)"/i', ' class="imported-image$1"', $content) ?? $content;
        $content = preg_replace('/(<\/figure>)\s*(<figure\b)/i', "$1\n$2", $content) ?? $content;

        return trim($content);
    }

    private function extractLegacyUploadUrls(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        preg_match_all('~(?:https?:)?//[^"\'\s>]+/wp-content/uploads/[^"\'\s>]+|/wp-content/uploads/[^"\'\s>]+~i', $html, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $url) => $this->normalizeLegacyUploadUrl($url))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeLegacyUploadUrl(string $url): ?string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($url === '' || ! str_contains(Str::lower($url), '/wp-content/uploads/')) {
            return null;
        }

        if (Str::startsWith($url, '//')) {
            return 'https:'.$url;
        }

        if (Str::startsWith($url, '/wp-content/uploads/')) {
            return 'https://donaldsextonphotography.com'.$url;
        }

        return $url;
    }

    private function legacyUploadRelativePath(string $url): ?string
    {
        $url = $this->normalizeLegacyUploadUrl($url);

        if ($url === null) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! str_contains(Str::lower($path), '/wp-content/uploads/')) {
            return null;
        }

        return ltrim($path, '/');
    }

    private function downloadLegacyUpload(string $url, string $relativePath, ?string $sourceDir = null): void
    {
        $absolutePath = public_path($relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));

        $sourceDir = is_string($sourceDir) ? rtrim($sourceDir, '/') : null;
        $uploadsSuffix = preg_replace('~^wp-content/uploads/?~i', '', $relativePath) ?? $relativePath;

        if ($sourceDir) {
            $candidate = $sourceDir.'/'.$uploadsSuffix;

            if (File::exists($candidate)) {
                File::copy($candidate, $absolutePath);

                return;
            }

            $candidate = $sourceDir.'/wp-content/uploads/'.$uploadsSuffix;

            if (File::exists($candidate)) {
                File::copy($candidate, $absolutePath);

                return;
            }
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'wp-upload-');

        if ($temporaryPath === false) {
            throw new \RuntimeException('A temporary file could not be created for legacy media download.');
        }

        try {
            try {
                $response = Http::timeout(60)
                    ->withHeaders(['User-Agent' => 'DonaldSextonImporter/1.0'])
                    ->withOptions(['sink' => $temporaryPath])
                    ->get($url);

                $response->throw();
            } catch (Throwable) {
                $process = new Process([
                    'curl',
                    '-fSL',
                    '-A',
                    'Mozilla/5.0',
                    $url,
                    '-o',
                    $temporaryPath,
                ]);
                $process->setTimeout(60);
                $process->mustRun();
            }

            File::copy($temporaryPath, $absolutePath);
        } finally {
            @unlink($temporaryPath);
        }
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

    private function extractPlainText(string $html): string
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/<\s*br\s*\/?>/i', ' ', $html) ?? $html;
        $html = preg_replace('/<\/p>/i', "</p>\n", $html) ?? $html;

        return $this->normalizeText(strip_tags($html));
    }
}
