<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * The presentation-ready segments of a wedding story: the hero copy shown at
 * the top, the optional imported gallery markup, and the reading body.
 */
class PresentationContent extends Data
{
    public function __construct(
        public ?string $heroCopy,
        public ?string $galleryHtml,
        public ?string $bodyHtml,
    ) {}
}
