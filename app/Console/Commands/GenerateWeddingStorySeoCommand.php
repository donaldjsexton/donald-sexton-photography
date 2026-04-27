<?php

namespace App\Console\Commands;

use App\Models\WeddingStory;
use App\Services\Seo\WeddingStorySeoGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('seo:generate-wedding-stories
    {--all : Regenerate even when seo_title and seo_description are already filled}
    {--story=* : Limit to specific WeddingStory IDs}
    {--limit= : Maximum number of stories to process}
    {--dry-run : Show what would be generated without saving or calling the API}
')]
#[Description('Generate SEO titles and descriptions for wedding stories using Claude Haiku.')]
class GenerateWeddingStorySeoCommand extends Command
{
    public function handle(WeddingStorySeoGenerator $generator): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $regenerateAll = (bool) $this->option('all');
        $ids = array_filter(array_map('intval', (array) $this->option('story')));
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $query = WeddingStory::query()->orderBy('id');

        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        }

        if (! $regenerateAll) {
            $query->where(function (Builder $query): void {
                $query->whereNull('seo_title')->orWhereNull('seo_description');
            });
        }

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No wedding stories need SEO generation.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s SEO for %d wedding %s%s.',
            $isDryRun ? 'Previewing' : 'Generating',
            $total,
            $total === 1 ? 'story' : 'stories',
            $regenerateAll ? ' (regenerating all)' : '',
        ));

        $written = 0;
        $skipped = 0;
        $failed = 0;

        $query->each(function (WeddingStory $story) use ($generator, $isDryRun, &$written, &$skipped, &$failed): void {
            $this->line(sprintf('• #%d %s', $story->id, $story->title ?? '(untitled)'));

            if ($isDryRun) {
                $skipped++;

                return;
            }

            $result = $generator->generate($story);

            if ($result === null) {
                $failed++;
                $this->warn('  ↳ generator returned null (see logs).');

                return;
            }

            $story->forceFill([
                'seo_title' => $result->title,
                'seo_description' => $result->description,
            ])->save();

            $written++;
            $this->line('  ↳ title:       '.$result->title);
            $this->line('  ↳ description: '.$result->description);
        });

        $this->newLine();

        if ($isDryRun) {
            $this->info(sprintf('Dry run complete. %d %s would be processed.', $skipped, $skipped === 1 ? 'story' : 'stories'));
        } else {
            $this->info(sprintf('Wrote SEO for %d %s. Failed: %d.', $written, $written === 1 ? 'story' : 'stories', $failed));
        }

        return $failed > 0 && $written === 0 ? self::FAILURE : self::SUCCESS;
    }
}
