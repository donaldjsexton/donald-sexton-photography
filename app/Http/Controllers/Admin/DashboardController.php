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
            'businessPulse' => $this->businessPulseData(),
            'marketingHealth' => $this->marketingHealthData($siteSettings),
            'contentOps' => $this->contentOpsData($siteSettings, $latestFailedImport),
            'quickActions' => $this->quickActions(),
            'recentInquiries' => Inquiry::query()->latest()->limit(5)->get(),
            'recentImports' => ImportRun::query()->latest()->limit(5)->get(),
        ]);
    }

    /**
     * @return array{stats: array<int, array{label: string, value: string, meta: string}>, funnel: array<int, array{label: string, value: int, context: string}>, topSources: array<int, array{label: string, meta: string}>}
     */
    private function businessPulseData(): array
    {
        $startOfWeek = now()->startOfWeek();
        $startOfPreviousWeek = now()->subWeek()->startOfWeek();
        $startOfYear = now()->startOfYear();

        $thisWeek = Inquiry::query()->where('created_at', '>=', $startOfWeek)->count();
        $lastWeek = Inquiry::query()
            ->whereBetween('created_at', [$startOfPreviousWeek, $startOfWeek])
            ->count();

        $activePipeline = Inquiry::query()
            ->whereIn('status', ['new', 'active', 'follow_up'])
            ->count();

        $bookedYtd = Inquiry::query()
            ->where('status', 'booked')
            ->where('created_at', '>=', $startOfYear)
            ->count();

        $totalYtd = Inquiry::query()
            ->where('created_at', '>=', $startOfYear)
            ->whereNot('status', 'archived')
            ->count();

        $conversionRate = $totalYtd > 0
            ? round(($bookedYtd / $totalYtd) * 100)
            : 0;

        $weekDelta = $thisWeek - $lastWeek;
        $weekDeltaMeta = $lastWeek === 0 && $thisWeek === 0
            ? 'No inquiries last week either.'
            : ($weekDelta >= 0
                ? '+'.$weekDelta.' vs last week ('.$lastWeek.').'
                : $weekDelta.' vs last week ('.$lastWeek.').');

        $stats = [
            [
                'label' => 'New This Week',
                'value' => (string) $thisWeek,
                'meta' => $weekDeltaMeta,
            ],
            [
                'label' => 'Active Pipeline',
                'value' => (string) $activePipeline,
                'meta' => 'New, active, and follow-up inquiries combined.',
            ],
            [
                'label' => 'Booked YTD',
                'value' => (string) $bookedYtd,
                'meta' => $totalYtd > 0
                    ? $conversionRate.'% conversion rate on '.$totalYtd.' live leads.'
                    : 'No inquiries captured this year yet.',
            ],
            [
                'label' => 'Avg Response Time',
                'value' => '—',
                'meta' => 'Needs a first_contacted_at column on inquiries to compute.',
            ],
        ];

        $funnelLabels = Inquiry::statusOptions();
        $funnelCounts = Inquiry::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $funnelContexts = [
            'new' => 'Waiting for first review.',
            'active' => 'Currently in conversation.',
            'follow_up' => 'Scheduled to circle back.',
            'booked' => 'Contract signed.',
            'archived' => 'Closed or disqualified.',
        ];

        $funnel = [];
        foreach ($funnelLabels as $status => $label) {
            $funnel[] = [
                'label' => $label,
                'value' => (int) ($funnelCounts[$status] ?? 0),
                'context' => $funnelContexts[$status] ?? '',
            ];
        }

        $topSources = Inquiry::query()
            ->selectRaw('source, count(*) as total')
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->groupBy('source')
            ->orderByDesc('total')
            ->limit(4)
            ->get()
            ->map(fn ($row) => [
                'label' => str($row->source)->replace('_', ' ')->headline()->toString(),
                'meta' => $row->total.' inquiries tracked.',
            ])
            ->all();

        return [
            'stats' => $stats,
            'funnel' => $funnel,
            'topSources' => $topSources,
        ];
    }

    /**
     * @return array{signals: array<int, array{label: string, value: string, description: string, tone: string}>, seoCoverage: array<int, array{label: string, value: string, context: string}>, attribution: array<int, array{label: string, meta: string}>}
     */
    private function marketingHealthData(SiteSetting $siteSettings): array
    {
        $seoCoverage = $this->seoCoverageBreakdown();

        $signals = [
            [
                'label' => 'Organic Traffic',
                'value' => 'Not connected',
                'description' => 'Connect Search Console in Settings to populate impressions and clicks.',
                'tone' => 'neutral',
            ],
            [
                'label' => 'GA4 Analytics',
                'value' => $siteSettings->analyticsIsConfigured() ? 'Connected' : 'Missing',
                'description' => $siteSettings->analyticsIsConfigured()
                    ? 'Measurement ID '.$siteSettings->analyticsMeasurementId().' is tracking site visits.'
                    : 'Add a GA4 measurement ID in Settings to start tracking.',
                'tone' => $siteSettings->analyticsIsConfigured() ? 'positive' : 'warning',
            ],
            [
                'label' => 'Sitemap',
                'value' => 'Published',
                'description' => 'Generated from published pages, stories, and journal posts at /sitemap.xml.',
                'tone' => 'positive',
            ],
            [
                'label' => 'Broken Links',
                'value' => '—',
                'description' => 'Surface internal 404s once a link auditor is in place.',
                'tone' => 'neutral',
            ],
        ];

        $attribution = Inquiry::query()
            ->whereNotNull('utm_source')
            ->where('utm_source', '!=', '')
            ->where('created_at', '>=', now()->subDays(90))
            ->selectRaw('utm_source, count(*) as total')
            ->groupBy('utm_source')
            ->orderByDesc('total')
            ->limit(4)
            ->get()
            ->map(fn ($row) => [
                'label' => str($row->utm_source)->headline()->toString(),
                'meta' => $row->total.' tagged inquiries in the last 90 days.',
            ])
            ->all();

        return [
            'signals' => $signals,
            'seoCoverage' => $seoCoverage,
            'attribution' => $attribution,
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, context: string}>
     */
    private function seoCoverageBreakdown(): array
    {
        $types = [
            'Pages' => Page::query(),
            'Wedding Stories' => WeddingStory::query(),
            'Journal Posts' => JournalPost::query(),
        ];

        $rows = [];

        foreach ($types as $label => $query) {
            $total = (clone $query)->count();
            $withMeta = (clone $query)
                ->whereNotNull('seo_title')
                ->where('seo_title', '!=', '')
                ->whereNotNull('seo_description')
                ->where('seo_description', '!=', '')
                ->count();

            $coverage = $total > 0 ? round(($withMeta / $total) * 100) : 0;

            $rows[] = [
                'label' => $label,
                'value' => $coverage.'%',
                'context' => $withMeta.' of '.$total.' records have title and description.',
            ];
        }

        return $rows;
    }

    /**
     * @return array{stats: array<int, array{label: string, value: string, meta: string}>, systemPanels: array<int, array{label: string, value: string, description: string, tone: string}>, radar: array<int, array{label: string, value: int, context: string}>}
     */
    private function contentOpsData(SiteSetting $siteSettings, ?ImportRun $latestFailedImport): array
    {
        $stats = [
            [
                'label' => 'Published Stories',
                'value' => (string) WeddingStory::published()->count(),
                'meta' => WeddingStory::query()->count().' total records.',
            ],
            [
                'label' => 'Journal Entries',
                'value' => (string) JournalPost::published()->count(),
                'meta' => JournalPost::query()->count().' total records.',
            ],
            [
                'label' => 'Media Library',
                'value' => (string) Media::query()->count(),
                'meta' => 'Local assets ready for editorial use.',
            ],
            [
                'label' => 'New Inquiries (MTD)',
                'value' => (string) Inquiry::query()
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count(),
                'meta' => 'Captured since '.now()->startOfMonth()->format('M j').'.',
            ],
        ];

        $systemPanels = [
            [
                'label' => 'Homepage Curation',
                'value' => $this->homepageStatusLabel(),
                'tone' => HomepageSetting::query()->exists() ? 'positive' : 'neutral',
                'description' => HomepageSetting::query()->exists()
                    ? 'Hero copy and featured story selections are saved.'
                    : 'Homepage content is still using safe fallbacks.',
            ],
            [
                'label' => 'Imports',
                'value' => (string) ImportRun::query()->count(),
                'tone' => $latestFailedImport ? 'warning' : 'positive',
                'description' => $latestFailedImport
                    ? 'Latest failed run: '.ucfirst($latestFailedImport->source_type).' on '.$latestFailedImport->created_at?->format('M j, Y g:i A').'.'
                    : 'No failed imports are currently recorded.',
            ],
        ];

        $radar = [
            [
                'label' => 'Page drafts',
                'value' => Page::query()->where('status', 'draft')->count(),
                'context' => 'Unpublished pages awaiting review.',
            ],
            [
                'label' => 'Wedding story drafts',
                'value' => WeddingStory::query()->where('status', 'draft')->count(),
                'context' => 'Drafts waiting for final polish.',
            ],
            [
                'label' => 'Journal drafts',
                'value' => JournalPost::query()->where('status', 'draft')->count(),
                'context' => 'Posts not yet published.',
            ],
            [
                'label' => 'Import runs this month',
                'value' => ImportRun::query()
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count(),
                'context' => 'Sync and migration activity.',
            ],
        ];

        return [
            'stats' => $stats,
            'systemPanels' => $systemPanels,
            'radar' => $radar,
        ];
    }

    /**
     * @return array<int, array{label: string, href: string, description: string}>
     */
    private function quickActions(): array
    {
        return [
            [
                'label' => 'Edit Homepage',
                'href' => route('admin.homepage.edit'),
                'description' => 'Update hero copy, featured stories, and curated journal selections.',
            ],
            [
                'label' => 'Review Inquiries',
                'href' => route('admin.inquiries.index'),
                'description' => 'Sort new leads, update statuses, and keep the pipeline current.',
            ],
            [
                'label' => 'Review Media',
                'href' => route('admin.media.index'),
                'description' => 'Audit focal points, alt text, and uploaded image inventory.',
            ],
            [
                'label' => 'Open Settings',
                'href' => route('admin.settings.edit'),
                'description' => 'Manage analytics and import operations from one place.',
            ],
        ];
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
