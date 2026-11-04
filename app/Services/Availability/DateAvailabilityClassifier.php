<?php

namespace App\Services\Availability;

use Carbon\CarbonImmutable;

/**
 * Turns free/busy ranges into a day status.
 *
 * Past dates and dates inside the lead time are unavailable. A single busy
 * block that covers a whole local day, or at least the booked-hours threshold
 * of merged busy time, is booked. Any busy time on the hold calendar is held
 * unless the day is already booked. Busy time between the limited and booked
 * thresholds is limited. Anything shorter stays open.
 */
class DateAvailabilityClassifier
{
    public function __construct(
        private readonly string $timezone = 'America/New_York',
        private readonly int $leadTimeDays = 14,
        private readonly int $bookedSeconds = 21600,
        private readonly int $limitedSeconds = 7200,
    ) {}

    /**
     * @param  list<BusyInterval>  $busy
     * @param  list<BusyInterval>  $holds
     * @return list<array{date: string, status: string}>
     */
    public function month(CarbonImmutable $monthStart, array $busy, array $holds): array
    {
        $cursor = $monthStart->timezone($this->timezone)->startOfMonth()->startOfDay();
        $end = $cursor->addMonth();
        $days = [];

        while ($cursor->lessThan($end)) {
            $days[] = [
                'date' => $cursor->toDateString(),
                'status' => $this->statusFor($cursor, $busy, $holds)->value,
            ];
            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * @param  list<BusyInterval>  $busy
     * @param  list<BusyInterval>  $holds
     */
    public function statusFor(CarbonImmutable $date, array $busy, array $holds): AvailabilityStatus
    {
        $day = $date->timezone($this->timezone)->startOfDay();
        $earliest = CarbonImmutable::now($this->timezone)->startOfDay()->addDays($this->leadTimeDays);

        if ($day->lessThan($earliest)) {
            return AvailabilityStatus::Unavailable;
        }

        $dayEnd = $day->addDay();
        $overlap = $this->overlapSeconds($busy, $day, $dayEnd);

        if ($this->coversEntireDay($busy, $day, $dayEnd) || ($overlap > 0 && $overlap >= $this->bookedSeconds)) {
            return AvailabilityStatus::Booked;
        }

        if ($this->overlapSeconds($holds, $day, $dayEnd) > 0) {
            return AvailabilityStatus::Held;
        }

        if ($overlap > 0 && $overlap >= $this->limitedSeconds) {
            return AvailabilityStatus::Limited;
        }

        return AvailabilityStatus::Open;
    }

    /**
     * A busy block is all-day for this local date when it contains the whole
     * civil day, including a 23-hour spring-forward day or a 25-hour fallback day.
     *
     * @param  list<BusyInterval>  $intervals
     */
    private function coversEntireDay(array $intervals, CarbonImmutable $dayStart, CarbonImmutable $dayEnd): bool
    {
        $start = $dayStart->getTimestamp();
        $end = $dayEnd->getTimestamp();

        foreach ($intervals as $interval) {
            if ($interval->start->getTimestamp() <= $start && $interval->end->getTimestamp() >= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Merged overlap with the local day, in real seconds. Overlapping ranges
     * from different calendars count once.
     *
     * @param  list<BusyInterval>  $intervals
     */
    private function overlapSeconds(array $intervals, CarbonImmutable $dayStart, CarbonImmutable $dayEnd): int
    {
        $windowStart = $dayStart->getTimestamp();
        $windowEnd = $dayEnd->getTimestamp();
        $slices = [];

        foreach ($intervals as $interval) {
            $start = max($interval->start->getTimestamp(), $windowStart);
            $end = min($interval->end->getTimestamp(), $windowEnd);

            if ($end <= $start) {
                continue;
            }

            $slices[] = [$start, $end];
        }

        if ($slices === []) {
            return 0;
        }

        usort($slices, fn (array $left, array $right): int => $left[0] <=> $right[0]);

        $mergedStart = $slices[0][0];
        $mergedEnd = $slices[0][1];
        $total = 0;

        foreach (array_slice($slices, 1) as [$start, $end]) {
            if ($start <= $mergedEnd) {
                $mergedEnd = max($mergedEnd, $end);

                continue;
            }

            $total += $mergedEnd - $mergedStart;
            $mergedStart = $start;
            $mergedEnd = $end;
        }

        return $total + ($mergedEnd - $mergedStart);
    }
}
