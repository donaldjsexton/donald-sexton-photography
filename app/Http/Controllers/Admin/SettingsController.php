<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportRun;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        $siteSettings = SiteSetting::current();

        return view('admin.settings.edit', [
            'siteSettings' => $siteSettings,
            'resolvedAnalyticsMeasurementId' => $siteSettings->analyticsMeasurementId(),
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

        $siteSettings = SiteSetting::query()->first() ?? new SiteSetting();
        $siteSettings->google_analytics_measurement_id = filled($validated['google_analytics_measurement_id'] ?? null)
            ? strtoupper(trim((string) $validated['google_analytics_measurement_id']))
            : null;
        $siteSettings->save();

        return redirect()
            ->route('admin.settings.edit', ['tab' => 'analytics'])
            ->with('status', 'Platform settings updated.');
    }
}
