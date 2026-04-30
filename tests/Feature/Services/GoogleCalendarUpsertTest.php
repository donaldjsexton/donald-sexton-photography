<?php

namespace Tests\Feature\Services;

use App\Models\Inquiry;
use App\Services\CalendarSyncOutcome;
use App\Services\GoogleCalendar;
use App\Services\GoogleClient;
use Google\Service\Calendar as CalendarService;
use Google\Service\Calendar\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GoogleCalendarUpsertTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_not_connected_when_calendar_unavailable(): void
    {
        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('calendar')->andReturn(null);

        $service = new GoogleCalendar($googleClient);
        $inquiry = Inquiry::factory()->booked()->create([
            'event_date' => '2027-09-11',
        ]);

        $this->assertSame(CalendarSyncOutcome::NotConnected, $service->upsertBookingEvent($inquiry));
        $this->assertNull($inquiry->fresh()->calendar_event_id);
    }

    public function test_returns_missing_event_date_when_inquiry_has_no_date(): void
    {
        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('calendar')->andReturn(Mockery::mock(CalendarService::class));

        $service = new GoogleCalendar($googleClient);
        $inquiry = Inquiry::factory()->booked()->create(['event_date' => null]);

        $this->assertSame(CalendarSyncOutcome::MissingEventDate, $service->upsertBookingEvent($inquiry));
        $this->assertNull($inquiry->fresh()->calendar_event_id);
    }

    public function test_inserts_event_and_persists_calendar_event_id_when_none_exists(): void
    {
        $created = new Event;
        $created->setId('evt-new-1');
        $created->setHtmlLink('https://calendar.google.com/event?eid=new-1');

        $eventsResource = Mockery::mock(CalendarService\Resource\Events::class);
        $eventsResource->shouldReceive('insert')
            ->once()
            ->withArgs(function (string $calendarId, Event $event): bool {
                return $calendarId === 'primary'
                    && str_contains($event->getSummary(), 'Wedding');
            })
            ->andReturn($created);
        $eventsResource->shouldNotReceive('update');

        $service = $this->buildService($eventsResource);

        $inquiry = Inquiry::factory()->booked()->create([
            'event_date' => '2027-09-11',
            'event_type' => 'wedding',
            'calendar_event_id' => null,
        ]);

        $this->assertSame(CalendarSyncOutcome::Synced, $service->upsertBookingEvent($inquiry));
        $this->assertSame('evt-new-1', $inquiry->fresh()->calendar_event_id);
    }

    public function test_updates_existing_event_when_calendar_event_id_already_set(): void
    {
        $eventsResource = Mockery::mock(CalendarService\Resource\Events::class);
        $eventsResource->shouldReceive('update')
            ->once()
            ->withArgs(function (string $calendarId, string $eventId): bool {
                return $calendarId === 'primary' && $eventId === 'evt-existing';
            })
            ->andReturn(new Event);
        $eventsResource->shouldNotReceive('insert');

        $service = $this->buildService($eventsResource);

        $inquiry = Inquiry::factory()->booked()->create([
            'event_date' => '2027-09-11',
            'calendar_event_id' => 'evt-existing',
        ]);

        $this->assertSame(CalendarSyncOutcome::Synced, $service->upsertBookingEvent($inquiry));
        $this->assertSame('evt-existing', $inquiry->fresh()->calendar_event_id);
    }

    public function test_recreates_event_when_stored_event_id_no_longer_exists(): void
    {
        $created = new Event;
        $created->setId('evt-recreated');

        $eventsResource = Mockery::mock(CalendarService\Resource\Events::class);
        $eventsResource->shouldReceive('update')
            ->once()
            ->andThrow(new \Exception('Event not found'));
        $eventsResource->shouldReceive('insert')
            ->once()
            ->andReturn($created);

        $service = $this->buildService($eventsResource);

        $inquiry = Inquiry::factory()->booked()->create([
            'event_date' => '2027-09-11',
            'calendar_event_id' => 'evt-stale',
        ]);

        $this->assertSame(CalendarSyncOutcome::Synced, $service->upsertBookingEvent($inquiry));
        $this->assertSame('evt-recreated', $inquiry->fresh()->calendar_event_id);
    }

    public function test_returns_failed_when_insert_throws(): void
    {
        $eventsResource = Mockery::mock(CalendarService\Resource\Events::class);
        $eventsResource->shouldReceive('insert')
            ->once()
            ->andThrow(new \Exception('Network error'));

        $service = $this->buildService($eventsResource);

        $inquiry = Inquiry::factory()->booked()->create([
            'event_date' => '2027-09-11',
            'calendar_event_id' => null,
        ]);

        $this->assertSame(CalendarSyncOutcome::Failed, $service->upsertBookingEvent($inquiry));
        $this->assertNull($inquiry->fresh()->calendar_event_id);
    }

    private function buildService(Mockery\MockInterface $eventsResource): GoogleCalendar
    {
        $calendarService = Mockery::mock(CalendarService::class);
        $calendarService->events = $eventsResource;

        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('calendar')->andReturn($calendarService);

        return new GoogleCalendar($googleClient);
    }
}
