<?php

namespace App\Services\Cms;

use App\Models\SiteSetting;
use App\Support\FloridaGeo;

class SiteSettingsManager
{
    /**
     * Settings a remote CMS client may read and change. The IndexNow key,
     * OAuth tokens, Square credentials, and Business Profile links are not
     * in this list and are never returned or written through MCP.
     *
     * @var list<string>
     */
    public const MCP_FIELDS = [
        'google_analytics_measurement_id',
        'instagram_url', 'pinterest_url', 'facebook_url', 'youtube_url', 'tiktok_url', 'x_url',
        'google_site_verification', 'bing_site_verification', 'pinterest_site_verification',
        'business_phone', 'business_email', 'business_street_address', 'business_locality',
        'business_region', 'business_postal_code', 'business_country', 'business_latitude',
        'business_longitude', 'business_hours_note', 'business_price_range',
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'google_analytics_measurement_id.regex' => 'Use a valid GA4 measurement ID like G-ABC123XYZ.',
            'indexnow_key.regex' => 'IndexNow key must be 8–128 lowercase hex characters.',
        ];
    }

    public function current(): SiteSetting
    {
        return SiteSetting::query()->first() ?? new SiteSetting;
    }

    /**
     * Apply only the keys present in the validated input.
     *
     * @param  array<string, mixed>  $validated
     */
    public function apply(SiteSetting $siteSettings, array $validated): void
    {
        $has = fn (string $key): bool => array_key_exists($key, $validated);

        if ($has('google_analytics_measurement_id')) {
            $siteSettings->google_analytics_measurement_id = filled($validated['google_analytics_measurement_id'] ?? null)
                ? strtoupper(trim((string) $validated['google_analytics_measurement_id']))
                : null;
        }

        foreach (['instagram_url', 'pinterest_url', 'facebook_url', 'youtube_url', 'tiktok_url', 'x_url'] as $field) {
            if ($has($field)) {
                $siteSettings->{$field} = filled($validated[$field] ?? null) ? trim((string) $validated[$field]) : null;
            }
        }

        foreach (['google_site_verification', 'bing_site_verification', 'pinterest_site_verification'] as $field) {
            if ($has($field)) {
                $siteSettings->{$field} = filled($validated[$field] ?? null) ? trim((string) $validated[$field]) : null;
            }
        }

        if ($has('indexnow_key')) {
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
            if ($has($field)) {
                $siteSettings->{$field} = filled($validated[$field] ?? null) ? trim((string) $validated[$field]) : null;
            }
        }

        if ($has('business_country')) {
            $siteSettings->business_country = filled($validated['business_country'] ?? null)
                ? strtoupper(trim((string) $validated['business_country']))
                : null;
        }

        foreach (['business_latitude', 'business_longitude'] as $field) {
            if ($has($field)) {
                $value = $validated[$field] ?? null;
                $normalized = $field === 'business_latitude'
                    ? FloridaGeo::latitude($value, $siteSettings->business_region, $siteSettings->business_locality, $siteSettings->business_country)
                    : FloridaGeo::longitude($value, $siteSettings->business_region, $siteSettings->business_locality, $siteSettings->business_country);
                $siteSettings->{$field} = ($value === null || $value === '') ? null : $normalized;
            }
        }

        $siteSettings->save();
    }
}
