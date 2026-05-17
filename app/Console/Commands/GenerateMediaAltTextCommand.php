<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\Media\AltTextGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:generate-alt-text
    {--all : Regenerate and overwrite even when alt_text is already filled}
    {--media=* : Limit to specific Media IDs}
    {--limit=50 : Maximum number of images to process in this run}
    {--sleep=1 : Seconds to sleep between API calls}
    {--max-seconds=45 : Stop gracefully after roughly this many seconds so web-triggered runs return before the request times out; 0 disables the budget}
    {--dry-run : Show which records would be processed without calling the API}
')]
#[Description('Generate alt text for media records using Claude vision.')]
class GenerateMediaAltTextCommand extends Command
{
    public function handle(AltTextGenerator $generator): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $regenerateAll = (bool) $this->option('all');
        $ids = array_filter(array_map('intval', (array) $this->option('media')));
        $limit = max(1, (int) $this->option('limit'));
        $sleepSeconds = max(0, (int) $this->option('sleep'));
        $maxSeconds = max(0, (int) $this->option('max-seconds'));
        $startedAt = microtime(true);

        $query = Media::query()
            ->whereNotNull('path')
            ->where('path', '!=', '')
            ->orderBy('id');

        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        }

        if (! $regenerateAll) {
            $query->where(function ($q): void {
                $q->whereNull('alt_text')->orWhere('alt_text', '');
            });
        }

        $query->limit($limit);

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No media records need alt text generation.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s alt text for %d %s%s.',
            $isDryRun ? 'Previewing' : 'Generating',
            $total,
            $total === 1 ? 'image' : 'images',
            $regenerateAll ? ' (regenerating all)' : '',
        ));

        $written = 0;
        $skipped = 0;
        $failed = 0;
        $processed = 0;
        $stoppedEarly = false;

        $query->with(['weddingStories', 'journalPosts', 'venues'])->each(function (Media $media) use (
            $generator, $isDryRun, $regenerateAll, $sleepSeconds, $total, $maxSeconds, $startedAt,
            &$written, &$skipped, &$failed, &$processed, &$stoppedEarly
        ): bool {
            if ($maxSeconds > 0 && (microtime(true) - $startedAt) >= $maxSeconds) {
                $stoppedEarly = true;

                return false;
            }

            $processed++;
            $this->line(sprintf('• #%d  %s', $media->id, $media->path));

            if ($isDryRun) {
                $context = $this->buildContext($media);
                $this->line('  ↳ context: '.($context ?? '(none)'));
                $skipped++;

                return true;
            }

            $context = $this->buildContext($media);
            $altText = $generator->generate($media, $context);

            if ($altText === null) {
                $failed++;
                $this->warn('  ↳ generation failed (see logs).');

                return true;
            }

            if (! $regenerateAll && filled($media->alt_text)) {
                $skipped++;
                $this->line('  ↳ existing alt text preserved (use --all to overwrite).');

                return true;
            }

            $media->forceFill(['alt_text' => $altText])->save();
            $written++;
            $this->line('  ↳ '.$altText);

            if ($sleepSeconds > 0 && $processed < $total) {
                sleep($sleepSeconds);
            }

            return true;
        });

        $this->newLine();

        if ($isDryRun) {
            $this->info(sprintf('Dry run complete. %d %s would be processed.', $skipped, $skipped === 1 ? 'image' : 'images'));
        } else {
            $this->info(sprintf(
                'Wrote alt text for %d %s. Preserved: %d. Failed: %d.',
                $written,
                $written === 1 ? 'image' : 'images',
                $skipped,
                $failed,
            ));
        }

        if ($stoppedEarly) {
            $remaining = max(0, $total - $processed);

            $this->warn(sprintf(
                'Stopped after the %ds time budget with %d %s still pending. Run the command again to continue, or pass --max-seconds=0 to disable the budget.',
                $maxSeconds,
                $remaining,
                $remaining === 1 ? 'image' : 'images',
            ));
        }

        return $failed > 0 && $written === 0 ? self::FAILURE : self::SUCCESS;
    }

    private function buildContext(Media $media): ?string
    {
        $story = $media->weddingStories->first();

        if ($story) {
            $venueName = $story->venue?->name ?? $story->location_name ?? null;

            return $venueName
                ? $story->title.' — '.$venueName
                : $story->title;
        }

        $post = $media->journalPosts->first();

        if ($post) {
            return $post->title;
        }

        $venue = $media->venues->first();

        if ($venue) {
            return $venue->name.' wedding venue';
        }

        return null;
    }
}
