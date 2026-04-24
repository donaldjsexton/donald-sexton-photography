<?php

namespace Tests\Feature;

use App\Models\BookedJob;
use App\Services\CalendarSync;
use App\Services\GoogleClient;
use Google\Service\Calendar as CalendarService;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\Events;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_imports_wedding_events(): void
    {
        $event = $this->makeEvent(
            id: 'evt-123',
            summary: 'Sarah & James Wedding',
            date: '2026-06-15',
            location: 'The Breakers, Palm Beach',
            description: 'Ceremony at 4:30 PM on the lawn.',
        );

        $service = $this->buildSyncWithEvents([$event]);
        $count = $service->sync();

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('booked_jobs', [
            'google_event_id' => 'evt-123',
            'couple_names' => 'Sarah & James',
            'location' => 'The Breakers, Palm Beach',
        ]);
    }

    public function test_sync_skips_spam_events(): void
    {
        $event = $this->makeEvent(
            id: 'spam-1',
            summary: 'Bitcoin Investment Opportunity',
            date: '2026-07-01',
        );

        $service = $this->buildSyncWithEvents([$event]);
        $count = $service->sync();

        $this->assertSame(0, $count);
        $this->assertDatabaseMissing('booked_jobs', ['google_event_id' => 'spam-1']);
    }

    public function test_sync_skips_non_wedding_events(): void
    {
        $event = $this->makeEvent(
            id: 'dentist-1',
            summary: 'Dentist Appointment',
            date: '2026-07-01',
        );

        $service = $this->buildSyncWithEvents([$event]);
        $count = $service->sync();

        $this->assertSame(0, $count);
    }

    public function test_sync_handles_cancelled_events(): void
    {
        $event = $this->makeEvent(
            id: 'evt-cancel',
            summary: 'Cancelled: Amy & Bob Wedding',
            date: '2026-08-01',
        );

        $service = $this->buildSyncWithEvents([$event]);
        $service->sync();

        $this->assertDatabaseHas('booked_jobs', [
            'google_event_id' => 'evt-cancel',
            'status' => 'cancelled',
        ]);
    }

    public function test_sync_upserts_existing_events(): void
    {
        BookedJob::factory()->create([
            'google_event_id' => 'evt-existing',
            'couple_names' => 'Old Names',
        ]);

        $event = $this->makeEvent(
            id: 'evt-existing',
            summary: 'New Names Wedding',
            date: '2026-09-01',
        );

        $service = $this->buildSyncWithEvents([$event]);
        $service->sync();

        $this->assertDatabaseCount('booked_jobs', 1);
        $this->assertDatabaseHas('booked_jobs', [
            'google_event_id' => 'evt-existing',
            'couple_names' => 'New Names',
        ]);
    }

    public function test_sync_cancels_confirmed_events_missing_from_calendar(): void
    {
        $deleted = BookedJob::factory()->create([
            'google_event_id' => 'evt-deleted',
            'event_date' => now()->addMonth(),
            'status' => 'confirmed',
        ]);

        $kept = $this->makeEvent(
            id: 'evt-kept',
            summary: 'Alice & Ben Wedding',
            date: now()->addMonths(2)->toDateString(),
        );
        $keptJob = BookedJob::factory()->create([
            'google_event_id' => 'evt-kept',
            'event_date' => now()->addMonths(2),
            'status' => 'confirmed',
        ]);

        $service = $this->buildSyncWithEvents([$kept]);
        $service->sync();

        $this->assertDatabaseHas('booked_jobs', [
            'id' => $deleted->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('booked_jobs', [
            'id' => $keptJob->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_sync_does_not_cancel_events_outside_the_sync_window(): void
    {
        $farFuture = BookedJob::factory()->create([
            'google_event_id' => 'evt-far',
            'event_date' => now()->addYears(2),
            'status' => 'confirmed',
        ]);

        $service = $this->buildSyncWithEvents([]);
        $service->sync();

        $this->assertDatabaseHas('booked_jobs', [
            'id' => $farFuture->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_sync_preserves_completed_jobs_missing_from_calendar(): void
    {
        $completed = BookedJob::factory()->completed()->create([
            'google_event_id' => 'evt-done',
        ]);

        $service = $this->buildSyncWithEvents([]);
        $service->sync();

        $this->assertDatabaseHas('booked_jobs', [
            'id' => $completed->id,
            'status' => 'completed',
        ]);
    }

    public function test_sync_returns_zero_when_calendar_not_connected(): void
    {
        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('calendar')->andReturn(null);

        $service = new CalendarSync($googleClient);
        $count = $service->sync();

        $this->assertSame(0, $count);
    }

    public function test_sync_parses_time_from_description(): void
    {
        $event = $this->makeEvent(
            id: 'evt-time',
            summary: 'Lisa & Mark Wedding',
            date: '2026-10-10',
            description: 'Please arrive by 3:30 PM for the ceremony.',
        );

        $service = $this->buildSyncWithEvents([$event]);
        $service->sync();

        $this->assertDatabaseHas('booked_jobs', [
            'google_event_id' => 'evt-time',
            'event_time' => '3:30 PM',
        ]);
    }

    private function makeEvent(string $id, string $summary, string $date, string $location = '', string $description = ''): Event
    {
        $event = new Event;
        $event->setId($id);
        $event->setSummary($summary);
        $event->setLocation($location);
        $event->setDescription($description);
        $event->setStatus('confirmed');

        $start = new EventDateTime;
        $start->setDate($date);
        $event->setStart($start);

        $end = new EventDateTime;
        $end->setDate($date);
        $event->setEnd($end);

        return $event;
    }

    private function buildSyncWithEvents(array $events): CalendarSync
    {
        $eventsResult = Mockery::mock(Events::class);
        $eventsResult->shouldReceive('getItems')->andReturn($events);
        $eventsResult->shouldReceive('getNextPageToken')->andReturn(null);

        $eventsResource = Mockery::mock(CalendarService\Resource\Events::class);
        $eventsResource->shouldReceive('listEvents')->andReturn($eventsResult);

        $calendarService = Mockery::mock(CalendarService::class);
        $calendarService->events = $eventsResource;

        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('calendar')->andReturn($calendarService);

        return new CalendarSync($googleClient);
    }
}
