<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Support\LocationPageDraft;
use App\Tenancy\CurrentSite;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seo:scaffold-location
    {city : City name, e.g. "Tampa"}
    {--state=FL : Two-letter state code}
    {--region= : Optional broader region label, e.g. "Tampa Bay"}
    {--dry-run : Show the draft that would be created without saving}
')]
#[Description('Scaffold a draft "[City] Wedding Photographer" landing page to consolidate local search intent.')]
class ScaffoldLocationPageCommand extends Command
{
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $city = trim((string) $this->argument('city'));

        if ($city === '') {
            $this->error('A city name is required.');

            return self::FAILURE;
        }

        $state = trim((string) $this->option('state')) ?: null;
        $region = trim((string) $this->option('region')) ?: null;

        $draft = LocationPageDraft::build(city: $city, state: $state, region: $region);
        $slug = $this->resolveSlug($draft->slug);

        $this->info(sprintf('%s location page for "%s".', $isDryRun ? 'Previewing' : 'Creating', $draft->city));
        $this->line('  slug:        '.$slug);
        $this->line('  seo_title:   '.$draft->seoTitle);
        $this->line('  description: '.$draft->seoDescription);
        $this->line('  faqs:        '.count($draft->faqs));

        if ($isDryRun) {
            $this->newLine();
            $this->info('Dry run complete. Nothing was saved.');

            return self::SUCCESS;
        }

        $page = new Page;
        $page->fill([
            'title' => $draft->title,
            'slug' => $slug,
            'template' => 'location',
            'status' => 'draft',
            'excerpt' => $draft->excerpt,
            'body' => $draft->body,
            'seo_title' => $draft->seoTitle,
            'seo_description' => $draft->seoDescription,
            'faqs' => $draft->faqs,
        ]);
        $page->sort_order = 0;
        $page->save();

        $this->newLine();
        $this->info(sprintf('Drafted page #%d. Add a hero image, tighten the copy, and publish from the admin.', $page->id));

        return self::SUCCESS;
    }

    private function resolveSlug(string $candidate): string
    {
        $siteId = app(CurrentSite::class)->id();
        $slug = $candidate;
        $suffix = 2;

        while (Page::query()->where('site_id', $siteId)->where('slug', $slug)->exists()) {
            $slug = $candidate.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
