<?php

namespace App\Services;

use App\Models\BookedJob;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CalendarSync
{
    /** Keywords that indicate spam/scam calendar injections. */
    private const SPAM_KEYWORDS = [
        'bitcoin', 'btc', 'crypto', 'mcafee', 'geek squad', 'norton',
        'paypal', 'refund', 'subscription', 'antivirus', 'virus',
        'claim your', 'act now', 'limited time', 'congratulations',
    ];

    /** Coordinators whose events are always imported. */
    private const KNOWN_COORDINATORS = [
        'sales@fldestwed.com',
        'fldestwed.com',
    ];

    public function __construct(private readonly GoogleClient $googleClient) {}

    /**
     * Sync events from Google Calendar into the booked_jobs table.
     * Returns the count of upserted records.
     */
    public function sync(): int
    {
        $calendar = $this->googleClient->calendar();

        if ($calendar === null) {
            Log::info('CalendarSync: Calendar not connected, skipping.');

            return 0;
        }

        try {
            $events = $this->fetchEvents($calendar);
        } catch (\Throwable $e) {
            Log::warning('CalendarSync: Failed to fetch events — '.$e->getMessage());

            return 0;
        }

        $upserted = 0;
        $seenEventIds = [];

        foreach ($events as $event) {
            $eventId = $event->getId();

            if ($eventId !== null && $eventId !== '') {
                $seenEventIds[] = $eventId;
            }

            if ($this->isSpam($event)) {
                continue;
            }

            if (! $this->looksLikeWedding($event)) {
                continue;
            }

            $this->upsertEvent($event);
            $upserted++;
        }

        $cancelled = $this->cancelMissingEvents($seenEventIds);

        Log::info("CalendarSync: Synced {$upserted} booked jobs, cancelled {$cancelled} deleted events.");

        return $upserted;
    }

    /**
     * Mark confirmed jobs as cancelled when their Google event no longer
     * appears in the current sync window (e.g. deleted from the calendar).
     *
     * @param  array<int, string>  $seenEventIds
     */
    private function cancelMissingEvents(array $seenEventIds): int
    {
        $windowStart = now()->subMonths(6)->toDateString();
        $windowEnd = now()->addYear()->toDateString();

        return BookedJob::query()
            ->whereBetween('event_date', [$windowStart, $windowEnd])
            ->whereNotNull('google_event_id')
            ->where('status', 'confirmed')
            ->when($seenEventIds !== [], fn ($query) => $query->whereNotIn('google_event_id', $seenEventIds))
            ->update([
                'status' => 'cancelled',
                'synced_at' => now(),
            ]);
    }

    /**
     * @return array<int, Event>
     */
    private function fetchEvents(Calendar $calendar): array
    {
        $events = [];
        $pageToken = null;

        do {
            $params = [
                'maxResults' => 250,
                'singleEvents' => true,
                'orderBy' => 'startTime',
                'timeMin' => now()->subMonths(6)->toRfc3339String(),
                'timeMax' => now()->addYear()->toRfc3339String(),
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $result = $calendar->events->listEvents('primary', $params);

            foreach ($result->getItems() as $event) {
                $events[] = $event;
            }

            $pageToken = $result->getNextPageToken();
        } while ($pageToken);

        return $events;
    }

    private function isSpam(Event $event): bool
    {
        $text = Str::lower($event->getSummary().' '.$event->getDescription());

        foreach (self::SPAM_KEYWORDS as $keyword) {
            if (Str::contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeWedding(Event $event): bool
    {
        $summary = Str::lower($event->getSummary() ?? '');

        $weddingTerms = ['wedding', 'ceremony', 'reception', 'rehearsal', 'elopement', 'vow renewal'];

        foreach ($weddingTerms as $term) {
            if (Str::contains($summary, $term)) {
                return true;
            }
        }

        // Check if any known coordinator is an attendee or in the organizer.
        if ($this->hasKnownCoordinator($event)) {
            return true;
        }

        // Check description for wedding-related content.
        $description = Str::lower($event->getDescription() ?? '');

        foreach (['bride', 'groom', 'officiant', 'ceremony', 'wedding'] as $term) {
            if (Str::contains($description, $term)) {
                return true;
            }
        }

        return false;
    }

    private function hasKnownCoordinator(Event $event): bool
    {
        $organizer = $event->getOrganizer();

        if ($organizer) {
            $email = Str::lower($organizer->getEmail() ?? '');

            foreach (self::KNOWN_COORDINATORS as $coordinator) {
                if (Str::contains($email, $coordinator)) {
                    return true;
                }
            }
        }

        foreach ($event->getAttendees() ?? [] as $attendee) {
            $email = Str::lower($attendee->getEmail() ?? '');

            foreach (self::KNOWN_COORDINATORS as $coordinator) {
                if (Str::contains($email, $coordinator)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function upsertEvent(Event $event): void
    {
        $summary = $event->getSummary() ?? '';
        $description = $event->getDescription() ?? '';
        $isCancelled = Str::startsWith(Str::lower($summary), 'cancelled') ||
            $event->getStatus() === 'cancelled';

        $coupleNames = $this->parseCoupleNames($summary);
        $ceremonyNotes = $this->parseCeremonyNotes($description);
        $eventTime = $this->parseEventTime($event);
        $coordinator = $this->parseCoordinator($event);

        BookedJob::updateOrCreate(
            ['google_event_id' => $event->getId()],
            [
                'summary' => Str::limit($summary, 255),
                'couple_names' => $coupleNames,
                'event_date' => $this->parseEventDate($event),
                'event_time' => $eventTime,
                'location' => Str::limit($event->getLocation() ?? '', 255) ?: null,
                'coordinator' => $coordinator,
                'ceremony_notes' => $ceremonyNotes,
                'status' => $isCancelled ? 'cancelled' : 'confirmed',
                'raw_description' => $description ?: null,
                'synced_at' => now(),
            ]
        );
    }

    private function parseEventDate(Event $event): string
    {
        $start = $event->getStart();

        if ($start->getDateTime()) {
            return Carbon::parse($start->getDateTime())->toDateString();
        }

        return $start->getDate();
    }

    private function parseEventTime(Event $event): ?string
    {
        $start = $event->getStart();

        if ($start->getDateTime()) {
            return Carbon::parse($start->getDateTime())->format('g:i A');
        }

        // Try to extract time from description.
        $description = $event->getDescription() ?? '';

        if (preg_match('/(\d{1,2}:\d{2}\s*(?:AM|PM|am|pm))/i', $description, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function parseCoupleNames(string $summary): ?string
    {
        // Remove common prefixes like "Cancelled:" or "CANCELLED -".
        $cleaned = preg_replace('/^(?:cancelled|canceled)\s*[-:]\s*/i', '', $summary);

        // Remove trailing " Wedding", " Ceremony", etc.
        $cleaned = preg_replace('/\s*[-—]\s*(wedding|ceremony|reception|rehearsal|elopement).*$/i', '', $cleaned);
        $cleaned = preg_replace('/\s+(wedding|ceremony|reception|rehearsal|elopement).*$/i', '', $cleaned);

        $cleaned = trim($cleaned);

        return $cleaned !== '' ? Str::limit($cleaned, 255) : null;
    }

    private function parseCeremonyNotes(string $description): ?string
    {
        if (blank($description)) {
            return null;
        }

        // Strip HTML tags but preserve line breaks.
        $text = preg_replace('/<br\s*\/?>/i', "\n", $description);
        $text = preg_replace('/<\/(?:p|div|li|tr)>/i', "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse excessive whitespace.
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        $text = trim($text);

        return $text !== '' ? $text : null;
    }

    private function parseCoordinator(Event $event): ?string
    {
        $organizer = $event->getOrganizer();

        if ($organizer) {
            $email = Str::lower($organizer->getEmail() ?? '');

            foreach (self::KNOWN_COORDINATORS as $coordinator) {
                if (Str::contains($email, $coordinator)) {
                    return $organizer->getDisplayName() ?: 'FL Destination Weddings';
                }
            }
        }

        foreach ($event->getAttendees() ?? [] as $attendee) {
            $email = Str::lower($attendee->getEmail() ?? '');

            foreach (self::KNOWN_COORDINATORS as $coordinator) {
                if (Str::contains($email, $coordinator)) {
                    return $attendee->getDisplayName() ?: 'FL Destination Weddings';
                }
            }
        }

        return null;
    }
}
