<?php

namespace App\Services;

use App\Models\SiteSetting;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Gmail;
use Google\Service\SearchConsole;
use Illuminate\Support\Facades\Log;

class GoogleClient
{
    private ?Client $client = null;

    public function __construct(private readonly SiteSetting $settings) {}

    public function connectedEmail(): ?string
    {
        return $this->settings->google_connected_email;
    }

    /**
     * @param  array<int, string>  $scopes
     */
    public function hasAnyScope(array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if ($this->settings->googleHasScope($scope)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return a configured, token-refreshed Google client, or null if not
     * connected (including when the refresh token has been revoked).
     */
    public function client(): ?Client
    {
        if (! $this->settings->googleIsConnected()) {
            return null;
        }

        if ($this->client === null) {
            $this->client = $this->buildClient();
        }

        return $this->client;
    }

    /**
     * Return a Gmail service, or null if the gmail.send scope is not granted.
     */
    public function gmail(): ?Gmail
    {
        $client = $this->client();

        if ($client === null || ! $this->settings->googleHasScope('https://www.googleapis.com/auth/gmail.send')) {
            return null;
        }

        return new Gmail($client);
    }

    /**
     * Return a Search Console service, or null if the scope is not granted.
     */
    public function searchConsole(): ?SearchConsole
    {
        $client = $this->client();

        if ($client === null || ! $this->settings->googleHasScope('https://www.googleapis.com/auth/webmasters.readonly')) {
            return null;
        }

        return new SearchConsole($client);
    }

    /**
     * Return a Calendar service, or null if the scope is not granted.
     */
    public function calendar(): ?Calendar
    {
        $client = $this->client();

        if ($client === null || ! $this->settings->googleHasScope('https://www.googleapis.com/auth/calendar')) {
            return null;
        }

        return new Calendar($client);
    }

    private function buildClient(): ?Client
    {
        $client = new Client;
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));

        $client->setAccessToken([
            'access_token' => $this->settings->google_access_token,
            'refresh_token' => $this->settings->google_refresh_token,
            'expires_in' => max(0, (int) $this->settings->google_token_expires_at - time()),
            'token_type' => 'Bearer',
        ]);

        if ($client->isAccessTokenExpired()) {
            try {
                $newToken = $this->refreshAccessToken($client);

                if (($newToken['error'] ?? null) === 'invalid_grant') {
                    $this->markConnectionRevoked();

                    return null;
                }

                if (isset($newToken['access_token'])) {
                    $this->settings->update([
                        'google_access_token' => $newToken['access_token'],
                        'google_token_expires_at' => time() + ($newToken['expires_in'] ?? 3600),
                    ]);
                }
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'invalid_grant')) {
                    $this->markConnectionRevoked();

                    return null;
                }

                Log::warning('Google token refresh failed: '.$e->getMessage());
            }
        }

        return $client;
    }

    /**
     * Exchange the stored refresh token for a fresh access token. Returns the
     * raw token endpoint response, which carries an `error` key on failure.
     *
     * @return array<string, mixed>
     */
    protected function refreshAccessToken(Client $client): array
    {
        return (array) $client->fetchAccessTokenWithRefreshToken($this->settings->google_refresh_token);
    }

    /**
     * An invalid_grant response means the stored refresh token is permanently
     * dead (revoked, expired, or password change). Clear the tokens so every
     * Google-backed service skips its API calls instead of failing on each
     * request, and the admin settings screen prompts for a reconnect.
     */
    private function markConnectionRevoked(): void
    {
        Log::warning('Google refresh token is no longer valid (invalid_grant). Google services are paused until the account is reconnected in Admin → Settings.');

        $this->settings->update([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);
    }
}
