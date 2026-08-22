<?php

namespace App\Services\Galleries;

/**
 * Encoded formats a gallery rendition can be written in, best-quality-per-byte
 * first. Gallery photos are streamed by controllers rather than offered through
 * a <picture> element, so the format is chosen server-side from the request's
 * Accept header instead of by the browser.
 */
enum PhotoFormat: string
{
    case Avif = 'avif';
    case Webp = 'webp';

    public function extension(): string
    {
        return $this->value;
    }

    public function mimeType(): string
    {
        return 'image/'.$this->value;
    }

    /**
     * Encoder quality. AVIF reaches comparable perceptual quality to WebP at a
     * markedly lower number, so the two are not interchangeable.
     */
    public function quality(): int
    {
        return match ($this) {
            self::Avif => 50,
            self::Webp => 82,
        };
    }

    /**
     * Formats to try, best first, for a client advertising $acceptHeader.
     *
     * WebP is always the last entry: it is the format every stored rendition is
     * guaranteed to have, so it doubles as the fallback for photos ingested
     * before AVIF generation existed.
     *
     * @return list<self>
     */
    public static function negotiate(?string $acceptHeader): array
    {
        if ($acceptHeader !== null && str_contains(strtolower($acceptHeader), 'image/avif')) {
            return [self::Avif, self::Webp];
        }

        return [self::Webp];
    }
}
