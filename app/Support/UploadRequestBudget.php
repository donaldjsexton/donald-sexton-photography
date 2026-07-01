<?php

namespace App\Support;

/**
 * Resolves how large a single HTTP request body may be, so the admin gallery
 * uploader can split big photo batches into requests PHP will actually accept
 * instead of tripping the "POST data is too large" (413) middleware guard.
 */
class UploadRequestBudget
{
    /**
     * The maximum request body size PHP accepts, in bytes. Returns 0 when
     * post_max_size is unlimited.
     */
    public static function maxRequestBytes(): int
    {
        return self::iniSizeToBytes((string) ini_get('post_max_size'));
    }

    /**
     * Resolve a php.ini size shorthand (e.g. "64M", "1G", "512k") to bytes.
     * Returns 0 for unlimited ("0", "-1") or unparseable values.
     */
    public static function iniSizeToBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '0' || $value === '-1') {
            return 0;
        }

        $number = (int) $value;

        $bytes = match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };

        return max(0, $bytes);
    }
}
