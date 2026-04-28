<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'google_analytics_measurement_id',
        'google_connected_email',
        'google_access_token',
        'google_refresh_token',
        'google_token_expires_at',
        'google_granted_scopes',
        'gmail_last_history_id',
        'gmail_last_synced_at',
        'gbp_account_name',
        'gbp_location_name',
        'instagram_url',
        'pinterest_url',
        'facebook_url',
        'youtube_url',
        'tiktok_url',
        'x_url',
        'google_site_verification',
        'bing_site_verification',
        'pinterest_site_verification',
        'indexnow_key',
    ];

    protected $casts = [
        'google_granted_scopes' => 'array',
        'gmail_last_synced_at' => 'datetime',
    ];

    protected $hidden = [
        'google_access_token',
        'google_refresh_token',
    ];

    public static function current(): self
    {
        if (! Schema::hasTable('site_settings')) {
            return new self;
        }

        return static::query()->first() ?? new self;
    }

    public function analyticsMeasurementId(): ?string
    {
        $measurementId = trim((string) ($this->google_analytics_measurement_id ?: config('services.google_analytics.measurement_id')));

        return $measurementId !== '' ? $measurementId : null;
    }

    public function analyticsIsConfigured(): bool
    {
        return $this->analyticsMeasurementId() !== null;
    }

    public function googleIsConnected(): bool
    {
        return filled($this->google_refresh_token) && filled($this->google_connected_email);
    }

    public function googleHasScope(string $scope): bool
    {
        return in_array($scope, $this->google_granted_scopes ?? [], true);
    }

    /**
     * All scopes we request during the OAuth flow.
     *
     * @return array<int, string>
     */
    public static function googleScopes(): array
    {
        return [
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/webmasters.readonly',
            'https://www.googleapis.com/auth/business.manage',
            'https://www.googleapis.com/auth/calendar',
            'https://www.googleapis.com/auth/gmail.send',
            'https://www.googleapis.com/auth/gmail.readonly',
        ];
    }

    /**
     * URLs for the schema.org `sameAs` array on the organization entity.
     *
     * @return array<int, string>
     */
    public function socialProfileUrls(): array
    {
        return array_values(array_filter([
            $this->instagram_url,
            $this->pinterest_url,
            $this->facebook_url,
            $this->youtube_url,
            $this->tiktok_url,
            $this->x_url,
        ], fn ($url) => filled($url)));
    }

    /**
     * Site-verification meta tags pasted into the page head.
     *
     * @return array<string, string>
     */
    public function verificationMetas(): array
    {
        return array_filter([
            'google-site-verification' => (string) $this->google_site_verification,
            'msvalidate.01' => (string) $this->bing_site_verification,
            'p:domain_verify' => (string) $this->pinterest_site_verification,
        ], fn ($value) => $value !== '');
    }
}
