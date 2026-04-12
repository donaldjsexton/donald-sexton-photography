<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\JournalPost;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\WeddingStory;

class MarketingHealth
{
    public function __construct(
        private readonly GoogleSearchConsole $searchConsole,
        private readonly GoogleBusinessProfile $businessProfile,
    ) {}

    /**
     * @return array{signals: array<int, array{label: string, value: string, description: string, tone: string}>, seoCoverage: array<int, array{label: string, value: string, context: string}>, attribution: array<int, array{label: string, meta: string}>}
     */
    public function snapshot(SiteSetting $siteSettings): array
    {
        return [
            'signals' => $this->signals($siteSettings),
            'seoCoverage' => $this->seoCoverage(),
            'attribution' => $this->attribution(),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, description: string, tone: string}>
     */
    private function signals(SiteSetting $siteSettings): array
    {
        $analyticsConfigured = $siteSettings->analyticsIsConfigured();
        $googleConnected = $siteSettings->googleIsConnected();

        // Search Console
        $scData = null;
        if ($googleConnected && $siteSettings->googleHasScope('https://www.googleapis.com/auth/webmasters.readonly')) {
            $scData = $this->searchConsole->snapshot(rtrim((string) config('app.url'), '/'));
        }

        // Google Business Profile
        $gbpData = null;
        if ($googleConnected) {
            $gbpData = $this->businessProfile->snapshot();
        }

        return [
            $this->organicTrafficSignal($googleConnected, $scData),
            [
                'label' => 'GA4 Analytics',
                'value' => $analyticsConfigured ? 'Connected' : 'Missing',
                'description' => $analyticsConfigured
                    ? 'Measurement ID '.$siteSettings->analyticsMeasurementId().' is tracking site visits.'
                    : 'Add a GA4 measurement ID in Settings to start tracking.',
                'tone' => $analyticsConfigured ? 'positive' : 'warning',
            ],
            [
                'label' => 'Sitemap',
                'value' => 'Published',
                'description' => 'Generated from published pages, stories, and journal posts at /sitemap.xml.',
                'tone' => 'positive',
            ],
            $this->reputationSignal($googleConnected, $gbpData),
        ];
    }

    /**
     * @param  array{impressions: int, clicks: int, position: float, topQueries: array<int, array{query: string, clicks: int, impressions: int}>}|null  $data
     * @return array{label: string, value: string, description: string, tone: string}
     */
    private function organicTrafficSignal(bool $googleConnected, ?array $data): array
    {
        if (! $googleConnected) {
            return [
                'label' => 'Organic Traffic',
                'value' => 'Not connected',
                'description' => 'Connect Google in Settings to populate impressions and clicks.',
                'tone' => 'neutral',
            ];
        }

        if ($data === null) {
            return [
                'label' => 'Organic Traffic',
                'value' => 'No data yet',
                'description' => 'Search Console is connected but returned no data. Verify the site is verified in GSC.',
                'tone' => 'warning',
            ];
        }

        $impressions = number_format($data['impressions']);
        $clicks = number_format($data['clicks']);

        return [
            'label' => 'Organic Traffic',
            'value' => $clicks.' clicks',
            'description' => $impressions.' impressions · avg position '.$data['position'].' (last 28 days)',
            'tone' => $data['clicks'] > 0 ? 'positive' : 'warning',
        ];
    }

    /**
     * @param  array{rating: float, reviewCount: int, recentReviews: array<int, array{author: string, rating: int, excerpt: string, date: string}>}|null  $data
     * @return array{label: string, value: string, description: string, tone: string}
     */
    private function reputationSignal(bool $googleConnected, ?array $data): array
    {
        if (! $googleConnected) {
            return [
                'label' => 'Reputation',
                'value' => 'Not connected',
                'description' => 'Connect Google in Settings to surface rating and review activity.',
                'tone' => 'neutral',
            ];
        }

        if ($data === null) {
            return [
                'label' => 'Reputation',
                'value' => 'No listing found',
                'description' => 'Connected but no Google Business Profile listing was found for this account.',
                'tone' => 'warning',
            ];
        }

        $stars = number_format($data['rating'], 1);

        return [
            'label' => 'Reputation',
            'value' => $stars.' ★',
            'description' => $data['reviewCount'].' reviews on Google Business Profile.',
            'tone' => $data['rating'] >= 4.0 ? 'positive' : 'warning',
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, context: string}>
     */
    private function seoCoverage(): array
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
     * @return array<int, array{label: string, meta: string}>
     */
    private function attribution(): array
    {
        return Inquiry::query()
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
    }
}
