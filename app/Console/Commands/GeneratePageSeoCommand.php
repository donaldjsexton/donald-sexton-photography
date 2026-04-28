<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Services\Seo\PageSeoGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('seo:generate-pages
    {--all : Regenerate and overwrite even when seo_title and seo_description are already filled}
    {--page=* : Limit to specific Page IDs}
    {--limit=5 : Maximum number of pages to process in this run}
    {--sleep=2 : Seconds to sleep between API calls so we drip through in small batches}
    {--dry-run : Show what would be generated without saving or calling the API}
')]
#[Description('Generate SEO titles and descriptions for standalone pages using Claude Haiku.')]
class GeneratePageSeoCommand extends Command
{
    public function handle(PageSeoGenerator $generator): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $regenerateAll = (bool) $this->option('all');
        $ids = array_filter(array_map('intval', (array) $this->option('page')));
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $sleepSeconds = max(0, (int) $this->option('sleep'));

        $query = Page::query()->orderBy('id');

        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        }

        if (! $regenerateAll) {
            $query->where(function (Builder $query): void {
                $query
                    ->where(fn (Builder $q) => $q->whereNull('seo_title')->orWhere('seo_title', ''))
                    ->orWhere(fn (Builder $q) => $q->whereNull('seo_description')->orWhere('seo_description', ''));
            });
        }

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No pages need SEO generation.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s SEO for %d %s%s.',
            $isDryRun ? 'Previewing' : 'Generating',
            $total,
            $total === 1 ? 'page' : 'pages',
            $regenerateAll ? ' (regenerating all)' : '',
        ));

        $written = 0;
        $skipped = 0;
        $failed = 0;
        $processed = 0;

        $query->each(function (Page $page) use ($generator, $isDryRun, $regenerateAll, $sleepSeconds, $total, &$written, &$skipped, &$failed, &$processed): void {
            $processed++;
            $this->line(sprintf('• #%d %s', $page->id, $page->title ?? '(untitled)'));

            if ($isDryRun) {
                $skipped++;

                return;
            }

            $result = $generator->generate($page);

            if ($result === null) {
                $failed++;
                $this->warn('  ↳ generator returned null (see logs).');

                return;
            }

            $updates = [];

            if ($regenerateAll || $this->isBlank($page->seo_title)) {
                $updates['seo_title'] = $result->title;
            }

            if ($regenerateAll || $this->isBlank($page->seo_description)) {
                $updates['seo_description'] = $result->description;
            }

            if (empty($updates)) {
                $skipped++;
                $this->line('  ↳ existing SEO preserved (use --all to overwrite).');
            } else {
                $page->forceFill($updates)->save();
                $written++;

                if (array_key_exists('seo_title', $updates)) {
                    $this->line('  ↳ title:       '.$result->title);
                } else {
                    $this->line('  ↳ title kept:  '.$page->seo_title);
                }

                if (array_key_exists('seo_description', $updates)) {
                    $this->line('  ↳ description: '.$result->description);
                } else {
                    $this->line('  ↳ desc kept:   '.$page->seo_description);
                }
            }

            if ($sleepSeconds > 0 && $processed < $total) {
                sleep($sleepSeconds);
            }
        });

        $this->newLine();

        if ($isDryRun) {
            $this->info(sprintf('Dry run complete. %d %s would be processed.', $skipped, $skipped === 1 ? 'page' : 'pages'));
        } else {
            $this->info(sprintf(
                'Wrote SEO for %d %s. Preserved: %d. Failed: %d.',
                $written,
                $written === 1 ? 'page' : 'pages',
                $skipped,
                $failed,
            ));
        }

        return $failed > 0 && $written === 0 ? self::FAILURE : self::SUCCESS;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
