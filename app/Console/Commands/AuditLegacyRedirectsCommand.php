<?php

namespace App\Console\Commands;

use App\Models\JournalPost;
use App\Models\Redirect;
use App\Models\WeddingStory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('seo:audit-redirects
    {--missing-only : Only list records whose legacy root path has no redirect}
')]
#[Description('Audit whether legacy WordPress-style /{slug}/ paths 301 to their canonical /weddings or /journal URL.')]
class AuditLegacyRedirectsCommand extends Command
{
    public function handle(): int
    {
        $missingOnly = (bool) $this->option('missing-only');

        $knownFromPaths = Redirect::query()
            ->pluck('from_path')
            ->map(fn (string $path): string => '/'.trim($path, '/'))
            ->flip();

        $missing = 0;
        $covered = 0;

        foreach (['weddings' => WeddingStory::class, 'journal' => JournalPost::class] as $prefix => $model) {
            /** @var Collection<int, WeddingStory|JournalPost> $records */
            $records = $model::published()->orderBy('slug')->get(['id', 'slug', 'title']);

            $this->newLine();
            $this->info(strtoupper($prefix).' — '.$records->count().' published');

            foreach ($records as $record) {
                $slug = (string) $record->slug;
                $legacyRoot = '/'.trim($slug, '/');
                $canonical = '/'.$prefix.'/'.$slug;
                $hasRedirect = $knownFromPaths->has($legacyRoot);

                if ($hasRedirect) {
                    $covered++;

                    if (! $missingOnly) {
                        $this->line("  <fg=green>✓</> {$legacyRoot} → {$canonical}");
                    }

                    continue;
                }

                $missing++;
                $this->line("  <fg=yellow>•</> no redirect for {$legacyRoot}  (canonical {$canonical})");

                if (str_contains($slug, 'ipad')) {
                    $this->line("    <fg=red>↳ off-topic slug under /{$prefix} — consider reparenting to /journal.</>");
                }
            }
        }

        $this->newLine();
        $this->info("Legacy redirects present: {$covered}. Missing: {$missing}.");
        $this->line('A missing entry means the old /{slug}/ URL will not 301 to its canonical, so both can stay indexed and split ranking signals.');

        return self::SUCCESS;
    }
}
