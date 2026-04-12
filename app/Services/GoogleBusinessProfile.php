<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleBusinessProfile
{
    public function __construct(private readonly GoogleClient $googleClient) {}

    /**
     * Return rating, review count, and recent reviews.
     * Returns null when GBP is not connected or no location is found.
     *
     * @return array{rating: float, reviewCount: int, recentReviews: array<int, array{author: string, rating: int, excerpt: string, date: string}>}|null
     */
    public function snapshot(): ?array
    {
        $client = $this->googleClient->client();

        if ($client === null) {
            return null;
        }

        return Cache::remember('google_business_profile_snapshot', now()->addHours(6), function () use ($client) {
            try {
                $accessToken = $client->getAccessToken()['access_token'] ?? null;

                if (! $accessToken) {
                    return null;
                }

                // Discover accounts
                $accountsResponse = Http::withToken($accessToken)
                    ->get('https://mybusinessaccountmanagement.googleapis.com/v1/accounts');

                if ($accountsResponse->failed()) {
                    return null;
                }

                $accounts = $accountsResponse->json('accounts', []);

                if (empty($accounts)) {
                    return null;
                }

                $accountName = $accounts[0]['name'];

                // Discover locations
                $locationsResponse = Http::withToken($accessToken)
                    ->get("https://mybusinessbusinessinformation.googleapis.com/v1/{$accountName}/locations", [
                        'readMask' => 'name,title,metadata',
                    ]);

                if ($locationsResponse->failed()) {
                    return null;
                }

                $locations = $locationsResponse->json('locations', []);

                if (empty($locations)) {
                    return null;
                }

                $locationName = $locations[0]['name'];

                // Fetch reviews
                $reviewsResponse = Http::withToken($accessToken)
                    ->get("https://mybusiness.googleapis.com/v4/{$locationName}/reviews", [
                        'pageSize' => 5,
                        'orderBy' => 'updateTime desc',
                    ]);

                if ($reviewsResponse->failed()) {
                    return null;
                }

                $reviewData = $reviewsResponse->json();
                $avgRating = (float) ($reviewData['averageRating'] ?? 0);
                $totalCount = (int) ($reviewData['totalReviewCount'] ?? 0);

                $recentReviews = collect($reviewData['reviews'] ?? [])
                    ->map(fn ($r) => [
                        'author' => $r['reviewer']['displayName'] ?? 'Anonymous',
                        'rating' => $this->starRatingToInt($r['starRating'] ?? 'ZERO'),
                        'excerpt' => str($r['comment'] ?? '')->limit(120)->toString(),
                        'date' => isset($r['updateTime'])
                            ? Carbon::parse($r['updateTime'])->format('M j, Y')
                            : '',
                    ])
                    ->all();

                return [
                    'rating' => $avgRating,
                    'reviewCount' => $totalCount,
                    'recentReviews' => $recentReviews,
                ];
            } catch (\Throwable $e) {
                Log::warning('Google Business Profile API error: '.$e->getMessage());

                return null;
            }
        });
    }

    private function starRatingToInt(string $rating): int
    {
        return match ($rating) {
            'ONE' => 1,
            'TWO' => 2,
            'THREE' => 3,
            'FOUR' => 4,
            'FIVE' => 5,
            default => 0,
        };
    }
}
