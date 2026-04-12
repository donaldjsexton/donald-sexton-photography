<?php

namespace App\Services;

use App\Models\Inquiry;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Facades\Log;

class GoogleCalendar
{
    /** The Google Calendar ID to write events into. Defaults to primary. */
    private const CALENDAR_ID = 'primary';

    public function __construct(private readonly GoogleClient $googleClient) {}

    /**
     * Create or update a calendar event for a booked inquiry.
     * Returns the event HTML link, or null on failure / when Calendar is not connected.
     */
    public function upsertBookingEvent(Inquiry $inquiry): ?string
    {
        $calendar = $this->googleClient->calendar();

        if ($calendar === null) {
            return null;
        }

        if (! $inquiry->event_date) {
            return null;
        }

        try {
            $summary = trim(implode(' & ', array_filter([
                $inquiry->primary_name,
                $inquiry->partner_name,
            ]))).' — '.str($inquiry->event_type)->headline();

            $description = collect([
                'Email: '.$inquiry->email,
                $inquiry->phone ? 'Phone: '.$inquiry->phone : null,
                $inquiry->venue_name ? 'Venue: '.$inquiry->venue_name : null,
                $inquiry->location_city ? 'Location: '.$inquiry->location_city : null,
                $inquiry->guest_count_range ? 'Guests: '.$inquiry->guest_count_range : null,
                $inquiry->message ? "\n".$inquiry->message : null,
            ])->filter()->join("\n");

            $eventDate = $inquiry->event_date->toDateString();

            $event = new Event([
                'summary' => $summary,
                'description' => $description,
                'start' => (new EventDateTime)->setDate($eventDate)->setTimeZone('America/New_York'),
                'end' => (new EventDateTime)->setDate($eventDate)->setTimeZone('America/New_York'),
            ]);

            // Use the existing calendar_event_id if one was already created.
            if ($inquiry->calendar_event_id) {
                $updated = $calendar->events->update(self::CALENDAR_ID, $inquiry->calendar_event_id, $event);

                return $updated->getHtmlLink();
            }

            $created = $calendar->events->insert(self::CALENDAR_ID, $event);

            $inquiry->update(['calendar_event_id' => $created->getId()]);

            return $created->getHtmlLink();
        } catch (\Throwable $e) {
            Log::warning('Google Calendar event upsert failed: '.$e->getMessage());

            return null;
        }
    }
}
