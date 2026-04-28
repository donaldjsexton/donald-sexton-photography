<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookedJob;
use App\Models\HomepageSetting;
use App\Models\ImportRun;
use App\Models\Inquiry;
use App\Models\JournalPost;
use App\Models\Media;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\WeddingStory;
use App\Services\CrmMetrics;
use App\Services\MarketingHealth;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly CrmMetrics $crmMetrics,
        private readonly MarketingHealth $marketingHealth,
    ) {}

    public function __invoke(): View
    {
        $siteSettings = SiteSetting::current();
        $latestFailedImport = ImportRun::query()
            ->where('status', 'failed')
            ->latest()
            ->first();

        return view('admin.dashboard', [
            'businessPulse' => $this->crmMetrics->pulse(),
            'marketingHealth' => $this->marketingHealth->snapshot($siteSettings),
            'contentOps' => $this->contentOpsData($siteSettings, $latestFailedImport),
            'quickActions' => $this->quickActions(),
            'thisWeek' => $this->thisWeekJobs(),
            'needsReply' => $this->needsReplyInquiries(),
            'recentInquiries' => Inquiry::query()->where('status', '!=', 'archived')->latest()->limit(5)->get(),
            'recentImports' => ImportRun::query()->latest()->limit(5)->get(),
        ]);
    }

    /**
     * Booked jobs in the next 7 days (today through today+6).
     *
     * @return Collection<int, BookedJob>
     */
    private function thisWeekJobs(): Collection
    {
        return BookedJob::query()
            ->whereBetween('event_date', [today(), today()->copy()->addDays(6)])
            ->where('status', 'confirmed')
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->limit(10)
            ->get();
    }

    /**
     * Inquiries that have not yet been responded to. Returns count + oldest waiting.
     *
     * @return array{count: int, oldestHours: ?int, items: Collection<int, Inquiry>}
     */
    private function needsReplyInquiries(): array
    {
        $items = Inquiry::query()
            ->whereNull('first_responded_at')
            ->whereIn('status', ['new', 'active'])
            ->orderBy('created_at')
            ->limit(5)
            ->get();

        $count = Inquiry::query()
            ->whereNull('first_responded_at')
            ->whereIn('status', ['new', 'active'])
            ->count();

        $oldest = $items->first();

        return [
            'count' => $count,
            'oldestHours' => $oldest && $oldest->created_at
                ? (int) $oldest->created_at->diffInHours(now())
                : null,
            'items' => $items,
        ];
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
                'label' => 'New Inquiry',
                'href' => route('admin.inquiries.create'),
                'description' => 'Log a lead from a referral, call, or DM that did not come through the form.',
            ],
            [
                'label' => 'Open Calendar',
                'href' => route('admin.booked-jobs.index'),
                'description' => 'Confirmed shoots and upcoming weeks at a glance.',
            ],
            [
                'label' => 'Edit Homepage',
                'href' => route('admin.homepage.edit'),
                'description' => 'Update hero copy, featured stories, and curated journal selections.',
            ],
            [
                'label' => 'Review Media',
                'href' => route('admin.media.index'),
                'description' => 'Audit focal points, alt text, and uploaded image inventory.',
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
