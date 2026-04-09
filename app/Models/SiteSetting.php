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
    ];

    public static function current(): self
    {
        if (! Schema::hasTable('site_settings')) {
            return new self();
        }

        return static::query()->first() ?? new self();
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
}
