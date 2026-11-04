<?php

namespace App\Services\Availability;

use Carbon\CarbonImmutable;

/**
 * Sample busy time for previewing the calendar without the connected Google account.
 * The day-of-month pattern repeats every month. Lead time still applies, so
 * early dates in the current month stay unavailable.
 *
 * - day number mod 11 = 0: the whole local day is busy (booked)
 * - 3: 10:00–13:00 (limited)
 * - 5: 15:00–16:00 (stays open)
 * - 7: a hold from 11:00–14:00 (held)
 * - 9: 09:00–17:00 (booked)
 */
class MockBusyIntervals
{
    /**
     * @return array{busy: list<BusyInterval>, holds: list<BusyInterval>}
     */
    public function forMonth(CarbonImmutable $monthStart): array
    {
        $cursor = $monthStart->startOfMonth()->startOfDay();
        $end = $cursor->addMonth();
        $busy = [];
        $holds = [];

        while ($cursor->lessThan($end)) {
            $slot = $cursor->day % 11;

            if ($slot === 0) {
                $busy[] = new BusyInterval($cursor->startOfDay(), $cursor->addDay()->startOfDay());
            } elseif ($slot === 3) {
                $busy[] = new BusyInterval($cursor->setTime(10, 0), $cursor->setTime(13, 0));
            } elseif ($slot === 5) {
                $busy[] = new BusyInterval($cursor->setTime(15, 0), $cursor->setTime(16, 0));
            } elseif ($slot === 7) {
                $holds[] = new BusyInterval($cursor->setTime(11, 0), $cursor->setTime(14, 0));
            } elseif ($slot === 9) {
                $busy[] = new BusyInterval($cursor->setTime(9, 0), $cursor->setTime(17, 0));
            }

            $cursor = $cursor->addDay();
        }

        return [
            'busy' => $busy,
            'holds' => $holds,
        ];
    }
}
