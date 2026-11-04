<?php

namespace App\Services\Availability;

use App\Services\GoogleClient;
use Carbon\CarbonImmutable;
use Google\Service\Calendar\FreeBusyCalendar;
use Google\Service\Calendar\FreeBusyRequest;
use Google\Service\Calendar\FreeBusyRequestItem;
use Google\Service\Calendar\TimePeriod;
use Illuminate\Support\Facades\Log;

/**
 * Reads free/busy time for the studio's primary calendar.
 *
 * Uses the Google account already connected in admin settings. The only
 * Calendar call is freeBusy.query, so event titles, descriptions, and guests
 * are never requested. The existing calendar scope can make that call.
 */
class GoogleFreeBusyGateway
{
    /**
     * The same calendar Calendar sync reads and booking writes use.
     */
    public const CALENDAR_ID = 'primary';

    public function __construct(private readonly GoogleClient $google) {}

    public function configured(): bool
    {
        return $this->google->calendar() !== null;
    }

    /**
     * @return list<BusyInterval>
     */
    public function busyRanges(CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): array
    {
        $calendar = $this->google->calendar();

        if ($calendar === null) {
            throw new AvailabilityLookupFailed('Availability is not configured.');
        }

        $item = new FreeBusyRequestItem;
        $item->setId(self::CALENDAR_ID);

        $request = new FreeBusyRequest;
        $request->setTimeMin($rangeStart->toRfc3339String());
        $request->setTimeMax($rangeEnd->toRfc3339String());
        $request->setTimeZone($rangeStart->getTimezone()->getName());
        $request->setItems([$item]);

        try {
            $response = $calendar->freebusy->query($request);
        } catch (\Throwable) {
            Log::warning('Availability free/busy request failed.');

            throw new AvailabilityLookupFailed('Availability could not be checked.');
        }

        $entry = $response->getCalendars()[self::CALENDAR_ID] ?? null;

        if (! $entry instanceof FreeBusyCalendar || $entry->getErrors()) {
            Log::warning('Availability free/busy could not be read for the primary calendar.');

            throw new AvailabilityLookupFailed('Availability could not be checked.');
        }

        $ranges = [];

        foreach ($entry->getBusy() ?? [] as $period) {
            if (! $period instanceof TimePeriod || ! is_string($period->getStart()) || ! is_string($period->getEnd())) {
                throw new AvailabilityLookupFailed('Availability could not be checked.');
            }

            try {
                $ranges[] = new BusyInterval(
                    CarbonImmutable::parse($period->getStart()),
                    CarbonImmutable::parse($period->getEnd()),
                );
            } catch (\Throwable) {
                throw new AvailabilityLookupFailed('Availability could not be checked.');
            }
        }

        return $ranges;
    }
}
