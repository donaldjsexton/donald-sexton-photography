<?php

namespace Tests\Feature\Services;

use App\Models\SiteSetting;
use App\Services\GoogleClient;
use Google\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_grant_response_marks_the_connection_revoked(): void
    {
        $settings = $this->connectedSettingsWithExpiredToken();

        $service = new class($settings) extends GoogleClient
        {
            protected function refreshAccessToken(Client $client): array
            {
                return ['error' => 'invalid_grant', 'error_description' => 'Bad Request'];
            }
        };

        $this->assertNull($service->client());

        $settings->refresh();
        $this->assertNull($settings->google_refresh_token);
        $this->assertNull($settings->google_access_token);
        $this->assertFalse($settings->googleIsConnected());
    }

    public function test_invalid_grant_exception_marks_the_connection_revoked(): void
    {
        $settings = $this->connectedSettingsWithExpiredToken();

        $service = new class($settings) extends GoogleClient
        {
            protected function refreshAccessToken(Client $client): array
            {
                throw new \RuntimeException('400 Bad Request: {"error": "invalid_grant", "error_description": "Bad Request"}');
            }
        };

        $this->assertNull($service->client());
        $this->assertFalse($settings->refresh()->googleIsConnected());
    }

    public function test_a_transient_refresh_failure_keeps_the_connection(): void
    {
        $settings = $this->connectedSettingsWithExpiredToken();

        $service = new class($settings) extends GoogleClient
        {
            protected function refreshAccessToken(Client $client): array
            {
                throw new \RuntimeException('Connection timed out');
            }
        };

        $this->assertInstanceOf(Client::class, $service->client());

        $settings->refresh();
        $this->assertSame('refresh-token', $settings->google_refresh_token);
        $this->assertTrue($settings->googleIsConnected());
    }

    public function test_a_successful_refresh_stores_the_new_access_token(): void
    {
        $settings = $this->connectedSettingsWithExpiredToken();

        $service = new class($settings) extends GoogleClient
        {
            protected function refreshAccessToken(Client $client): array
            {
                return ['access_token' => 'fresh-token', 'expires_in' => 3600];
            }
        };

        $this->assertInstanceOf(Client::class, $service->client());

        $settings->refresh();
        $this->assertSame('fresh-token', $settings->google_access_token);
        $this->assertGreaterThan(time(), (int) $settings->google_token_expires_at);
    }

    private function connectedSettingsWithExpiredToken(): SiteSetting
    {
        return SiteSetting::create([
            'google_connected_email' => 'studio@example.com',
            'google_access_token' => 'expired-token',
            'google_refresh_token' => 'refresh-token',
            'google_token_expires_at' => time() - 3600,
        ]);
    }
}
