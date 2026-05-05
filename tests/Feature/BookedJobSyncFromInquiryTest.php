<?php

namespace Tests\Feature;

use App\Models\BookedJob;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\BookedJobSync;
use App\Services\CalendarSync;
use App\Services\GoogleClient;
use Google\Service\Calendar as CalendarService;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\Events;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BookedJobSyncFromInquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_inquiry_booked_creates_calendar_entry_even_without_google(): void
    {
        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('calendar')->andReturn(null);
        $this->app->instance(GoogleClient::class, $googleClient);

        $admin = User::factory()->create();
        $inquiry = Inquiry::factory()->create([
            'status' => 'new',
            'event_date' => '2027-09-11',
            'primary_name' => 'Sarah Smith',
            'partner_name' => 'James Doe',
            'event_type' => 'wedding',
            'venue_name' => 'The Breakers',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.inquiries.update', $inquiry), ['status' => 'booked'])
            ->assertRedirect(route('admin.inquiries.edit', $inquiry));

        $this->assertDatabaseHas('booked_jobs', [
            'inquiry_id' => $inquiry->id,
            'couple_names' => 'Sarah Smith & James Doe',
            'location' => 'The Breakers',
            'status' => 'confirmed',
            'google_event_id' => null,
        ]);

        $this->assertSame('2027-09-11', BookedJob::query()->where('inquiry_id', $inquiry->id)->first()->event_date->toDateString());
    }

    public function test_booked_job_carries_google_event_id_when_calendar_push_succeeds(): void
    {
        $created = new Event;
        $created->setId('evt-from-pipeline');

        $eventsResource = Mockery::mock(CalendarService\Resource\Events::class);
        $eventsResource->shouldReceive('insert')->once()->andReturn($created);

        $calendarService = Mockery::mock(CalendarService::class);
        $calendarService->events = $eventsResource;

        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('calendar')->andReturn($calendarService);
        $this->app->instance(GoogleClient::class, $googleClient);

        $admin = User::factory()->create();
        $inquiry = Inquiry::factory()->create([
            'status' => 'new',
            'event_date' => '2027-10-10',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.inquiries.update', $inquiry), ['status' => 'booked']);

        $this->assertDatabaseHas('booked_jobs', [
            'inquiry_id' => $inquiry->id,
            'google_event_id' => 'evt-from-pipeline',
        ]);
        $this->assertSame('evt-from-pipeline', $inquiry->fresh()->calendar_event_id);
    }

    public function test_booking_inquiry_without_event_date_does_not_create_booked_job(): void
    {
        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('calendar')->andReturn(null);
        $this->app->instance(GoogleClient::class, $googleClient);

        $admin = User::factory()->create();
        $inquiry = Inquiry::factory()->create([
            'status' => 'new',
            'event_date' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.inquiries.update', $inquiry), ['status' => 'booked']);

        $this->assertDatabaseMissing('booked_jobs', ['inquiry_id' => $inquiry->id]);
    }

    public function test_re_booking_an_inquiry_updates_existing_booked_job_in_place(): void
    {
        $sync = new BookedJobSync;

        $inquiry = Inquiry::factory()->booked()->create([
            'event_date' => '2027-09-11',
            'venue_name' => 'Original Venue',
        ]);

        $first = $sync->syncFromInquiry($inquiry);
        $this->assertNotNull($first);

        $inquiry->update(['venue_name' => 'Updated Venue']);
        $second = $sync->syncFromInquiry($inquiry->refresh());

        $this->assertNotNull($second);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('booked_jobs', 1);
        $this->assertSame('Updated Venue', $second->fresh()->location);
    }

    public function test_calendar_sync_does_not_cancel_inquiry_linked_jobs_missing_from_google(): void
    {
        $inquiry = Inquiry::factory()->booked()->create([
            'event_date' => now()->addMonth(),
        ]);

        $job = BookedJob::factory()->create([
            'inquiry_id' => $inquiry->id,
            'google_event_id' => 'evt-pipeline',
            'event_date' => now()->addMonth(),
            'status' => 'confirmed',
        ]);

        $service = $this->buildSyncWithEvents([]);
        $service->sync();

        $this->assertDatabaseHas('booked_jobs', [
            'id' => $job->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_calendar_sync_does_not_duplicate_when_google_returns_pipeline_event(): void
    {
        $inquiry = Inquiry::factory()->booked()->create([
            'event_date' => '2027-04-12',
        ]);

        BookedJob::factory()->create([
            'inquiry_id' => $inquiry->id,
            'google_event_id' => 'evt-shared',
            'event_date' => '2027-04-12',
            'couple_names' => 'Pipeline Names',
        ]);

        $event = $this->makeEvent(
            id: 'evt-shared',
            summary: 'Pipeline Names Wedding',
            date: '2027-04-12',
        );

        $service = $this->buildSyncWithEvents([$event]);
        $service->sync();

        $this->assertDatabaseCount('booked_jobs', 1);
        $this->assertDatabaseHas('booked_jobs', [
            'google_event_id' => 'evt-shared',
            'inquiry_id' => $inquiry->id,
        ]);
    }

    private function makeEvent(string $id, string $summary, string $date): Event
    {
        $event = new Event;
        $event->setId($id);
        $event->setSummary($summary);
        $event->setStatus('confirmed');

        $start = new EventDateTime;
        $start->setDate($date);
        $event->setStart($start);

        $end = new EventDateTime;
        $end->setDate($date);
        $event->setEnd($end);

        return $event;
    }

    /**
     * @param  array<int, Event>  $events
     */
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
