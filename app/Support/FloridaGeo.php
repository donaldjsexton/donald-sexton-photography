<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clearwater is on Florida's Gulf Coast: north of the equator and west of
 * the prime meridian. A positive longitude or a negative latitude inside
 * Florida's range is a sign typo. The live site stored +82.787, which places
 * the studio in western China.
 */
class FloridaGeo
{
    private const LATITUDE_MIN = 24.4;

    private const LATITUDE_MAX = 31.1;

    private const LONGITUDE_MIN = 79.8;

    private const LONGITUDE_MAX = 87.7;

    public static function latitude(mixed $latitude, ?string $region, ?string $locality): ?float
    {
        if ($latitude === null || $latitude === '') {
            return null;
        }

        $lat = (float) $latitude;

        if (! self::appliesToFlorida($region, $locality)) {
            return $lat;
        }

        $absolute = abs($lat);

        if ($lat < 0 && $absolute >= self::LATITUDE_MIN && $absolute <= self::LATITUDE_MAX) {
            return round($absolute, 6);
        }

        return $lat;
    }

    public static function longitude(mixed $longitude, ?string $region, ?string $locality): ?float
    {
        if ($longitude === null || $longitude === '') {
            return null;
        }

        $lng = (float) $longitude;

        if (! self::appliesToFlorida($region, $locality)) {
            return $lng;
        }

        $absolute = abs($lng);

        if ($lng > 0 && $absolute >= self::LONGITUDE_MIN && $absolute <= self::LONGITUDE_MAX) {
            return round(-$absolute, 6);
        }

        return $lng;
    }

    /**
     * Persist sign corrections for Florida coordinates already stored on
     * site settings. Safe to run more than once.
     */
    public static function correctStoredSettings(): int
    {
        if (! Schema::hasTable('site_settings')) {
            return 0;
        }

        $updated = 0;

        $rows = DB::table('site_settings')
            ->select(['id', 'business_latitude', 'business_longitude', 'business_region', 'business_locality'])
            ->get();

        foreach ($rows as $row) {
            $latitude = self::latitude($row->business_latitude, $row->business_region, $row->business_locality);
            $longitude = self::longitude($row->business_longitude, $row->business_region, $row->business_locality);
            $currentLatitude = $row->business_latitude === null ? null : round((float) $row->business_latitude, 6);
            $currentLongitude = $row->business_longitude === null ? null : round((float) $row->business_longitude, 6);
            $nextLatitude = $latitude === null ? null : round($latitude, 6);
            $nextLongitude = $longitude === null ? null : round($longitude, 6);

            if ($currentLatitude === $nextLatitude && $currentLongitude === $nextLongitude) {
                continue;
            }

            DB::table('site_settings')->where('id', $row->id)->update([
                'business_latitude' => $latitude,
                'business_longitude' => $longitude,
            ]);

            $updated++;
        }

        return $updated;
    }

    private static function appliesToFlorida(?string $region, ?string $locality): bool
    {
        $region = strtoupper(trim((string) $region));
        $locality = strtolower(trim((string) $locality));

        return $region === 'FL' || $region === 'FLORIDA' || $locality === 'clearwater';
    }
}
