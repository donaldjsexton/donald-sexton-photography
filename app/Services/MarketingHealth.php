<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\JournalPost;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\WeddingStory;

class MarketingHealth
{
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

        return [
            [
                'label' => 'Organic Traffic',
                'value' => 'Not connected',
                'description' => 'Connect Search Console in Settings to populate impressions and clicks.',
                'tone' => 'neutral',
            ],
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
            [
                'label' => 'Reputation',
                'value' => 'Not connected',
                'description' => 'Link Google Business Profile in Settings to surface rating and review activity.',
                'tone' => 'neutral',
            ],
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
