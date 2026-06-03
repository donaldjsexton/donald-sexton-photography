<?php

namespace App\Console\Commands;

use App\Models\Venue;
use App\Models\WeddingStory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('venue:backfill-links
    {--create : Create a venue page for stories whose location has no matching venue yet}
    {--dry-run : Show what would change without saving}
')]
#[Description('Link published wedding stories to venue pages (and optionally create missing venues), building the venue landing-page graph.')]
class BackfillVenueLinksCommand extends Command
{
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $shouldCreate = (bool) $this->option('create');

        $stories = WeddingStory::query()
            ->where('status', 'published')
            ->whereNull('venue_id')
            ->whereNotNull('location_name')
            ->where('location_name', '!=', '')
            ->orderBy('id')
            ->get();

        if ($stories->isEmpty()) {
            $this->info('No published stories are missing a venue link.');

            return self::SUCCESS;
        }

        $venuesByName = Venue::query()
            ->get()
            ->keyBy(fn (Venue $venue) => $this->normalizeName($venue->name));

        $linked = 0;
        $created = 0;
        $candidates = [];

        foreach ($stories as $story) {
            $key = $this->normalizeName($story->location_name);

            if ($key === '') {
                continue;
            }

            $venue = $venuesByName->get($key);

            if ($venue === null) {
                if (! $shouldCreate) {
                    $candidates[$story->location_name] = ($candidates[$story->location_name] ?? 0) + 1;

                    continue;
                }

                $venue = $this->buildVenue($story, $isDryRun);
                $venuesByName->put($key, $venue);
                $created++;
                $this->line(sprintf('+ venue "%s" (%s)', $venue->name, $this->cityState($story) ?: 'location unknown'));
            }

            $this->linkStoryToVenue($story, $venue, $isDryRun);
            $linked++;
            $this->line(sprintf('  ↳ #%d %s → %s', $story->id, $story->title ?? '(untitled)', $venue->name));
        }

        $this->newLine();

        if ($candidates !== []) {
            $this->warn('Stories whose location has no matching venue (re-run with --create to add them):');
            arsort($candidates);
            foreach ($candidates as $name => $count) {
                $this->line(sprintf('  · %s (%d %s)', $name, $count, $count === 1 ? 'story' : 'stories'));
            }
            $this->newLine();
        }

        $this->info(sprintf(
            '%s%d stories linked, %d venues created.',
            $isDryRun ? '[dry run] ' : '',
            $linked,
            $created,
        ));

        return self::SUCCESS;
    }

    private function buildVenue(WeddingStory $story, bool $isDryRun): Venue
    {
        $venue = new Venue([
            'name' => trim((string) $story->location_name),
            'slug' => $this->uniqueSlug((string) $story->location_name),
            'city' => $story->city,
            'state' => $story->state,
            'hero_media_id' => $story->hero_media_id,
        ]);

        // saveQuietly skips the BelongsToSite creating hook, so carry the
        // tenant from the story that seeded this venue.
        $venue->site_id = $story->site_id;

        if (! $isDryRun) {
            $venue->saveQuietly();
        }

        return $venue;
    }

    private function linkStoryToVenue(WeddingStory $story, Venue $venue, bool $isDryRun): void
    {
        if ($isDryRun) {
            return;
        }

        $story->venue_id = $venue->getKey();
        $story->saveQuietly();

        // Give the venue a hero from one of its weddings if it has none yet.
        if ($this->isBlank($venue->hero_media_id) && filled($story->hero_media_id)) {
            $venue->forceFill(['hero_media_id' => $story->hero_media_id])->saveQuietly();
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'venue';
        $slug = $base;
        $suffix = 2;

        while (Venue::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function cityState(WeddingStory $story): string
    {
        return trim(implode(', ', array_filter([$story->city, $story->state])));
    }

    private function normalizeName(?string $name): string
    {
        return Str::of((string) $name)->lower()->squish()->value();
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
