<?php

namespace App\Services;

use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoogleSearchConsole
{
    public function __construct(private readonly GoogleClient $googleClient) {}

    /**
     * Return impressions, clicks, and average position for the last 28 days.
     * Returns null when Search Console is not connected.
     *
     * @return array{impressions: int, clicks: int, position: float, topQueries: array<int, array{query: string, clicks: int, impressions: int}>}|null
     */
    public function snapshot(string $siteUrl): ?array
    {
        $service = $this->googleClient->searchConsole();

        if ($service === null) {
            return null;
        }

        $cacheKey = 'google_search_console_snapshot_'.md5($siteUrl);

        return Cache::remember($cacheKey, now()->addHour(), function () use ($service, $siteUrl) {
            try {
                $endDate = now()->subDays(3)->toDateString(); // GSC data lags ~3 days
                $startDate = now()->subDays(31)->toDateString();

                // Overall totals
                $totalsRequest = new SearchAnalyticsQueryRequest;
                $totalsRequest->setStartDate($startDate);
                $totalsRequest->setEndDate($endDate);
                $totalsRequest->setRowLimit(1);

                $totals = $service->searchanalytics->query($siteUrl, $totalsRequest);
                $rows = $totals->getRows() ?? [];
                $impressions = array_sum(array_map(fn ($r) => (int) $r->getImpressions(), $rows));
                $clicks = array_sum(array_map(fn ($r) => (int) $r->getClicks(), $rows));
                $position = count($rows) > 0 ? round((float) $rows[0]->getPosition(), 1) : 0.0;

                // Top queries
                $queriesRequest = new SearchAnalyticsQueryRequest;
                $queriesRequest->setStartDate($startDate);
                $queriesRequest->setEndDate($endDate);
                $queriesRequest->setDimensions(['query']);
                $queriesRequest->setRowLimit(5);

                $queriesResponse = $service->searchanalytics->query($siteUrl, $queriesRequest);
                $topQueries = collect($queriesResponse->getRows() ?? [])
                    ->map(fn ($r) => [
                        'query' => $r->getKeys()[0] ?? '',
                        'clicks' => (int) $r->getClicks(),
                        'impressions' => (int) $r->getImpressions(),
                    ])
                    ->all();

                return compact('impressions', 'clicks', 'position', 'topQueries');
            } catch (\Throwable $e) {
                Log::warning('Search Console API error: '.$e->getMessage());

                return null;
            }
        });
    }
}
