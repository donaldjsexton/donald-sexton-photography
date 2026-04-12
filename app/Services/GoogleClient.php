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

    /**
     * Return a configured, token-refreshed Google client, or null if not connected.
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

    private function buildClient(): Client
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
                $client->fetchAccessTokenWithRefreshToken($this->settings->google_refresh_token);
                $newToken = $client->getAccessToken();

                $this->settings->update([
                    'google_access_token' => $newToken['access_token'],
                    'google_token_expires_at' => time() + ($newToken['expires_in'] ?? 3600),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Google token refresh failed: '.$e->getMessage());
            }
        }

        return $client;
    }
}
