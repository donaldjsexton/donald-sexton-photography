<?php

namespace App\Services;

use App\Models\BookedJob;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class InquiryAvailabilityService
{
    /**
     * Look up availability for a prospective event date based on the
     * studio's confirmed bookings. Cancelled or completed jobs are
     * considered open. When the date is unavailable, suggest nearby
     * Saturdays within roughly four weeks.
     *
     * @return array{
     *     status: 'unknown'|'available'|'unavailable',
     *     event_date: ?Carbon,
     *     nearby_dates: array<int, Carbon>
     * }
     */
    public function forDate(?CarbonInterface $eventDate): array
    {
        if (! $eventDate) {
            return [
                'status' => 'unknown',
                'event_date' => null,
                'nearby_dates' => [],
            ];
        }

        $eventDate = Carbon::instance($eventDate)->startOfDay();

        if ($eventDate->isPast()) {
            return [
                'status' => 'unknown',
                'event_date' => $eventDate,
                'nearby_dates' => [],
            ];
        }

        $isBooked = $this->isDateBooked($eventDate);

        if (! $isBooked) {
            return [
                'status' => 'available',
                'event_date' => $eventDate,
                'nearby_dates' => [],
            ];
        }

        return [
            'status' => 'unavailable',
            'event_date' => $eventDate,
            'nearby_dates' => $this->nearbySaturdays($eventDate),
        ];
    }

    private function isDateBooked(Carbon $date): bool
    {
        return BookedJob::query()
            ->whereDate('event_date', $date->toDateString())
            ->where('status', 'confirmed')
            ->exists();
    }

    /**
     * @return array<int, Carbon>
     */
    private function nearbySaturdays(Carbon $eventDate, int $weeks = 4): array
    {
        $candidates = collect();

        for ($offset = 1; $offset <= $weeks; $offset++) {
            foreach ([-1, 1] as $direction) {
                $candidate = $eventDate->copy()->addWeeks($direction * $offset);

                if ($candidate->isPast()) {
                    continue;
                }

                $saturday = $candidate->isSaturday()
                    ? $candidate
                    : $candidate->copy()->next(Carbon::SATURDAY);

                if ($saturday->isPast()) {
                    continue;
                }

                $candidates->push($saturday->copy()->startOfDay());
            }
        }

        return $candidates
            ->unique(fn (Carbon $date) => $date->toDateString())
            ->reject(fn (Carbon $date) => $this->isDateBooked($date))
            ->sortBy(fn (Carbon $date) => abs($date->diffInDays($eventDate, false)))
            ->take(3)
            ->values()
            ->all();
    }
}
