<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

class GoogleOAuthController extends Controller
{
    /**
     * Redirect to Google's OAuth consent screen requesting all required scopes.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(SiteSetting::googleScopes())
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    /**
     * Handle the OAuth callback, persist tokens, and return to settings.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()
                ->route('admin.settings.edit', ['tab' => 'integrations'])
                ->with('status_error', 'Google sign-in failed or was cancelled.');
        }

        $settings = SiteSetting::current();

        $settings->google_connected_email = $googleUser->getEmail();
        $settings->google_access_token = $googleUser->token;
        // Google only returns a refresh token on the first consent; preserve the existing one on re-auth.
        $settings->google_refresh_token = $googleUser->refreshToken ?? $settings->google_refresh_token;
        $settings->google_token_expires_at = time() + ($googleUser->expiresIn ?? 3600);
        $settings->google_granted_scopes = SiteSetting::googleScopes();
        $settings->save();

        return redirect()
            ->route('admin.settings.edit', ['tab' => 'integrations'])
            ->with('status', 'Google account connected: '.$googleUser->getEmail());
    }

    /**
     * Clear all stored Google credentials.
     */
    public function disconnect(): RedirectResponse
    {
        $settings = SiteSetting::current();

        $settings->google_connected_email = null;
        $settings->google_access_token = null;
        $settings->google_refresh_token = null;
        $settings->google_token_expires_at = null;
        $settings->google_granted_scopes = null;
        $settings->save();

        return redirect()
            ->route('admin.settings.edit', ['tab' => 'integrations'])
            ->with('status', 'Google account disconnected.');
    }
}
