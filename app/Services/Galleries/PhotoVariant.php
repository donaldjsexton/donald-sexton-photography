<?php

namespace App\Services\Galleries;

/**
 * Derived renditions generated alongside an ingested original. Widths are the
 * maximum long-edge target; sources smaller than the target are not upscaled.
 */
enum PhotoVariant: string
{
    case Thumb = 'thumb';
    case Web = 'web';

    public function maxWidth(): int
    {
        return match ($this) {
            self::Thumb => 400,
            self::Web => 1600,
        };
    }

    /**
     * The rendition's storage path, derived from the original's path.
     */
    public function pathFor(string $originalPath): string
    {
        return preg_replace('/\.[^.]+$/', '', $originalPath).'_'.$this->value.'.webp';
    }
}
