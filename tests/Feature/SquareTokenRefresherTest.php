<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\Payments\SquareTokenRefresher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SquareTokenRefresherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.mode' => 'sandbox',
            'payments.gateways.square.oauth.client_id' => 'app123',
            'payments.gateways.square.oauth.client_secret' => 'secret123',
        ]);
    }

    public function test_refreshes_token_that_expires_within_the_window(): void
    {
        $settings = $this->connectedSettings(expiresAt: now()->addDays(2));

        Http::fake([
            'connect.squareupsandbox.com/oauth2/token' => Http::response([
                'access_token' => 'new-token',
                'refresh_token' => 'new-refresh',
                'expires_at' => now()->addDays(30)->toIso8601String(),
            ]),
        ]);

        $this->assertTrue((new SquareTokenRefresher)->refreshIfExpiring($settings));

        Http::assertSent(fn (Request $request) => $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'old-refresh'
            && $request['client_id'] === 'app123'
            && $request['client_secret'] === 'secret123');

        $fresh = SiteSetting::current();
        $this->assertSame('new-token', $fresh->square_access_token);
        $this->assertSame('new-refresh', $fresh->square_refresh_token);
        $this->assertTrue($fresh->square_token_expires_at->isAfter(now()->addDays(29)));
    }

    public function test_refreshes_token_that_has_already_expired(): void
    {
        $settings = $this->connectedSettings(expiresAt: now()->subDays(3));

        Http::fake([
            'connect.squareupsandbox.com/oauth2/token' => Http::response([
                'access_token' => 'new-token',
                'expires_at' => now()->addDays(30)->toIso8601String(),
            ]),
        ]);

        $this->assertTrue((new SquareTokenRefresher)->refreshIfExpiring($settings));

        $fresh = SiteSetting::current();
        $this->assertSame('new-token', $fresh->square_access_token);
        $this->assertSame('old-refresh', $fresh->square_refresh_token);
    }

    public function test_skips_refresh_when_token_is_not_close_to_expiring(): void
    {
        $settings = $this->connectedSettings(expiresAt: now()->addDays(20));

        Http::fake();

        $this->assertFalse((new SquareTokenRefresher)->refreshIfExpiring($settings));

        Http::assertNothingSent();
        $this->assertSame('old-token', SiteSetting::current()->square_access_token);
    }

    public function test_skips_refresh_when_square_is_not_connected(): void
    {
        Http::fake();

        $this->assertFalse((new SquareTokenRefresher)->refreshIfExpiring(SiteSetting::current()));

        Http::assertNothingSent();
    }

    public function test_keeps_existing_token_when_square_rejects_the_refresh(): void
    {
        $settings = $this->connectedSettings(expiresAt: now()->subDay());

        Http::fake([
            'connect.squareupsandbox.com/oauth2/token' => Http::response([
                'errors' => [['category' => 'AUTHENTICATION_ERROR', 'code' => 'UNAUTHORIZED']],
            ], 401),
        ]);

        $this->assertFalse((new SquareTokenRefresher)->refresh($settings));

        $this->assertSame('old-token', SiteSetting::current()->square_access_token);
    }

    public function test_does_not_refresh_without_oauth_credentials(): void
    {
        config(['payments.gateways.square.oauth.client_secret' => null]);
        $settings = $this->connectedSettings(expiresAt: now()->subDay());

        Http::fake();

        $this->assertFalse((new SquareTokenRefresher)->refresh($settings));

        Http::assertNothingSent();
    }

    private function connectedSettings(\DateTimeInterface $expiresAt): SiteSetting
    {
        return SiteSetting::query()->create([
            'square_merchant_id' => 'M1',
            'square_access_token' => 'old-token',
            'square_refresh_token' => 'old-refresh',
            'square_token_expires_at' => $expiresAt,
            'square_location_id' => 'LOC1',
        ]);
    }
}
