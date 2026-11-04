<?php

namespace App\Services\Availability;

use Carbon\CarbonImmutable;

readonly class BusyInterval
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}
}
