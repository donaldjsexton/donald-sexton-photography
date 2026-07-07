<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * Raw segments parsed out of an imported WordPress story body: the opening
 * lead paragraph, the first imported gallery, and the remaining reading copy.
 */
class StorySegments extends Data
{
    public function __construct(
        public ?string $leadText,
        public string $galleryHtml,
        public string $bodyHtml,
    ) {}
}
