<?php

use App\Models\Page;
use App\Models\Redirect;
use App\Models\User;
use App\Models\Media;
use App\Models\ImportMapping;
use App\Models\JournalPost;
use App\Models\WeddingStory;
use App\Services\Media\MediaDuplicateAuditor;
use App\Services\Media\MediaOptimizer;
use App\Services\PicTime\PicTimeImporter;
use App\Services\WordPress\WordPressJournalImporter;
use App\Services\WordPress\WordPressPostClassifier;
use App\Services\WordPress\RealWeddingPromoter;
use App\Support\PicTimeContent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:user {email} {--name=} {--password=}', function () {
    $email = (string) $this->argument('email');
    $name = (string) ($this->option('name') ?: Str::before($email, '@') ?: 'Admin');
    $password = (string) ($this->option('password') ?: Str::password(16));

    $user = User::query()->updateOrCreate(
        ['email' => $email],
        [
            'name' => $name,
            'password' => Hash::make($password),
        ]
    );

    $this->components->info("Admin user ready: {$user->email}");
    $this->line("Password: {$password}");

    return 0;
})->purpose('Create or update a user account for the admin CMS');

Artisan::command('media:audit-duplicates {--disk=public} {--path-prefix=} {--limit=0} {--report=}', function (MediaDuplicateAuditor $auditor) {
    $disk = (string) $this->option('disk');
    $pathPrefix = trim((string) $this->option('path-prefix'));
    $limit = max(0, (int) $this->option('limit'));

    $query = Media::query()
        ->where('disk', $disk)
        ->withCount(['pages', 'weddingStories', 'journalPosts', 'venues'])
        ->orderBy('id');

    if ($pathPrefix !== '') {
        $query->where('path', 'like', rtrim($pathPrefix, '/').'%');
    }

    if ($limit > 0) {
        $query->limit($limit);
    }

    $mediaItems = $query->get();
    $report = $auditor->audit($mediaItems);
    $summary = $report['summary'];
    $reportPath = trim((string) $this->option('report'));

    if ($reportPath === '') {
        $reportPath = storage_path('app/private/reports/media-duplicates-'.now()->format('Ymd-His').'.json');
    } elseif (! Str::startsWith($reportPath, ['/'])) {
        $reportPath = base_path($reportPath);
    }

    File::ensureDirectoryExists(dirname($reportPath));
    File::put($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $this->components->info('Media duplicate audit complete.');
    $this->line("- files seen: {$summary['files_seen']}");
    $this->line("- files missing: {$summary['files_missing']}");
    $this->line("- duplicate groups: {$summary['duplicate_groups']}");
    $this->line("- duplicate files: {$summary['duplicate_files']}");
    $this->line('- reclaimable bytes: '.$summary['reclaimable_bytes']);
    $this->line("- report: {$reportPath}");

    foreach (array_slice($report['groups'], 0, 10) as $group) {
        $ids = collect($group['items'])->pluck('id')->implode(', ');
        $samplePath = $group['items'][0]['path'] ?? 'unknown';
        $this->line('- group sha256 '.substr((string) $group['sha256'], 0, 12).'…: '
            .count($group['items']).' files, '.$group['bytes'].' bytes each, ids ['.$ids.'], sample '.$samplePath);
    }

    return 0;
})->purpose('Audit exact duplicate media files and write a JSON report');

Artisan::command('media:optimize {--disk=public} {--path-prefix=} {--from-id=0} {--to-id=0} {--limit=0} {--max-width=1600} {--jpeg-quality=82} {--webp-quality=80} {--min-bytes=250000} {--generate-webp} {--only-missing-webp} {--dry-run} {--summary-only} {--report=}', function (MediaOptimizer $optimizer) {
    $disk = (string) $this->option('disk');
    $pathPrefix = trim((string) $this->option('path-prefix'));
    $fromId = max(0, (int) $this->option('from-id'));
    $toId = max(0, (int) $this->option('to-id'));
    $limit = max(0, (int) $this->option('limit'));
    $summaryOnly = (bool) $this->option('summary-only');
    $reportPath = trim((string) $this->option('report'));
    $options = [
        'max_width' => (int) $this->option('max-width'),
        'jpeg_quality' => (int) $this->option('jpeg-quality'),
        'webp_quality' => (int) $this->option('webp-quality'),
        'min_bytes' => (int) $this->option('min-bytes'),
        'generate_webp' => (bool) $this->option('generate-webp'),
        'only_missing_webp' => (bool) $this->option('only-missing-webp'),
        'dry_run' => (bool) $this->option('dry-run'),
    ];

    $query = Media::query()
        ->where('disk', $disk)
        ->orderBy('id');

    if ($pathPrefix !== '') {
        $query->where('path', 'like', rtrim($pathPrefix, '/').'%');
    }

    if ($fromId > 0) {
        $query->where('id', '>=', $fromId);
    }

    if ($toId > 0) {
        $query->where('id', '<=', $toId);
    }

    if ($limit > 0) {
        $query->limit($limit);
    }

    $mediaItems = $query->get();

    $summary = [
        'files_seen' => 0,
        'optimized' => 0,
        'skipped' => 0,
        'errors' => 0,
        'resized' => 0,
        'webp_created' => 0,
        'webp_updated' => 0,
        'bytes_saved' => 0,
        'source_bytes_before' => 0,
        'source_bytes_after' => 0,
        'webp_bytes_before' => 0,
        'webp_bytes_after' => 0,
        'net_storage_delta' => 0,
    ];

    foreach ($mediaItems as $media) {
        $summary['files_seen']++;
        $storage = Storage::disk($media->disk ?: $disk);
        $sourceBytesBefore = $storage->exists($media->path) ? max(0, (int) $storage->size($media->path)) : 0;
        $webpPath = $media->webpPath();
        $webpBytesBefore = $webpPath !== null && $storage->exists($webpPath)
            ? max(0, (int) $storage->size($webpPath))
            : 0;

        $summary['source_bytes_before'] += $sourceBytesBefore;
        $summary['webp_bytes_before'] += $webpBytesBefore;

        try {
            $result = $optimizer->optimize($media, $options);
        } catch (Throwable $throwable) {
            $summary['errors']++;
            $summary['source_bytes_after'] += $sourceBytesBefore;
            $summary['webp_bytes_after'] += $webpBytesBefore;

            if (! $summaryOnly) {
                $this->line("- {$media->id}: error {$throwable->getMessage()}");
            }

            continue;
        }

        $sourceBytesAfter = ($result['status'] ?? 'skipped') === 'optimized'
            ? max(0, (int) ($result['optimized_bytes'] ?? $sourceBytesBefore))
            : $sourceBytesBefore;
        $webpBytesAfter = (($result['webp_created'] ?? false) || ($result['webp_updated'] ?? false))
            ? max(0, (int) ($result['webp_bytes'] ?? $webpBytesBefore))
            : $webpBytesBefore;

        $summary['source_bytes_after'] += $sourceBytesAfter;
        $summary['webp_bytes_after'] += $webpBytesAfter;
        $summary['net_storage_delta'] += ($sourceBytesAfter + $webpBytesAfter) - ($sourceBytesBefore + $webpBytesBefore);

        if (($result['status'] ?? 'skipped') === 'optimized') {
            $summary['optimized']++;
            $summary['resized'] += (int) ($result['resized'] ?? false);
            $summary['webp_created'] += (int) ($result['webp_created'] ?? false);
            $summary['webp_updated'] += (int) ($result['webp_updated'] ?? false);
            $summary['bytes_saved'] += (int) ($result['bytes_saved'] ?? 0);

            if (! $summaryOnly) {
                $this->line("- {$media->id}: optimized {$media->path} (saved ".((int) ($result['bytes_saved'] ?? 0)).' bytes)');
            }
        } else {
            $summary['skipped']++;
        }
    }

    if ($reportPath !== '') {
        if (! Str::startsWith($reportPath, ['/'])) {
            $reportPath = base_path($reportPath);
        }

        File::ensureDirectoryExists(dirname($reportPath));
        File::put($reportPath, json_encode([
            'generated_at' => now()->toIso8601String(),
            'options' => [
                'disk' => $disk,
                'path_prefix' => $pathPrefix,
                'from_id' => $fromId,
                'to_id' => $toId,
                'limit' => $limit,
                ...$options,
            ],
            'summary' => $summary,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    $label = $options['dry_run'] ? 'Media optimization dry run complete.' : 'Media optimization complete.';
    $this->components->info($label);
    $this->line("- files seen: {$summary['files_seen']}");
    $this->line("- optimized: {$summary['optimized']}");
    $this->line("- skipped: {$summary['skipped']}");
    $this->line("- errors: {$summary['errors']}");
    $this->line("- resized: {$summary['resized']}");
    $this->line("- webp created: {$summary['webp_created']}");
    $this->line("- webp updated: {$summary['webp_updated']}");
    $this->line("- bytes saved: {$summary['bytes_saved']}");
    $this->line("- source bytes before: {$summary['source_bytes_before']}");
    $this->line("- source bytes after: {$summary['source_bytes_after']}");
    $this->line("- webp bytes before: {$summary['webp_bytes_before']}");
    $this->line("- webp bytes after: {$summary['webp_bytes_after']}");
    $this->line("- net storage delta: {$summary['net_storage_delta']}");

    if ($reportPath !== '') {
        $this->line("- report: {$reportPath}");
    }

    return $summary['errors'] === 0 ? 0 : 1;
})->purpose('Resize and recompress media files, with optional WebP generation');

Artisan::command('wordpress:import {path}', function (WordPressJournalImporter $importer) {
    $path = (string) $this->argument('path');

    if (! File::exists($path)) {
        $this->components->error("File not found: {$path}");

        return 1;
    }

    $this->components->info("Importing legacy XML from {$path}");

    $run = $importer->importFromPath($path);

    $this->components->info("Import run {$run->id} completed with status `{$run->status}`.");

    if ($run->summary_json) {
        foreach ($run->summary_json as $key => $value) {
            $this->line("- ".str_replace('_', ' ', $key).": {$value}");
        }
    }

    if ($run->error_log) {
        $this->newLine();
        $this->components->warn($run->error_log);
    }

    return $run->status === 'completed' ? 0 : 1;
})->purpose('Import a legacy blog XML export from a local file path');

Artisan::command('legacy:import {path}', function (WordPressJournalImporter $importer) {
    $path = (string) $this->argument('path');

    if (! File::exists($path)) {
        $this->components->error("File not found: {$path}");

        return 1;
    }

    $this->components->info("Importing legacy XML from {$path}");

    $run = $importer->importFromPath($path);

    $this->components->info("Import run {$run->id} completed with status `{$run->status}`.");

    if ($run->summary_json) {
        foreach ($run->summary_json as $key => $value) {
            $this->line("- ".str_replace('_', ' ', $key).": {$value}");
        }
    }

    if ($run->error_log) {
        $this->newLine();
        $this->components->warn($run->error_log);
    }

    return $run->status === 'completed' ? 0 : 1;
})->purpose('Import a legacy blog XML export from a local file path');

Artisan::command('wordpress:promote-real-weddings', function (RealWeddingPromoter $promoter) {
    $posts = JournalPost::query()
        ->with('tags')
        ->where('post_type', 'real_wedding')
        ->get();

    $this->components->info("Promoting {$posts->count()} real wedding posts into wedding stories");

    $promoted = 0;
    foreach ($posts as $post) {
        $promoter->promote($post);
        $promoted++;
    }

    $this->components->info("Promoted {$promoted} posts.");

    return 0;
})->purpose('Promote imported real wedding journal posts into wedding stories');

Artisan::command('wordpress:repair-classification {--dry-run}', function (WordPressPostClassifier $classifier, RealWeddingPromoter $promoter) {
    $dryRun = (bool) $this->option('dry-run');
    $posts = JournalPost::query()
        ->whereNotNull('original_wp_post_id')
        ->with(['categories', 'tags', 'media'])
        ->orderBy('id')
        ->get();

    $summary = [
        'posts_checked' => 0,
        'post_types_updated' => 0,
        'stories_promoted' => 0,
        'stories_archived' => 0,
        'journal_posts_restored' => 0,
    ];

    foreach ($posts as $post) {
        $summary['posts_checked']++;
        $resolvedType = $classifier->classifyJournalPost($post);
        $originalType = $post->post_type;
        $stories = WeddingStory::query()
            ->where('original_wp_post_id', $post->original_wp_post_id)
            ->orderBy('id')
            ->get();
        $activeStories = $stories->where('status', '!=', 'archived')->values();

        if ($originalType !== $resolvedType) {
            $summary['post_types_updated']++;

            if (! $dryRun) {
                $post->post_type = $resolvedType;
                $post->save();
            }

            $this->line("- {$post->slug}: post_type {$originalType} -> {$resolvedType}");
        }

        if ($resolvedType === 'real_wedding') {
            if ($activeStories->isEmpty() && $post->status === 'published') {
                $summary['stories_promoted']++;

                if (! $dryRun) {
                    $promoter->promote($post->fresh(['tags', 'media']));
                }
            }

            continue;
        }

        if ($activeStories->isEmpty()) {
            continue;
        }

        if ($post->status === 'archived') {
            $summary['journal_posts_restored']++;
        }

        if ($dryRun) {
            foreach ($activeStories as $story) {
                $summary['stories_archived']++;
                $this->line("- {$story->slug}: archive wedding story and restore /journal/{$post->slug}");
            }

            continue;
        }

        $activeHeroStory = $activeStories->firstWhere('hero_media_id', '!=', null);

        if (! $post->hero_media_id && $activeHeroStory?->hero_media_id) {
            $post->hero_media_id = $activeHeroStory->hero_media_id;
        }

        if ($post->status === 'archived') {
            $post->status = 'published';
        }

        $post->save();

        foreach ($activeStories as $story) {
            $summary['stories_archived']++;

            $story->status = 'archived';
            $story->save();

            Redirect::query()->where([
                'from_path' => '/journal/'.$post->slug,
                'to_path' => '/weddings/'.$story->slug,
            ])->delete();

            Redirect::query()->updateOrCreate(
                ['from_path' => '/weddings/'.$story->slug],
                [
                    'to_path' => '/journal/'.$post->slug,
                    'status_code' => 301,
                    'source' => 'wp_import',
                ]
            );
        }

        if ($post->original_wp_url) {
            $fromPath = parse_url($post->original_wp_url, PHP_URL_PATH);

            if (is_string($fromPath) && trim($fromPath) !== '') {
                Redirect::query()->updateOrCreate(
                    ['from_path' => '/'.ltrim($fromPath, '/')],
                    [
                        'to_path' => '/journal/'.$post->slug,
                        'status_code' => 301,
                        'source' => 'wp_import',
                    ]
                );
            }
        }

        ImportMapping::query()
            ->where('source_table', 'wp_posts')
            ->where('source_id', $post->original_wp_post_id)
            ->update([
                'target_type' => $post->getMorphClass(),
                'target_id' => $post->id,
            ]);
    }

    $this->components->info('WordPress classification repair complete.');
    $this->line("- posts checked: {$summary['posts_checked']}");
    $this->line("- post types updated: {$summary['post_types_updated']}");
    $this->line("- stories promoted: {$summary['stories_promoted']}");
    $this->line("- stories archived: {$summary['stories_archived']}");
    $this->line("- journal posts restored: {$summary['journal_posts_restored']}");

    return 0;
})->purpose('Reclassify imported WordPress posts and repair misrouted wedding-story promotions');

Artisan::command('legacy:audit-media {--include-archived} {--limit=0}', function (WordPressJournalImporter $importer) {
    $includeArchived = (bool) $this->option('include-archived');
    $limit = max(0, (int) $this->option('limit'));

    $auditModel = function (string $modelClass, string $routePrefix) use ($includeArchived, $limit, $importer): array {
        $query = $modelClass::query()
            ->whereNotNull('original_wp_post_id')
            ->where('body', 'like', '%wp-content/uploads%')
            ->orderBy('slug');

        if (! $includeArchived) {
            $query->published();
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $records = $query->get(['id', 'slug', 'title', 'body']);
        $items = $records->map(function ($record) use ($routePrefix, $importer) {
            $inspection = $importer->inspectLegacyMediaForRecord($record);

            return [
                'slug' => $record->slug,
                'title' => $record->title,
                'route' => $routePrefix.'/'.$record->slug,
                'found' => (int) $inspection['found'],
                'present' => (int) $inspection['present'],
                'missing' => (int) $inspection['missing'],
            ];
        })->values();

        return [
            'total' => $items->count(),
            'fully_mirrored' => $items->filter(fn ($item) => $item['found'] > 0 && $item['missing'] === 0)->count(),
            'missing_assets' => $items->filter(fn ($item) => $item['missing'] > 0)->count(),
            'items' => $items->filter(fn ($item) => $item['missing'] > 0)->values()->all(),
        ];
    };

    $stories = $auditModel(WeddingStory::class, '/weddings');
    $posts = $auditModel(JournalPost::class, '/journal');

    $this->components->info('Legacy WordPress media audit complete.');
    $this->line("- stories with legacy body media: {$stories['total']}");
    $this->line("- stories fully mirrored: {$stories['fully_mirrored']}");
    $this->line("- stories missing mirrored assets: {$stories['missing_assets']}");
    $this->line("- posts with legacy body media: {$posts['total']}");
    $this->line("- posts fully mirrored: {$posts['fully_mirrored']}");
    $this->line("- posts missing mirrored assets: {$posts['missing_assets']}");

    if ($stories['items'] !== []) {
        $this->line('Story routes missing mirrored assets:');

        foreach ($stories['items'] as $item) {
            $this->line("- {$item['route']} ({$item['present']}/{$item['found']} local) {$item['title']}");
        }
    }

    if ($posts['items'] !== []) {
        $this->line('Post routes missing mirrored assets:');

        foreach ($posts['items'] as $item) {
            $this->line("- {$item['route']} ({$item['present']}/{$item['found']} local) {$item['title']}");
        }
    }

    return 0;
})->purpose('Audit mirrored legacy WordPress upload assets referenced by imported body content');

Artisan::command('legacy:repair-media {--slug=*} {--include-archived} {--source-dir=}', function (WordPressJournalImporter $importer) {
    $slugs = collect((array) $this->option('slug'))
        ->map(fn ($slug) => trim((string) $slug))
        ->filter()
        ->values();
    $includeArchived = (bool) $this->option('include-archived');
    $sourceDir = trim((string) $this->option('source-dir')) ?: null;

    $queryModels = function (string $modelClass) use ($slugs, $includeArchived) {
        return $modelClass::query()
            ->whereNotNull('original_wp_post_id')
            ->where('body', 'like', '%wp-content/uploads%')
            ->when($slugs->isNotEmpty(), fn ($query) => $query->whereIn('slug', $slugs->all()))
            ->when(! $includeArchived, fn ($query) => $query->published())
            ->orderBy('slug')
            ->get();
    };

    $records = $queryModels(WeddingStory::class)->concat($queryModels(JournalPost::class));

    if ($records->isEmpty()) {
        $this->components->warn('No legacy WordPress records matched the current filters.');

        return 1;
    }

    $summary = [
        'records_seen' => 0,
        'records_updated' => 0,
        'images_found' => 0,
        'images_imported' => 0,
        'images_reused' => 0,
        'images_failed' => 0,
    ];

    foreach ($records as $record) {
        $summary['records_seen']++;
        $result = $importer->repairLegacyMediaForRecord($record, $sourceDir);
        $summary['images_found'] += (int) ($result['found'] ?? 0);
        $summary['images_imported'] += (int) ($result['imported'] ?? 0);
        $summary['images_reused'] += (int) ($result['reused'] ?? 0);
        $summary['images_failed'] += (int) ($result['failed'] ?? 0);

        if (($result['imported'] ?? 0) > 0 || ($result['reused'] ?? 0) > 0) {
            $summary['records_updated']++;
        }

        $this->line('- '.class_basename($record)."/{$record->slug}: found {$result['found']}, imported {$result['imported']}, reused {$result['reused']}, failed {$result['failed']}");
    }

    $this->components->info('Legacy WordPress media repair complete.');
    $this->line("- records seen: {$summary['records_seen']}");
    $this->line("- records updated: {$summary['records_updated']}");
    $this->line("- images found: {$summary['images_found']}");
    $this->line("- images imported: {$summary['images_imported']}");
    $this->line("- images reused: {$summary['images_reused']}");
    $this->line("- images failed: {$summary['images_failed']}");

    return 0;
})->purpose('Mirror legacy WordPress upload images referenced in imported body content');

Artisan::command('pictime:import {sources*} {--target=auto}', function (PicTimeImporter $importer) {
    $sources = (array) $this->argument('sources');
    $target = (string) $this->option('target');

    $this->components->info('Importing Pic-Time sources');

    $run = $importer->importSources($sources, $target);

    $this->components->info("Import run {$run->id} completed with status `{$run->status}`.");

    if ($run->summary_json) {
        foreach ($run->summary_json as $key => $value) {
            $this->line('- '.str_replace('_', ' ', $key).": {$value}");
        }
    }

    if ($run->error_log) {
        $this->newLine();
        $this->components->warn($run->error_log);
    }

    return $run->status === 'completed' ? 0 : 1;
})->purpose('Import Pic-Time blog pages from one or more URLs or local HTML files');

Artisan::command('pictime:repair-content', function () {
    $repairCollection = function ($records): array {
        $updated = 0;
        $withNarrative = 0;
        $withEmbedOnly = 0;

        foreach ($records as $record) {
            $markup = trim((string) ($record->source_markup ?? ''));
            $body = trim((string) ($record->body ?? ''));
            $manualPath = storage_path('app/private/manual-pictime/'.$record->slug.'.html');

            if (File::exists($manualPath)) {
                $markup = trim((string) File::get($manualPath));
            }

            if ($markup === '' && PicTimeContent::containsEmbed($body)) {
                $markup = $body;
            }

            if ($markup === '') {
                continue;
            }

            $excerpt = PicTimeContent::excerpt($markup);
            $derivedBody = PicTimeContent::bodyHtml($markup);

            $dirty = false;

            if (($record->source_markup ?? null) !== $markup) {
                $record->source_markup = $markup;
                $dirty = true;
            }

            if (blank($record->excerpt) && filled($excerpt)) {
                $record->excerpt = $excerpt;
                $dirty = true;
            }

            if (PicTimeContent::containsEmbed($body) || blank($body)) {
                $normalizedBody = $derivedBody ?: null;

                if (($record->body ?? null) !== $normalizedBody) {
                    $record->body = $normalizedBody;
                    $dirty = true;
                }
            }

            if ($dirty) {
                $record->save();
                $updated++;
            }

            if ($derivedBody) {
                $withNarrative++;
            } else {
                $withEmbedOnly++;
            }
        }

        return [$updated, $withNarrative, $withEmbedOnly];
    };

    $repairQuery = function ($query) use ($repairCollection): array {
        $updated = 0;
        $withNarrative = 0;
        $withEmbedOnly = 0;

        $query->orderBy('id')->chunkById(25, function ($records) use (&$updated, &$withNarrative, &$withEmbedOnly, $repairCollection) {
            [$chunkUpdated, $chunkNarrative, $chunkEmbedOnly] = $repairCollection($records);

            $updated += $chunkUpdated;
            $withNarrative += $chunkNarrative;
            $withEmbedOnly += $chunkEmbedOnly;

            unset($records);
            gc_collect_cycles();
        });

        return [$updated, $withNarrative, $withEmbedOnly];
    };

    [$storiesUpdated, $storiesNarrative, $storiesEmbedOnly] = $repairQuery(
        WeddingStory::query()->where(function ($query) {
            $query->where('canonical_url', 'like', '%pic-time.com%')
                ->orWhere('original_wp_url', 'like', '%pic-time.com%')
                ->orWhere('body', 'like', '%slideswebcomponentembed.js%')
                ->orWhere('source_markup', 'like', '%slideswebcomponentembed.js%');
        })
    );

    [$postsUpdated, $postsNarrative, $postsEmbedOnly] = $repairQuery(
        JournalPost::query()->where(function ($query) {
            $query->where('canonical_url', 'like', '%pic-time.com%')
                ->orWhere('original_wp_url', 'like', '%pic-time.com%')
                ->orWhere('body', 'like', '%slideswebcomponentembed.js%')
                ->orWhere('source_markup', 'like', '%slideswebcomponentembed.js%');
        })
    );

    $this->components->info('Pic-Time content repair complete.');
    $this->line("- wedding stories updated: {$storiesUpdated}");
    $this->line("- wedding stories with narrative: {$storiesNarrative}");
    $this->line("- wedding stories still embed-only: {$storiesEmbedOnly}");
    $this->line("- journal posts updated: {$postsUpdated}");
    $this->line("- journal posts with narrative: {$postsNarrative}");
    $this->line("- journal posts still embed-only: {$postsEmbedOnly}");

    return 0;
})->purpose('Repair existing Pic-Time records by separating source markup from readable body content');

Artisan::command('pictime:repair-media {--slug=*} {--include-archived} {--all-records}', function (PicTimeImporter $importer) {
    $slugs = collect((array) $this->option('slug'))
        ->map(fn ($slug) => trim((string) $slug))
        ->filter()
        ->values();
    $includeArchived = (bool) $this->option('include-archived');
    $allRecords = (bool) $this->option('all-records');

    $applyPicTimeScope = function ($query) {
        $query->where(function ($inner) {
            $inner->where('canonical_url', 'like', '%pic-time.com%')
                ->orWhere('original_wp_url', 'like', '%pic-time.com%')
                ->orWhere('body', 'like', '%slideswebcomponentembed.js%')
                ->orWhere('source_markup', 'like', '%slideswebcomponentembed.js%');
        });
    };

    $publishedAudit = function (string $modelClass) use ($applyPicTimeScope): array {
        $query = $modelClass::query();
        $applyPicTimeScope($query);

        $records = $query
            ->published()
            ->withCount('media')
            ->get();

        return [
            'total' => $records->count(),
            'zero_media' => $records->where('media_count', 0)->count(),
            'one_media' => $records->where('media_count', 1)->count(),
            'two_plus_media' => $records->where('media_count', '>=', 2)->count(),
        ];
    };

    $printAudit = function (string $label, array $audit): void {
        $this->line("- {$label} total: {$audit['total']}");
        $this->line("- {$label} with 0 media: {$audit['zero_media']}");
        $this->line("- {$label} with 1 media: {$audit['one_media']}");
        $this->line("- {$label} with 2+ media: {$audit['two_plus_media']}");
    };

    $beforeStoriesAudit = $publishedAudit(WeddingStory::class);
    $beforePostsAudit = $publishedAudit(JournalPost::class);

    $stories = WeddingStory::query()
        ->when(
            $slugs->isNotEmpty(),
            fn ($query) => $query->whereIn('slug', $slugs->all()),
            function ($query) use ($applyPicTimeScope, $includeArchived, $allRecords) {
                $applyPicTimeScope($query);

                if (! $includeArchived) {
                    $query->published();
                }

                if (! $allRecords) {
                    $query->withCount('media');
                }
            }
        )
        ->get();

    if ($slugs->isEmpty() && ! $allRecords) {
        $stories = $stories
            ->where('media_count', '<=', 1)
            ->sortBy([
                ['media_count', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    $posts = JournalPost::query()
        ->when(
            $slugs->isNotEmpty(),
            fn ($query) => $query->whereIn('slug', $slugs->all()),
            function ($query) use ($applyPicTimeScope, $includeArchived, $allRecords) {
                $applyPicTimeScope($query);

                if (! $includeArchived) {
                    $query->published();
                }

                if (! $allRecords) {
                    $query->withCount('media');
                }
            }
        )
        ->get();

    if ($slugs->isEmpty() && ! $allRecords) {
        $posts = $posts
            ->where('media_count', '<=', 1)
            ->sortBy([
                ['media_count', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    $records = $stories->concat($posts);

    if ($records->isEmpty()) {
        $label = $slugs->isEmpty() ? 'No Pic-Time records found.' : 'No matching Pic-Time records found for the provided slug filters.';
        $this->components->warn($label);

        return 1;
    }

    $summary = [
        'records_seen' => 0,
        'records_updated' => 0,
        'image_urls_found' => 0,
        'media_imported' => 0,
        'media_reused' => 0,
    ];

    foreach ($records as $record) {
        $summary['records_seen']++;

        $result = $importer->hydrateMediaForRecord($record);

        if (($result['imported'] ?? 0) > 0 || ($result['reused'] ?? 0) > 0) {
            $summary['records_updated']++;
        }

        $summary['image_urls_found'] += (int) ($result['found'] ?? 0);
        $summary['media_imported'] += (int) ($result['imported'] ?? 0);
        $summary['media_reused'] += (int) ($result['reused'] ?? 0);

        $this->line('- '.class_basename($record)."/{$record->slug}: found {$result['found']}, imported {$result['imported']}, reused {$result['reused']}");
    }

    $this->components->info('Pic-Time media repair complete.');
    $this->line('Published Pic-Time surface before repair:');
    $printAudit('stories', $beforeStoriesAudit);
    $printAudit('posts', $beforePostsAudit);
    $this->line('Published Pic-Time surface after repair:');
    $printAudit('stories', $publishedAudit(WeddingStory::class));
    $printAudit('posts', $publishedAudit(JournalPost::class));
    $this->line("- records seen: {$summary['records_seen']}");
    $this->line("- records updated: {$summary['records_updated']}");
    $this->line("- image urls found: {$summary['image_urls_found']}");
    $this->line("- media imported: {$summary['media_imported']}");
    $this->line("- media reused: {$summary['media_reused']}");

    return 0;
})->purpose('Hydrate local media for existing Pic-Time records from stored embed markup');

Artisan::command('pictime:audit {--include-archived} {--limit=0} {--deep}', function (PicTimeImporter $importer) {
    $includeArchived = (bool) $this->option('include-archived');
    $limit = max(0, (int) $this->option('limit'));
    $deep = (bool) $this->option('deep');

    $applyPicTimeScope = function ($query) {
        $query->where(function ($inner) {
            $inner->where('canonical_url', 'like', '%pic-time.com%')
                ->orWhere('original_wp_url', 'like', '%pic-time.com%')
                ->orWhere('body', 'like', '%slideswebcomponentembed.js%')
                ->orWhere('source_markup', 'like', '%slideswebcomponentembed.js%');
        });
    };

    $auditModel = function (string $modelClass, string $routePrefix) use ($applyPicTimeScope, $includeArchived, $limit, $deep, $importer): array {
        $query = $modelClass::query()
            ->withCount('media')
            ->orderBy('slug');

        $applyPicTimeScope($query);

        if (! $includeArchived) {
            $query->published();
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $records = $query->get(['id', 'slug', 'title', 'status', 'published_at', 'canonical_url', 'original_wp_url', 'body', 'source_markup']);
        $underIngested = collect();

        if ($deep) {
            $underIngested = $records
                ->map(function ($record) use ($routePrefix, $importer) {
                    $inspection = $importer->inspectMediaForRecord($record, false);

                    return [
                        'slug' => $record->slug,
                        'title' => $record->title,
                        'route' => $routePrefix.'/'.$record->slug,
                        'media_count' => (int) $inspection['media_count'],
                        'found' => (int) $inspection['found'],
                        'missing' => (int) $inspection['missing'],
                        'has_source_markup' => (bool) $inspection['has_source_markup'],
                    ];
                })
                ->filter(fn ($record) => $record['found'] > $record['media_count'])
                ->values();
        }

        $thin = $records
            ->filter(fn ($record) => (int) $record->media_count <= 1)
            ->values()
            ->map(fn ($record) => [
                'slug' => $record->slug,
                'title' => $record->title,
                'media_count' => (int) $record->media_count,
                'route' => $routePrefix.'/'.$record->slug,
            ])
            ->all();

        return [
            'total' => $records->count(),
            'zero_media' => $records->where('media_count', 0)->count(),
            'one_media' => $records->where('media_count', 1)->count(),
            'two_plus_media' => $records->filter(fn ($record) => (int) $record->media_count >= 2)->count(),
            'thin' => $thin,
            'under_ingested' => $underIngested->all(),
        ];
    };

    $stories = $auditModel(WeddingStory::class, '/weddings');
    $posts = $auditModel(JournalPost::class, '/journal');

    $printSummary = function (string $label, array $audit): void {
        $this->line("- {$label} total: {$audit['total']}");
        $this->line("- {$label} with 0 media: {$audit['zero_media']}");
        $this->line("- {$label} with 1 media: {$audit['one_media']}");
        $this->line("- {$label} with 2+ media: {$audit['two_plus_media']}");
    };

    $this->components->info('Pic-Time audit complete.');
    $printSummary('stories', $stories);
    $printSummary('posts', $posts);

    if ($stories['thin'] !== []) {
        $this->line('Thin story routes:');

        foreach ($stories['thin'] as $item) {
            $this->line("- {$item['route']} ({$item['media_count']} media) {$item['title']}");
        }
    }

    if ($posts['thin'] !== []) {
        $this->line('Thin post routes:');

        foreach ($posts['thin'] as $item) {
            $this->line("- {$item['route']} ({$item['media_count']} media) {$item['title']}");
        }
    }

    if ($deep && $stories['under_ingested'] !== []) {
        $this->line('Under-ingested story routes:');

        foreach ($stories['under_ingested'] as $item) {
            $this->line("- {$item['route']} ({$item['media_count']} local / {$item['found']} discoverable) {$item['title']}");
        }
    }

    if ($deep && $posts['under_ingested'] !== []) {
        $this->line('Under-ingested post routes:');

        foreach ($posts['under_ingested'] as $item) {
            $this->line("- {$item['route']} ({$item['media_count']} local / {$item['found']} discoverable) {$item['title']}");
        }
    }

    return 0;
})->purpose('Audit the current Pic-Time surface and list remaining thin public routes');

Artisan::command('pictime:sync-owners', function () {
    $copyMedia = function (string $fromType, int $fromId, string $toType, int $toId) {
        $existingMediaIds = DB::table('mediables')
            ->where('mediable_type', $toType)
            ->where('mediable_id', $toId)
            ->pluck('media_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rows = DB::table('mediables')
            ->where('mediable_type', $fromType)
            ->where('mediable_id', $fromId)
            ->orderBy('sort_order')
            ->get();

        $copied = 0;

        foreach ($rows as $row) {
            $mediaId = (int) $row->media_id;

            if (in_array($mediaId, $existingMediaIds, true)) {
                continue;
            }

            DB::table('mediables')->insert([
                'media_id' => $mediaId,
                'mediable_type' => $toType,
                'mediable_id' => $toId,
                'role' => $row->role,
                'sort_order' => $row->sort_order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $existingMediaIds[] = $mediaId;
            $copied++;
        }

        return $copied;
    };

    $syncFields = function ($target, $source, array $fields): int {
        $synced = 0;

        foreach ($fields as $field) {
            if (blank($target->{$field}) && filled($source->{$field})) {
                $target->{$field} = $source->{$field};
                $synced++;
            }
        }

        return $synced;
    };

    $summary = [
        'journal_pairs' => 0,
        'journal_media_rows_copied' => 0,
        'journal_heroes_assigned' => 0,
        'journal_fields_synced' => 0,
        'journal_redirects_created' => 0,
        'archived_pairs' => 0,
        'archived_media_rows_copied' => 0,
        'archived_heroes_assigned' => 0,
        'archived_fields_synced' => 0,
    ];

    $journalPairs = JournalPost::published()
        ->whereNotNull('canonical_url')
        ->where('canonical_url', '!=', '')
        ->get()
        ->map(function (JournalPost $post) {
            $story = WeddingStory::published()
                ->where('canonical_url', $post->canonical_url)
                ->first();

            return $story ? [$post, $story] : null;
        })
        ->filter()
        ->values();

    foreach ($journalPairs as [$post, $story]) {
        $summary['journal_pairs']++;

        DB::transaction(function () use ($post, $story, $copyMedia, $syncFields, &$summary) {
            $summary['journal_media_rows_copied'] += $copyMedia(
                JournalPost::class,
                $post->id,
                WeddingStory::class,
                $story->id
            );

            if (! $story->hero_media_id && $post->hero_media_id) {
                $story->hero_media_id = $post->hero_media_id;
                $summary['journal_heroes_assigned']++;
            }

            $summary['journal_fields_synced'] += $syncFields($story, $post, [
                'excerpt',
                'body',
                'source_markup',
                'original_wp_url',
            ]);

            $story->save();

            $from = '/journal/'.ltrim($post->slug, '/');
            $to = '/weddings/'.ltrim($story->slug, '/');

            if (! Redirect::query()->where('from_path', $from)->exists()) {
                Redirect::query()->create([
                    'from_path' => $from,
                    'to_path' => $to,
                    'status_code' => 301,
                    'source' => 'pictime_sync_owners',
                ]);
                $summary['journal_redirects_created']++;
            }
        });
    }

    $archivedPairs = WeddingStory::query()
        ->where('status', 'archived')
        ->get()
        ->map(function (WeddingStory $archived) {
            $publishedSlug = preg_replace('/-archived-\d+$/', '', $archived->slug) ?: $archived->slug;
            $published = WeddingStory::published()->where('slug', $publishedSlug)->first();

            return $published ? [$archived, $published] : null;
        })
        ->filter()
        ->values();

    foreach ($archivedPairs as [$archived, $published]) {
        $summary['archived_pairs']++;

        DB::transaction(function () use ($archived, $published, $copyMedia, $syncFields, &$summary) {
            $summary['archived_media_rows_copied'] += $copyMedia(
                WeddingStory::class,
                $archived->id,
                WeddingStory::class,
                $published->id
            );

            if (! $published->hero_media_id && $archived->hero_media_id) {
                $published->hero_media_id = $archived->hero_media_id;
                $summary['archived_heroes_assigned']++;
            }

            $summary['archived_fields_synced'] += $syncFields($published, $archived, [
                'excerpt',
                'body',
                'source_markup',
                'original_wp_post_id',
                'original_wp_url',
            ]);

            $published->save();
        });
    }

    $this->components->info('Pic-Time owner sync complete.');
    foreach ($summary as $key => $value) {
        $this->line('- '.str_replace('_', ' ', $key).": {$value}");
    }

    return 0;
})->purpose('Move imported Pic-Time media and content onto the published public owners');

Artisan::command('launch:check', function () {
    $kernel = app(\Illuminate\Contracts\Http\Kernel::class);

    $failures = [];
    $warnings = [];
    $report = function (string $label, bool $passed): void {
        $status = $passed ? 'OK' : 'FAIL';
        $this->line("[{$status}] {$label}");
    };

    $this->components->info('Running launch readiness checks');

    $manifestPath = public_path('build/manifest.json');
    $hasManifest = File::exists($manifestPath);
    $report('Frontend build manifest present', $hasManifest);
    if (! $hasManifest) {
        $failures[] = 'Missing `public/build/manifest.json`. Run `npm run build` before deploy.';
    }

    $storageLinkPath = public_path('storage');
    $storageTarget = storage_path('app/public');
    $hasStorageLink = File::isDirectory($storageLinkPath) || is_link($storageLinkPath);
    $report('Public storage path is available', $hasStorageLink);
    if (! $hasStorageLink) {
        $failures[] = "Missing `public/storage`. Run `php artisan storage:link` to expose {$storageTarget}.";
    }

    $legacyUploadReferences = JournalPost::published()
        ->where('body', 'like', '%wp-content/uploads%')
        ->count()
        + WeddingStory::published()
            ->where('body', 'like', '%wp-content/uploads%')
            ->count();
    $legacyUploadsPath = public_path('wp-content/uploads');
    $hasLegacyUploads = File::isDirectory($legacyUploadsPath) || is_link($legacyUploadsPath);
    $legacyUploadsOk = $legacyUploadReferences === 0 || $hasLegacyUploads;
    $report("Legacy WordPress uploads path is available when needed ({$legacyUploadReferences} records)", $legacyUploadsOk);
    if (! $legacyUploadsOk) {
        $failures[] = 'Missing `public/wp-content/uploads` while imported legacy WordPress records still reference old uploads. Link the shared legacy uploads directory before launch.';
    }

    $defaultDisk = config('filesystems.default');
    $diskOk = $defaultDisk === 'public' || $defaultDisk === 's3';
    $report("Filesystem default disk is deploy-safe (`{$defaultDisk}`)", $diskOk);
    if (! $diskOk) {
        $failures[] = "Default filesystem disk is `{$defaultDisk}`. Use `public` or `s3` for public media delivery.";
    }

    $appUrl = (string) config('app.url');
    $appUrlHost = parse_url($appUrl, PHP_URL_HOST);
    $appUrlOk = filled($appUrl) && $appUrlHost !== null;
    $report("APP_URL is configured (`{$appUrl}`)", $appUrlOk);
    if (! $appUrlOk) {
        $failures[] = 'APP_URL is not configured.';
    } elseif (in_array($appUrlHost, ['localhost', '127.0.0.1'], true)) {
        $warnings[] = "APP_URL is set to `{$appUrl}`. Update it to the public domain before production deploys.";
    }

    $publicRoutes = collect([
        'home',
        'weddings.index',
        'journal.index',
        'venues.index',
        'inquiry.create',
        'inquiry.thank-you',
        'sitemap',
    ])->filter(fn (string $name) => Route::has($name));

    foreach ($publicRoutes as $routeName) {
        $uri = route($routeName);
        $request = Request::create($uri, 'GET');
        $response = $kernel->handle($request);
        $ok = $response->getStatusCode() === 200;

        $report("GET {$routeName} returns 200", $ok);

        if (! $ok) {
            $failures[] = "Route `{$routeName}` returned {$response->getStatusCode()} during launch checks.";
        }

        $kernel->terminate($request, $response);
    }

    $requiredPages = [
        'about' => 'pages.about',
        'collections' => 'collections.index',
    ];

    foreach ($requiredPages as $slug => $routeName) {
        $page = Page::published()->where('slug', $slug)->first();
        $exists = $page !== null;

        $report("Published `{$slug}` page exists", $exists);

        if (! $exists) {
            $failures[] = "The published `{$slug}` page is missing. Create it before launch.";
            continue;
        }

        $uri = route($routeName);
        $request = Request::create($uri, 'GET');
        $response = $kernel->handle($request);
        $ok = $response->getStatusCode() === 200;

        $report("GET {$routeName} returns 200", $ok);

        if (! $ok) {
            $failures[] = "Route `{$routeName}` returned {$response->getStatusCode()} during launch checks.";
        }

        $kernel->terminate($request, $response);
    }

    if ($warnings !== []) {
        $this->newLine();
        $this->components->warn('Warnings');
        foreach ($warnings as $warning) {
            $this->line("- {$warning}");
        }
    }

    if ($failures !== []) {
        $this->newLine();
        $this->components->error('Launch checks failed');
        foreach ($failures as $failure) {
            $this->line("- {$failure}");
        }

        return 1;
    }

    $this->newLine();
    $this->components->info('Launch checks passed');

    return 0;
})->purpose('Run deploy-oriented checks for assets, storage, and public routes');
