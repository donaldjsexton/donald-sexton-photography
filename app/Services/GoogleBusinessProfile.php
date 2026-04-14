<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleBusinessProfile
{
    public function __construct(private readonly GoogleClient $googleClient) {}

    /**
     * List every account + location the connected Google user can manage,
     * so an admin can pick the right listing when multiples exist.
     *
     * @return array<int, array{account_name: string, account_label: string, locations: array<int, array{name: string, title: string, address: ?string}>}>
     */
    public function listAccountsAndLocations(): array
    {
        $token = $this->accessToken();

        if ($token === null) {
            return [];
        }

        return Cache::remember('gbp_accounts_and_locations', now()->addHour(), function () use ($token) {
            try {
                $accountsResponse = Http::withToken($token)
                    ->get('https://mybusinessaccountmanagement.googleapis.com/v1/accounts');

                if ($accountsResponse->failed()) {
                    Log::warning('GBP accounts list failed: '.$accountsResponse->body());

                    // Cache the empty result for the full hour so we don't hammer the dead endpoint.
                    return [];
                }

                $results = [];

                foreach ($accountsResponse->json('accounts', []) as $account) {
                    $accountName = $account['name'] ?? null;

                    if (! $accountName) {
                        continue;
                    }

                    $locationsResponse = Http::withToken($token)
                        ->get("https://mybusinessbusinessinformation.googleapis.com/v1/{$accountName}/locations", [
                            'readMask' => 'name,title,storefrontAddress',
                            'pageSize' => 100,
                        ]);

                    if ($locationsResponse->failed()) {
                        Log::warning("GBP locations list failed for {$accountName}: ".$locationsResponse->body());

                        continue;
                    }

                    $locations = collect($locationsResponse->json('locations', []))
                        ->map(fn ($loc) => [
                            'name' => $loc['name'] ?? '',
                            'title' => $loc['title'] ?? '(untitled)',
                            'address' => $this->formatAddress($loc['storefrontAddress'] ?? null),
                        ])
                        ->filter(fn ($loc) => $loc['name'] !== '')
                        ->values()
                        ->all();

                    $results[] = [
                        'account_name' => $accountName,
                        'account_label' => $account['accountName'] ?? $account['name'],
                        'locations' => $locations,
                    ];
                }

                return $results;
            } catch (\Throwable $e) {
                Log::warning('Google Business Profile listing error: '.$e->getMessage());

                return [];
            }
        });
    }

    /**
     * Return rating, review count, and recent reviews.
     * Returns null when GBP is not connected, no selection is stored, or the listing is inaccessible.
     *
     * @return array{rating: float, reviewCount: int, recentReviews: array<int, array{author: string, rating: int, excerpt: string, date: string}>}|null
     */
    public function snapshot(): ?array
    {
        $token = $this->accessToken();

        if ($token === null) {
            return null;
        }

        $settings = SiteSetting::current();
        $accountName = $settings->gbp_account_name;
        $locationName = $settings->gbp_location_name;

        // Without an explicit selection, do not call the accounts discovery endpoint —
        // it requires GBP API access approval and hammering it wastes quota.
        if (! $accountName || ! $locationName) {
            return null;
        }

        $cacheKey = 'google_business_profile_snapshot:'.md5($locationName);

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($token, $locationName) {
            try {
                $reviewsResponse = Http::withToken($token)
                    ->get("https://mybusiness.googleapis.com/v4/{$locationName}/reviews", [
                        'pageSize' => 5,
                        'orderBy' => 'updateTime desc',
                    ]);

                if ($reviewsResponse->failed()) {
                    Log::warning("GBP reviews fetch failed for {$locationName}: ".$reviewsResponse->body());

                    return null;
                }

                $reviewData = $reviewsResponse->json();

                return [
                    'rating' => (float) ($reviewData['averageRating'] ?? 0),
                    'reviewCount' => (int) ($reviewData['totalReviewCount'] ?? 0),
                    'recentReviews' => collect($reviewData['reviews'] ?? [])
                        ->map(fn ($r) => [
                            'author' => $r['reviewer']['displayName'] ?? 'Anonymous',
                            'rating' => $this->starRatingToInt($r['starRating'] ?? 'ZERO'),
                            'excerpt' => str($r['comment'] ?? '')->limit(120)->toString(),
                            'date' => isset($r['updateTime']) ? Carbon::parse($r['updateTime'])->format('M j, Y') : '',
                        ])
                        ->all(),
                ];
            } catch (\Throwable $e) {
                Log::warning('Google Business Profile API error: '.$e->getMessage());

                return null;
            }
        });
    }

    public function forgetCaches(): void
    {
        Cache::forget('gbp_accounts_and_locations');

        // Snapshot cache is keyed by location, so forget any stored one.
        $settings = SiteSetting::current();

        if ($settings->gbp_location_name) {
            Cache::forget('google_business_profile_snapshot:'.md5($settings->gbp_location_name));
        }

        // Also the legacy unkeyed snapshot from the prior implementation.
        Cache::forget('google_business_profile_snapshot');
    }

    private function accessToken(): ?string
    {
        $client = $this->googleClient->client();

        return $client?->getAccessToken()['access_token'] ?? null;
    }

    private function formatAddress(?array $address): ?string
    {
        if (! $address) {
            return null;
        }

        return trim(implode(', ', array_filter([
            implode(' ', $address['addressLines'] ?? []),
            $address['locality'] ?? null,
            $address['administrativeArea'] ?? null,
        ]))) ?: null;
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
