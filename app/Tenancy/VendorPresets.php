<?php

namespace App\Tenancy;

class VendorPresets
{
    public static function default(): string
    {
        return (string) config('vendors.default', 'photographer');
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return array_keys((array) config('vendors.types', []));
    }

    public static function exists(string $type): bool
    {
        return in_array($type, self::types(), true);
    }

    /**
     * Validated vendor type, falling back to the default.
     */
    public static function normalize(?string $type): string
    {
        $type = (string) $type;

        return self::exists($type) ? $type : self::default();
    }

    public static function name(string $type): string
    {
        return (string) config('vendors.types.'.$type.'.name', ucfirst($type));
    }

    /**
     * Display names keyed by type, for select inputs.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::types())
            ->mapWithKeys(fn (string $type): array => [$type => self::name($type)])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function onboarding(string $type): array
    {
        return (array) config('vendors.types.'.$type.'.onboarding', []);
    }

    public static function label(string $type, string $key, string $default = ''): string
    {
        return (string) config('vendors.types.'.$type.'.labels.'.$key, $default);
    }
}
