<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SquareOAuthController extends Controller
{
    private const SQUARE_VERSION = '2025-01-23';

    /**
     * Send the tenant to Square's authorization screen.
     */
    public function redirect(): RedirectResponse
    {
        $oauth = (array) config('payments.gateways.square.oauth');

        if (blank($oauth['client_id'] ?? null) || blank($oauth['client_secret'] ?? null)) {
            return $this->backToIntegrations()->with('status_error', 'Square OAuth is not configured on this platform.');
        }

        $state = Str::random(40);
        session(['square_oauth_state' => $state]);

        $query = http_build_query([
            'client_id' => $oauth['client_id'],
            'scope' => implode(' ', (array) ($oauth['scopes'] ?? [])),
            'session' => 'false',
            'state' => $state,
            'redirect_uri' => route('admin.settings.square.callback'),
        ]);

        return redirect()->away($this->baseUrl().'/oauth2/authorize?'.$query);
    }

    /**
     * Handle Square's redirect back: exchange the code and store tenant tokens.
     */
    public function callback(Request $request): RedirectResponse
    {
        $expectedState = (string) session()->pull('square_oauth_state', '');
        $state = (string) $request->query('state', '');

        if ($expectedState === '' || ! hash_equals($expectedState, $state)) {
            return $this->backToIntegrations()->with('status_error', 'Square connection could not be verified. Please try again.');
        }

        if ($request->filled('error')) {
            return $this->backToIntegrations()->with('status_error', 'Square authorization was declined.');
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return $this->backToIntegrations()->with('status_error', 'Square did not return an authorization code.');
        }

        $oauth = (array) config('payments.gateways.square.oauth');

        $response = Http::withHeaders(['Square-Version' => self::SQUARE_VERSION])
            ->asJson()
            ->post($this->baseUrl().'/oauth2/token', [
                'client_id' => $oauth['client_id'],
                'client_secret' => $oauth['client_secret'],
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => route('admin.settings.square.callback'),
            ]);

        if ($response->failed() || blank($response->json('access_token'))) {
            return $this->backToIntegrations()->with('status_error', 'Square token exchange failed. Please try again.');
        }

        $settings = SiteSetting::current();
        $settings->square_merchant_id = $response->json('merchant_id');
        $settings->square_access_token = $response->json('access_token');
        $settings->square_refresh_token = $response->json('refresh_token');
        $settings->square_token_expires_at = $response->json('expires_at')
            ? Carbon::parse($response->json('expires_at'))
            : null;
        $settings->square_location_id = $this->resolveLocationId((string) $response->json('access_token'));
        $settings->save();

        return $this->backToIntegrations()->with('status', 'Square account connected.');
    }

    /**
     * Clear the tenant's Square connection.
     */
    public function disconnect(): RedirectResponse
    {
        $settings = SiteSetting::current();
        $settings->square_merchant_id = null;
        $settings->square_access_token = null;
        $settings->square_refresh_token = null;
        $settings->square_token_expires_at = null;
        $settings->square_location_id = null;
        $settings->save();

        return $this->backToIntegrations()->with('status', 'Square account disconnected.');
    }

    private function resolveLocationId(string $accessToken): ?string
    {
        $response = Http::withToken($accessToken)
            ->withHeaders(['Square-Version' => self::SQUARE_VERSION])
            ->get($this->baseUrl().'/v2/locations');

        if ($response->failed()) {
            return null;
        }

        return $response->json('locations.0.id');
    }

    private function baseUrl(): string
    {
        return config('payments.mode') === 'live'
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';
    }

    private function backToIntegrations(): RedirectResponse
    {
        return redirect()->route('admin.settings.edit', ['tab' => 'integrations']);
    }
}
