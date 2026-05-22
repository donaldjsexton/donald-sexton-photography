<?php

namespace App\Console\Commands;

use App\Models\Page;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pages:backfill-blocks {--dry-run : Report what would change without writing}')]
#[Description('Wrap each legacy page body into a rich_text block so pages render through the block engine.')]
class BackfillPageBlocks extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $converted = 0;
        $skipped = 0;

        Page::query()
            ->whereDoesntHave('allBlocks')
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->orderBy('id')
            ->each(function (Page $page) use ($dryRun, &$converted, &$skipped): void {
                $body = trim((string) $page->body);

                if ($body === '') {
                    $skipped++;

                    return;
                }

                if ($dryRun) {
                    $this->line("Would convert page #{$page->id} ({$page->slug}).");
                    $converted++;

                    return;
                }

                $page->allBlocks()->create([
                    'type' => 'rich_text',
                    'body' => $body,
                    'sort_order' => 0,
                    'is_visible' => true,
                ]);

                $converted++;
            });

        $this->info(($dryRun ? 'Dry run: ' : '').sprintf('%d page(s) converted, %d skipped.', $converted, $skipped));

        return self::SUCCESS;
    }
}
