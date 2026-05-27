<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportRun;
use App\Models\SiteSetting;
use App\Services\GoogleBusinessProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(GoogleBusinessProfile $businessProfile): View
    {
        $siteSettings = SiteSetting::current();
        $googleConnected = $siteSettings->googleIsConnected();
        $analyticsId = $siteSettings->analyticsMeasurementId();

        $googleScopes = [
            ['label' => 'Gmail (send email)', 'scope' => 'https://www.googleapis.com/auth/gmail.send'],
            ['label' => 'Search Console (organic traffic)', 'scope' => 'https://www.googleapis.com/auth/webmasters.readonly'],
            ['label' => 'Business Profile (reviews & rating)', 'scope' => 'https://www.googleapis.com/auth/business.manage'],
            ['label' => 'Calendar (booking events)', 'scope' => 'https://www.googleapis.com/auth/calendar'],
        ];

        $gbpListing = [];

        if ($googleConnected && $siteSettings->googleHasScope('https://www.googleapis.com/auth/business.manage')) {
            $gbpListing = $businessProfile->listAccountsAndLocations();
        }

        $activeScopeCount = 0;

        foreach ($googleScopes as $entry) {
            if ($siteSettings->googleHasScope($entry['scope'])) {
                $activeScopeCount++;
            }
        }

        $analyticsStats = [
            [
                'label' => 'Tracking',
                'value' => $analyticsId ? 'Live' : 'Off',
                'meta' => $analyticsId ? 'Public pages are reporting to GA4.' : 'Add a measurement ID to turn it on.',
            ],
            [
                'label' => 'Measurement ID',
                'value' => $analyticsId ?: '—',
                'meta' => $analyticsId ? 'Embedded on every public page.' : 'No ID saved yet.',
            ],
            [
                'label' => 'Last Saved',
                'value' => $siteSettings->updated_at?->diffForHumans() ?: 'Never',
                'meta' => 'When the platform settings were last updated.',
            ],
        ];

        $integrationsStats = [
            [
                'label' => 'Connection',
                'value' => $googleConnected ? 'Connected' : 'Not connected',
                'meta' => $googleConnected ? 'Google sign-in is active for this site.' : 'Connect Google to enable services.',
            ],
            [
                'label' => 'Active Services',
                'value' => $googleConnected ? $activeScopeCount.' of '.count($googleScopes) : '—',
                'meta' => 'Scopes approved on this Google account.',
            ],
            [
                'label' => 'Account',
                'value' => $googleConnected ? ($siteSettings->google_connected_email ?: '—') : '—',
                'meta' => 'The Google login used for every service.',
            ],
        ];

        $importsStats = [
            [
                'label' => 'Total Runs',
                'value' => (string) ImportRun::query()->count(),
                'meta' => 'All import jobs recorded so far.',
            ],
            [
                'label' => 'Completed',
                'value' => (string) ImportRun::query()->where('status', 'completed')->count(),
                'meta' => 'Jobs that finished without errors.',
            ],
            [
                'label' => 'Failed',
                'value' => (string) ImportRun::query()->where('status', 'failed')->count(),
                'meta' => 'Jobs that ended with an error.',
            ],
        ];

        $recentImportRuns = ImportRun::query()
            ->latest()
            ->limit(6)
            ->get();

        return view('admin.settings.edit', [
            'siteSettings' => $siteSettings,
            'gbpListing' => $gbpListing,
            'resolvedAnalyticsMeasurementId' => $analyticsId,
            'googleConnected' => $googleConnected,
            'googleScopes' => $googleScopes,
            'analyticsStats' => $analyticsStats,
            'integrationsStats' => $integrationsStats,
            'importsStats' => $importsStats,
            'recentImportRuns' => $recentImportRuns,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'google_analytics_measurement_id' => ['nullable', 'string', 'max:32', 'regex:/^G-[A-Z0-9]+$/i'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'pinterest_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'tiktok_url' => ['nullable', 'url', 'max:255'],
            'x_url' => ['nullable', 'url', 'max:255'],
            'google_site_verification' => ['nullable', 'string', 'max:255'],
            'bing_site_verification' => ['nullable', 'string', 'max:255'],
            'pinterest_site_verification' => ['nullable', 'string', 'max:255'],
            'indexnow_key' => ['nullable', 'string', 'regex:/^[a-f0-9]{8,128}$/i'],
            'business_phone' => ['nullable', 'string', 'max:32'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'business_street_address' => ['nullable', 'string', 'max:255'],
            'business_locality' => ['nullable', 'string', 'max:128'],
            'business_region' => ['nullable', 'string', 'max:64'],
            'business_postal_code' => ['nullable', 'string', 'max:32'],
            'business_country' => ['nullable', 'string', 'size:2'],
            'business_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'business_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'business_hours_note' => ['nullable', 'string', 'max:255'],
            'business_price_range' => ['nullable', 'string', 'max:16'],
        ], [
            'google_analytics_measurement_id.regex' => 'Use a valid GA4 measurement ID like G-ABC123XYZ.',
            'indexnow_key.regex' => 'IndexNow key must be 8–128 lowercase hex characters.',
        ]);

        $siteSettings = SiteSetting::query()->first() ?? new SiteSetting;

        if ($request->has('google_analytics_measurement_id')) {
            $siteSettings->google_analytics_measurement_id = filled($validated['google_analytics_measurement_id'] ?? null)
                ? strtoupper(trim((string) $validated['google_analytics_measurement_id']))
                : null;
        }

        foreach (['instagram_url', 'pinterest_url', 'facebook_url', 'youtube_url', 'tiktok_url', 'x_url'] as $field) {
            if ($request->has($field)) {
                $siteSettings->{$field} = filled($validated[$field] ?? null) ? trim((string) $validated[$field]) : null;
            }
        }

        foreach (['google_site_verification', 'bing_site_verification', 'pinterest_site_verification'] as $field) {
            if ($request->has($field)) {
                $siteSettings->{$field} = filled($validated[$field] ?? null) ? trim((string) $validated[$field]) : null;
            }
        }

        if ($request->has('indexnow_key')) {
            $siteSettings->indexnow_key = filled($validated['indexnow_key'] ?? null)
                ? strtolower(trim((string) $validated['indexnow_key']))
                : null;
        }

        $businessStringFields = [
            'business_phone',
            'business_email',
            'business_street_address',
            'business_locality',
            'business_region',
            'business_postal_code',
            'business_hours_note',
            'business_price_range',
        ];

        foreach ($businessStringFields as $field) {
            if ($request->has($field)) {
                $siteSettings->{$field} = filled($validated[$field] ?? null) ? trim((string) $validated[$field]) : null;
            }
        }

        if ($request->has('business_country')) {
            $siteSettings->business_country = filled($validated['business_country'] ?? null)
                ? strtoupper(trim((string) $validated['business_country']))
                : null;
        }

        foreach (['business_latitude', 'business_longitude'] as $field) {
            if ($request->has($field)) {
                $value = $validated[$field] ?? null;
                $siteSettings->{$field} = ($value === null || $value === '') ? null : (float) $value;
            }
        }

        $siteSettings->save();

        return redirect()
            ->route('admin.settings.edit', ['tab' => $request->input('return_tab', 'analytics')])
            ->with('status', 'Platform settings updated.');
    }

    public function updateBusinessProfile(Request $request, GoogleBusinessProfile $businessProfile): RedirectResponse
    {
        $validated = $request->validate([
            'gbp_selection' => ['nullable', 'string'],
            'gbp_manual_location' => ['nullable', 'string', 'max:255'],
        ]);

        $selection = trim((string) ($validated['gbp_selection'] ?? ''));
        $manual = trim((string) ($validated['gbp_manual_location'] ?? ''));

        $siteSettings = SiteSetting::query()->first() ?? new SiteSetting;

        if ($manual !== '') {
            // Expected format: accounts/{id}/locations/{id}
            if (! preg_match('#^accounts/[^/]+/locations/[^/]+$#', $manual)) {
                return redirect()
                    ->route('admin.settings.edit', ['tab' => 'integrations'])
                    ->with('status_error', 'Manual resource name must look like accounts/123/locations/456.');
            }

            [, $accountPart, , $locationPart] = explode('/', $manual);
            $siteSettings->gbp_account_name = 'accounts/'.$accountPart;
            $siteSettings->gbp_location_name = $manual;
        } elseif ($selection === '') {
            $siteSettings->gbp_account_name = null;
            $siteSettings->gbp_location_name = null;
        } else {
            [$accountName, $locationName] = array_pad(explode('|', $selection, 2), 2, null);

            if (! $accountName || ! $locationName) {
                return redirect()
                    ->route('admin.settings.edit', ['tab' => 'integrations'])
                    ->with('status_error', 'Invalid Business Profile selection.');
            }

            $siteSettings->gbp_account_name = $accountName;
            $siteSettings->gbp_location_name = $locationName;
        }

        $siteSettings->save();
        $businessProfile->forgetCaches();

        return redirect()
            ->route('admin.settings.edit', ['tab' => 'integrations'])
            ->with('status', $selection === '' ? 'Business Profile selection cleared.' : 'Business Profile listing saved.');
    }
}
