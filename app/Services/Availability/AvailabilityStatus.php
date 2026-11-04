<?php

namespace App\Services\Availability;

enum AvailabilityStatus: string
{
    case Open = 'open';
    case Limited = 'limited';
    case Held = 'held';
    case Booked = 'booked';
    case Unavailable = 'unavailable';
}
