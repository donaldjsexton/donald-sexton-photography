<?php

namespace Tests\Feature\Services;

use App\Models\SiteSetting;
use App\Services\GoogleBusinessProfile;
use App\Services\GoogleClient;
use Google\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleBusinessProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SiteSetting::create([
            'google_connected_email' => 'studio@example.com',
            'google_access_token' => 'access-token-123',
            'google_refresh_token' => 'refresh-token-123',
            'google_token_expires_at' => time() + 3600,
            'google_granted_scopes' => ['https://www.googleapis.com/auth/business.manage'],
            'gbp_account_name' => 'accounts/111',
            'gbp_location_name' => 'accounts/111/locations/222',
        ]);

        // Stub GoogleClient so the service receives a usable access token
        // without exercising the real OAuth flow.
        $this->app->bind(GoogleClient::class, function () {
            $client = new Client;
            $client->setAccessToken([
                'access_token' => 'access-token-123',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
                'created' => time(),
            ]);

            return new class($client) extends GoogleClient
            {
                public function __construct(private Client $stubClient)
                {
                    // Skip parent constructor - we don't need SiteSetting injection here.
                }

                public function client(): ?Client
                {
                    return $this->stubClient;
                }
            };
        });
    }

    public function test_snapshot_persists_fresh_data_to_site_settings(): void
    {
        Http::fake([
            'mybusiness.googleapis.com/v4/*' => Http::response([
                'averageRating' => 4.9,
                'totalReviewCount' => 37,
                'reviews' => [
                    [
                        'reviewer' => ['displayName' => 'Avery K.'],
                        'starRating' => 'FIVE',
                        'comment' => 'Calm, kind, and the photos are unreal.',
                        'updateTime' => '2026-03-05T00:00:00Z',
                    ],
                ],
            ]),
        ]);

        $snapshot = app(GoogleBusinessProfile::class)->snapshot();

        $this->assertNotNull($snapshot);
        $this->assertSame(4.9, $snapshot['rating']);
        $this->assertSame(37, $snapshot['reviewCount']);
        $this->assertSame('Avery K.', $snapshot['recentReviews'][0]['author']);

        $persisted = SiteSetting::current();
        $this->assertSame(37, (int) $persisted->gbp_snapshot['reviewCount']);
        $this->assertNotNull($persisted->gbp_snapshot_fetched_at);
    }

    public function test_snapshot_falls_back_to_persisted_data_when_api_fails(): void
    {
        SiteSetting::current()->forceFill([
            'gbp_snapshot' => [
                'rating' => 4.8,
                'reviewCount' => 21,
                'recentReviews' => [
                    [
                        'author' => 'Cached Couple',
                        'rating' => 5,
                        'excerpt' => 'A persisted excerpt.',
                        'date' => 'Feb 1, 2026',
                    ],
                ],
            ],
            'gbp_snapshot_fetched_at' => now()->subDay(),
        ])->save();

        Cache::flush();

        Http::fake([
            'mybusiness.googleapis.com/v4/*' => Http::response('rate limited', 429),
        ]);

        $snapshot = app(GoogleBusinessProfile::class)->snapshot();

        $this->assertNotNull($snapshot);
        $this->assertSame(21, $snapshot['reviewCount']);
        $this->assertSame('Cached Couple', $snapshot['recentReviews'][0]['author']);
    }

    public function test_snapshot_returns_null_when_no_account_or_location_selected(): void
    {
        SiteSetting::current()->forceFill([
            'gbp_account_name' => null,
            'gbp_location_name' => null,
        ])->save();

        $this->assertNull(app(GoogleBusinessProfile::class)->snapshot());
    }
}
