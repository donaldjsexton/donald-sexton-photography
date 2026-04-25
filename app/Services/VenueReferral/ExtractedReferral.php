<?php

namespace App\Services\VenueReferral;

use Illuminate\Support\Carbon;

class ExtractedReferral
{
    /**
     * @param  array<int, string>  $coupleNames
     */
    public function __construct(
        public readonly array $coupleNames,
        public readonly ?Carbon $eventDate,
        public readonly ?string $primaryEmail,
        public readonly ?string $secondaryEmail,
        public readonly ?string $phone,
        public readonly float $confidence,
    ) {}

    public function isComplete(): bool
    {
        return $this->coupleNames !== []
            && $this->eventDate !== null
            && $this->primaryEmail !== null
            && $this->phone !== null;
    }

    public function primaryName(): string
    {
        return $this->coupleNames[0] ?? '';
    }

    public function partnerName(): ?string
    {
        return $this->coupleNames[1] ?? null;
    }
}
