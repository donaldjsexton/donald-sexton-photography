<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageSetting;
use App\Models\ImportRun;
use App\Models\Inquiry;
use App\Models\JournalPost;
use App\Models\Media;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\WeddingStory;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $siteSettings = SiteSetting::current();
        $latestFailedImport = ImportRun::query()
            ->where('status', 'failed')
            ->latest()
            ->first();

        return view('admin.dashboard', [
            'primaryStats' => [
                [
                    'label' => 'Published Stories',
                    'value' => WeddingStory::published()->count(),
                    'meta' => WeddingStory::query()->count().' total records',
                ],
                [
                    'label' => 'Journal Entries',
                    'value' => JournalPost::published()->count(),
                    'meta' => JournalPost::query()->count().' total records',
                ],
                [
                    'label' => 'Media Library',
                    'value' => Media::query()->count(),
                    'meta' => 'Local image assets ready for editorial use',
                ],
                [
                    'label' => 'New Inquiries',
                    'value' => Inquiry::query()->where('created_at', '>=', now()->startOfMonth())->count(),
                    'meta' => 'Captured since '.now()->startOfMonth()->format('M j'),
                ],
            ],
            'systemPanels' => [
                [
                    'label' => 'Analytics',
                    'value' => $siteSettings->analyticsIsConfigured() ? 'Connected' : 'Missing',
                    'tone' => $siteSettings->analyticsIsConfigured() ? 'positive' : 'warning',
                    'description' => $siteSettings->analyticsIsConfigured()
                        ? 'GA4 is ready with measurement ID '.$siteSettings->analyticsMeasurementId().'.'
                        : 'No GA4 measurement ID is saved yet.',
                ],
                [
                    'label' => 'Homepage Curation',
                    'value' => $this->homepageStatusLabel(),
                    'tone' => HomepageSetting::query()->exists() ? 'positive' : 'neutral',
                    'description' => HomepageSetting::query()->exists()
                        ? 'Hero copy and featured story selections are saved.'
                        : 'Homepage content is still using safe fallbacks.',
                ],
                [
                    'label' => 'Import Pipeline',
                    'value' => ImportRun::query()->count(),
                    'tone' => $latestFailedImport ? 'warning' : 'positive',
                    'description' => $latestFailedImport
                        ? 'Latest failed run: '.ucfirst($latestFailedImport->source_type).' on '.$latestFailedImport->created_at?->format('M j, Y g:i A')
                        : 'No failed imports are currently recorded.',
                ],
            ],
            'contentRadar' => [
                [
                    'label' => 'Pages',
                    'value' => Page::published()->count(),
                    'context' => Page::query()->where('status', 'draft')->count().' drafts',
                ],
                [
                    'label' => 'Wedding stories',
                    'value' => WeddingStory::query()->where('status', 'draft')->count(),
                    'context' => 'Drafts waiting for final polish',
                ],
                [
                    'label' => 'Journal drafts',
                    'value' => JournalPost::query()->where('status', 'draft')->count(),
                    'context' => 'Posts not yet published',
                ],
                [
                    'label' => 'Import runs this month',
                    'value' => ImportRun::query()->where('created_at', '>=', now()->startOfMonth())->count(),
                    'context' => 'Sync and migration activity',
                ],
            ],
            'quickActions' => [
                [
                    'label' => 'Tune Homepage',
                    'href' => route('admin.homepage.edit'),
                    'description' => 'Update hero copy, featured stories, and curated journal selections.',
                ],
                [
                    'label' => 'Open Settings',
                    'href' => route('admin.settings.edit'),
                    'description' => 'Manage analytics and import operations from one place.',
                ],
                [
                    'label' => 'Review Media',
                    'href' => route('admin.media.index'),
                    'description' => 'Audit focal points, alt text, and uploaded image inventory.',
                ],
            ],
            'homepageSetting' => HomepageSetting::query()->first(),
            'recentInquiries' => Inquiry::query()->latest()->limit(5)->get(),
            'recentImports' => ImportRun::query()->latest()->limit(5)->get(),
            'latestFailedImport' => $latestFailedImport,
        ]);
    }

    private function homepageStatusLabel(): string
    {
        $setting = HomepageSetting::query()->first();

        if (! $setting) {
            return 'Fallback';
        }

        if (filled($setting->hero_heading) || filled($setting->hero_subheading)) {
            return 'Curated';
        }

        return 'Basic';
    }
}
