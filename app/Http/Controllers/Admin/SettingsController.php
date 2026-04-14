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

        $gbpListing = [];

        if ($siteSettings->googleIsConnected() && $siteSettings->googleHasScope('https://www.googleapis.com/auth/business.manage')) {
            $gbpListing = $businessProfile->listAccountsAndLocations();
        }

        return view('admin.settings.edit', [
            'siteSettings' => $siteSettings,
            'gbpListing' => $gbpListing,
            'resolvedAnalyticsMeasurementId' => $siteSettings->analyticsMeasurementId(),
            'googleConnected' => $siteSettings->googleIsConnected(),
            'googleScopes' => [
                ['label' => 'Gmail (send email)', 'scope' => 'https://www.googleapis.com/auth/gmail.send'],
                ['label' => 'Search Console (organic traffic)', 'scope' => 'https://www.googleapis.com/auth/webmasters.readonly'],
                ['label' => 'Business Profile (reviews & rating)', 'scope' => 'https://www.googleapis.com/auth/business.manage'],
                ['label' => 'Calendar (booking events)', 'scope' => 'https://www.googleapis.com/auth/calendar'],
            ],
            'recentImportRuns' => ImportRun::query()->latest()->limit(12)->get(),
            'wordpressImportRuns' => ImportRun::query()
                ->where('source_type', 'wordpress')
                ->latest()
                ->limit(6)
                ->get(),
            'picTimeImportRuns' => ImportRun::query()
                ->where('source_type', 'pictime')
                ->latest()
                ->limit(6)
                ->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'google_analytics_measurement_id' => ['nullable', 'string', 'max:32', 'regex:/^G-[A-Z0-9]+$/i'],
        ], [
            'google_analytics_measurement_id.regex' => 'Use a valid GA4 measurement ID like G-ABC123XYZ.',
        ]);

        $siteSettings = SiteSetting::query()->first() ?? new SiteSetting;
        $siteSettings->google_analytics_measurement_id = filled($validated['google_analytics_measurement_id'] ?? null)
            ? strtoupper(trim((string) $validated['google_analytics_measurement_id']))
            : null;
        $siteSettings->save();

        return redirect()
            ->route('admin.settings.edit', ['tab' => 'analytics'])
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
