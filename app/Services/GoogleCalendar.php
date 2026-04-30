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
     */
    public function upsertBookingEvent(Inquiry $inquiry): CalendarSyncOutcome
    {
        $calendar = $this->googleClient->calendar();

        if ($calendar === null) {
            return CalendarSyncOutcome::NotConnected;
        }

        if (! $inquiry->event_date) {
            return CalendarSyncOutcome::MissingEventDate;
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

            // Note: Google\Service\Calendar setters do not return $this, so set
            // these properties imperatively rather than chaining.
            $start = new EventDateTime;
            $start->setDate($eventDate);
            $start->setTimeZone('America/New_York');

            $end = new EventDateTime;
            $end->setDate($eventDate);
            $end->setTimeZone('America/New_York');

            $event = new Event([
                'summary' => $summary,
                'description' => $description,
                'start' => $start,
                'end' => $end,
            ]);

            if ($inquiry->calendar_event_id) {
                try {
                    $calendar->events->update(self::CALENDAR_ID, $inquiry->calendar_event_id, $event);

                    return CalendarSyncOutcome::Synced;
                } catch (\Throwable $updateException) {
                    // The stored event id may point at an event that was deleted in
                    // Google Calendar. Fall through and recreate so admins are not
                    // permanently blocked from syncing.
                    Log::warning('Google Calendar event update failed, recreating: '.$updateException->getMessage());

                    $inquiry->update(['calendar_event_id' => null]);
                }
            }

            $created = $calendar->events->insert(self::CALENDAR_ID, $event);

            $inquiry->update(['calendar_event_id' => $created->getId()]);

            return CalendarSyncOutcome::Synced;
        } catch (\Throwable $e) {
            Log::warning('Google Calendar event upsert failed: '.$e->getMessage());

            return CalendarSyncOutcome::Failed;
        }
    }
}
