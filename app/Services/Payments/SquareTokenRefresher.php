<?php

namespace App\Services\Payments;

use App\Models\SiteSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps a connected tenant's Square OAuth access token alive. Square access
 * tokens expire 30 days after issue; the refresh token is used to mint a new
 * one before (or after) that happens.
 */
class SquareTokenRefresher
{
    private const SQUARE_VERSION = '2025-01-23';

    /**
     * Refresh when the token expires within this many days. Square recommends
     * refreshing at least every seven days.
     */
    private const REFRESH_WINDOW_DAYS = 7;

    /**
     * Refresh the connected token if it is expired or close to expiring.
     */
    public function refreshIfExpiring(SiteSetting $settings): bool
    {
        if (! $settings->squareIsConnected() || blank($settings->square_refresh_token)) {
            return false;
        }

        $expiresAt = $settings->square_token_expires_at;

        if ($expiresAt !== null && $expiresAt->isAfter(now()->addDays(self::REFRESH_WINDOW_DAYS))) {
            return false;
        }

        return $this->refresh($settings);
    }

    /**
     * Exchange the stored refresh token for a new access token.
     */
    public function refresh(SiteSetting $settings): bool
    {
        $oauth = (array) config('payments.gateways.square.oauth');

        if (blank($settings->square_refresh_token) || blank($oauth['client_id'] ?? null) || blank($oauth['client_secret'] ?? null)) {
            return false;
        }

        try {
            $response = Http::withHeaders(['Square-Version' => self::SQUARE_VERSION])
                ->asJson()
                ->post($this->baseUrl().'/oauth2/token', [
                    'client_id' => $oauth['client_id'],
                    'client_secret' => $oauth['client_secret'],
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $settings->square_refresh_token,
                ]);
        } catch (Throwable $e) {
            Log::error('Square token refresh request failed', ['message' => $e->getMessage()]);

            return false;
        }

        if ($response->failed() || blank($response->json('access_token'))) {
            Log::error('Square token refresh was rejected', [
                'status' => $response->status(),
                'errors' => $response->json('errors'),
            ]);

            return false;
        }

        $settings->square_access_token = $response->json('access_token');
        $settings->square_refresh_token = $response->json('refresh_token') ?: $settings->square_refresh_token;
        $settings->square_token_expires_at = $response->json('expires_at')
            ? Carbon::parse($response->json('expires_at'))
            : null;
        $settings->save();

        return true;
    }

    private function baseUrl(): string
    {
        return config('payments.mode') === 'live'
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';
    }
}
