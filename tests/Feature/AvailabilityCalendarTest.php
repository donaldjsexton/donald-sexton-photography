<?php

namespace Tests\Feature;

use App\Models\BookedJob;
use App\Services\GoogleClient;
use Google\Service\Calendar as CalendarService;
use Google\Service\Calendar\Error as FreeBusyError;
use Google\Service\Calendar\FreeBusyCalendar;
use Google\Service\Calendar\FreeBusyRequest;
use Google\Service\Calendar\FreeBusyResponse;
use Google\Service\Calendar\Resource\Freebusy;
use Google\Service\Calendar\TimePeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class AvailabilityCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-04 15:00:00', 'America/New_York'));
        Cache::flush();

        config([
            'availability.mock' => false,
            'availability.timezone' => 'America/New_York',
            'availability.lead_time_days' => 14,
            'availability.booked_hours' => 6,
            'availability.limited_hours' => 2,
            'availability.cache_seconds' => 600,
            'availability.horizon_months' => 18,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_missing_credentials_hide_the_grid_and_keep_the_inquiry_form(): void
    {
        $this->get('/inquire')
            ->assertOk()
            ->assertSee("Send an inquiry and I'll check your date by hand.", false)
            ->assertDontSee('data-availability-calendar', false)
            ->assertSee('data-multistep-form', false)
            ->assertSee('name="event_date"', false)
            ->assertSee('data-form-next', false)
            ->assertSee('data-form-submit', false);

        $response = $this->getJson('/api/availability?month=2026-12')
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Availability is not configured.']);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('s-maxage', (string) $response->headers->get('Cache-Control'));
    }

    public function test_mock_mode_previews_the_grid_and_applies_the_date_rules(): void
    {
        config(['availability.mock' => true]);

        $this->get('/inquire')
            ->assertOk()
            ->assertSee('data-availability-calendar', false)
            ->assertSee('data-availability-grid', false)
            ->assertSee('aria-label="Previous month"', false)
            ->assertSee('aria-label="Next month"', false)
            ->assertSee('data-availability-fallback hidden', false)
            ->assertSee('name="event_date"', false)
            ->assertSee('data-step="1"', false);

        $response = $this->getJson('/api/availability?month=2026-11')->assertOk();
        $cache = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cache);
        $this->assertStringContainsString('max-age=600', $cache);
        $this->assertStringContainsString('s-maxage=600', $cache);

        $payload = $response->json();
        $this->assertSame(['month', 'days'], array_keys($payload));
        $this->assertSame('2026-11', $payload['month']);
        $this->assertCount(30, $payload['days']);

        $statuses = collect($payload['days'])->mapWithKeys(fn (array $day): array => [$day['date'] => $day['status']]);

        foreach ($payload['days'] as $day) {
            $this->assertSame(['date', 'status'], array_keys($day));
            $this->assertContains($day['status'], ['open', 'limited', 'held', 'booked', 'unavailable']);
        }

        $this->assertSame('unavailable', $statuses['2026-11-11']);
        $this->assertSame('unavailable', $statuses['2026-11-17']);
        $this->assertSame('held', $statuses['2026-11-18']);
        $this->assertSame('booked', $statuses['2026-11-20']);
        $this->assertSame('booked', $statuses['2026-11-22']);
        $this->assertSame('limited', $statuses['2026-11-25']);
        $this->assertSame('open', $statuses['2026-11-27']);
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_mock_mode_is_ignored_in_production(): void
    {
        config(['availability.mock' => true]);
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->get('/inquire')
            ->assertOk()
            ->assertDontSee('data-availability-calendar', false)
            ->assertSee("Send an inquiry and I'll check your date by hand.", false);

        $this->getJson('/api/availability?month=2026-12')
            ->assertStatus(503);
    }

    public function test_invalid_or_out_of_window_months_are_rejected(): void
    {
        config(['availability.mock' => true]);

        $this->getJson('/api/availability?month=2026-13')->assertStatus(422);
        $this->getJson('/api/availability')->assertStatus(422);
        $this->getJson('/api/availability?month=2026-10')->assertStatus(422);
        $this->getJson('/api/availability?month=2028-06')->assertStatus(422);
        $this->getJson('/api/availability?month=2028-05')->assertOk();
    }

    public function test_free_busy_on_the_primary_calendar_returns_only_day_statuses_and_is_cached(): void
    {
        $this->bindFreeBusy($this->freeBusyResponse([
            ['2026-12-11T15:00:00Z', '2026-12-11T18:00:00Z'],
            ['2026-12-12T20:00:00Z', '2026-12-12T21:00:00Z'],
            ['2026-12-20T14:00:00Z', '2026-12-20T22:00:00Z'],
            ['2026-12-22T05:00:00Z', '2026-12-23T05:00:00Z'],
        ]));

        $first = $this->getJson('/api/availability?month=2026-12')->assertOk();
        $statuses = collect($first->json('days'))->mapWithKeys(fn (array $day): array => [$day['date'] => $day['status']]);

        $this->assertSame('limited', $statuses['2026-12-11']);
        $this->assertSame('open', $statuses['2026-12-12']);
        $this->assertSame('booked', $statuses['2026-12-20']);
        $this->assertSame('booked', $statuses['2026-12-22']);
        $this->assertSame(['month', 'days'], array_keys($first->json()));

        $this->getJson('/api/availability?month=2026-12')->assertOk();
    }

    public function test_studio_jobs_mark_booked_and_held_days_without_exposing_details(): void
    {
        $this->bindFreeBusy($this->freeBusyResponse([]));

        BookedJob::factory()->create([
            'summary' => 'Secret ceremony for the private couple',
            'couple_names' => 'Private Couple',
            'location' => 'A venue that must stay private',
            'event_date' => '2026-12-18',
            'status' => 'confirmed',
        ]);
        BookedJob::factory()->completed()->create([
            'summary' => 'Finished wedding that is still a secret',
            'event_date' => '2026-12-08',
        ]);
        BookedJob::factory()->cancelled()->create([
            'summary' => 'Cancelled wedding',
            'event_date' => '2026-12-10',
        ]);

        $response = $this->getJson('/api/availability?month=2026-12')->assertOk();
        $statuses = collect($response->json('days'))->mapWithKeys(fn (array $day): array => [$day['date'] => $day['status']]);

        $this->assertSame('held', $statuses['2026-12-18']);
        $this->assertSame('booked', $statuses['2026-12-08']);
        $this->assertSame('open', $statuses['2026-12-10']);
        $this->assertStringNotContainsString('Secret ceremony', $response->getContent());
        $this->assertStringNotContainsString('Private Couple', $response->getContent());
        $this->assertStringNotContainsString('must stay private', $response->getContent());
        $this->assertStringNotContainsString('Finished wedding', $response->getContent());
    }

    public function test_a_free_busy_error_does_not_publish_open_days(): void
    {
        $error = new FreeBusyError;
        $error->setReason('notFound');

        $entry = new FreeBusyCalendar;
        $entry->setErrors([$error]);

        $response = new FreeBusyResponse;
        $response->setCalendars(['primary' => $entry]);

        $this->bindFreeBusy($response);

        $httpResponse = $this->getJson('/api/availability?month=2026-12')->assertStatus(503);
        $this->assertStringContainsString('no-store', (string) $httpResponse->headers->get('Cache-Control'));
        $this->assertArrayNotHasKey('days', $httpResponse->json());
    }

    public function test_a_connected_calendar_shows_the_grid_on_the_inquiry_page(): void
    {
        $this->bindFreeBusy($this->freeBusyResponse([]), times: 0);

        $this->get('/inquire')
            ->assertOk()
            ->assertSee('data-availability-calendar', false)
            ->assertSee('name="event_date"', false);
    }

    public function test_page_script_keeps_the_inquiry_events_and_adds_date_selection(): void
    {
        $form = (string) file_get_contents(resource_path('js/app.js'));
        $calendar = (string) file_get_contents(resource_path('js/availability-calendar.js'));

        $this->assertStringContainsString("gtag('event', 'inquiry_form_start'", $form);
        $this->assertStringContainsString("gtag('event', 'inquiry_step_view'", $form);
        $this->assertStringContainsString('availability_date_select', $calendar);
        $this->assertStringContainsString('date_status', $calendar);
        $this->assertStringContainsString('months_ahead', $calendar);
        $this->assertStringNotContainsString('inquiry_form_start', $calendar);
        $this->assertStringNotContainsString('inquiry_step_view', $calendar);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $busy
     */
    private function freeBusyResponse(array $busy): FreeBusyResponse
    {
        $periods = [];

        foreach ($busy as [$start, $end]) {
            $period = new TimePeriod;
            $period->setStart($start);
            $period->setEnd($end);
            $periods[] = $period;
        }

        $calendar = new FreeBusyCalendar;
        $calendar->setBusy($periods);

        $response = new FreeBusyResponse;
        $response->setCalendars(['primary' => $calendar]);

        return $response;
    }

    private function bindFreeBusy(FreeBusyResponse $response, int $times = 1): void
    {
        $freebusy = Mockery::mock(Freebusy::class);
        $expectation = $freebusy->shouldReceive('query')->times($times);

        if ($times > 0) {
            $expectation->with(Mockery::on(function (FreeBusyRequest $request): bool {
                $items = $request->getItems();

                return $request->getTimeZone() === 'America/New_York'
                    && is_array($items)
                    && count($items) === 1
                    && $items[0]->getId() === 'primary';
            }))->andReturn($response);
        }

        $events = Mockery::mock();
        $events->shouldNotReceive('listEvents');

        $service = Mockery::mock(CalendarService::class);
        $service->freebusy = $freebusy;
        $service->events = $events;

        $google = Mockery::mock(GoogleClient::class)->shouldIgnoreMissing();
        $google->shouldReceive('calendar')->andReturn($service);

        $this->app->instance(GoogleClient::class, $google);
    }
}
