<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AvailabilityCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-04 15:00:00', 'America/New_York'));

        config([
            'availability.mock' => false,
            'availability.timezone' => 'America/New_York',
            'availability.calendar_ids' => [],
            'availability.hold_calendar_id' => null,
            'availability.lead_time_days' => 14,
            'availability.booked_hours' => 6,
            'availability.limited_hours' => 2,
            'availability.cache_seconds' => 600,
            'availability.horizon_months' => 18,
            'availability.google.service_account_json' => null,
            'availability.google.client_id' => null,
            'availability.google.client_secret' => null,
            'availability.google.refresh_token' => null,
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

    public function test_free_busy_query_returns_only_day_statuses_and_is_cached(): void
    {
        $this->configureServiceAccount();
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-1',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            'www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'kind' => 'calendar#freeBusy',
                'calendars' => [
                    'primary@example.com' => [
                        'summary' => 'Secret ceremony',
                        'busy' => [
                            ['start' => '2026-12-11T15:00:00Z', 'end' => '2026-12-11T18:00:00Z'],
                            ['start' => '2026-12-12T20:00:00Z', 'end' => '2026-12-12T21:00:00Z'],
                            ['start' => '2026-12-20T14:00:00Z', 'end' => '2026-12-20T22:00:00Z'],
                            ['start' => '2026-12-22T05:00:00Z', 'end' => '2026-12-23T05:00:00Z'],
                        ],
                    ],
                    'studio@example.com' => [
                        'busy' => [
                            ['start' => '2026-12-11T15:00:00Z', 'end' => '2026-12-11T18:00:00Z'],
                        ],
                    ],
                    'holds@example.com' => [
                        'busy' => [
                            ['start' => '2026-12-18T15:00:00Z', 'end' => '2026-12-18T17:00:00Z'],
                        ],
                    ],
                ],
            ]),
        ]);

        $first = $this->getJson('/api/availability?month=2026-12')->assertOk();
        $statuses = collect($first->json('days'))->mapWithKeys(fn (array $day): array => [$day['date'] => $day['status']]);

        $this->assertSame('limited', $statuses['2026-12-11']);
        $this->assertSame('open', $statuses['2026-12-12']);
        $this->assertSame('held', $statuses['2026-12-18']);
        $this->assertSame('booked', $statuses['2026-12-20']);
        $this->assertSame('booked', $statuses['2026-12-22']);
        $this->assertStringNotContainsString('Secret ceremony', $first->getContent());
        $this->assertSame(['month', 'days'], array_keys($first->json()));

        $sent = Http::recorded()->count();
        $this->getJson('/api/availability?month=2026-12')->assertOk();
        $this->assertCount($sent, Http::recorded());

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return false;
            }

            $parts = explode('.', (string) $request->data()['assertion']);
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            return ($request->data()['grant_type'] ?? null) === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && ($payload['scope'] ?? null) === 'https://www.googleapis.com/auth/calendar.freebusy';
        });

        Http::assertSent(function (Request $request): bool {
            if (! str_ends_with($request->url(), '/calendar/v3/freeBusy')) {
                return false;
            }

            $body = $request->data();
            $ids = array_column($body['items'], 'id');
            sort($ids);

            return $body['timeZone'] === 'America/New_York'
                && $ids === ['holds@example.com', 'primary@example.com', 'studio@example.com']
                && array_keys($body) === ['timeMin', 'timeMax', 'timeZone', 'items']
                && array_keys($body['items'][0]) === ['id'];
        });

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/events'));
    }

    public function test_a_hold_calendar_that_is_also_allowlisted_is_classified_only_as_held(): void
    {
        config([
            'availability.calendar_ids' => ['primary@example.com', 'holds@example.com'],
            'availability.hold_calendar_id' => 'holds@example.com',
            'availability.google.service_account_json' => $this->serviceAccountJson(),
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            'www.googleapis.com/calendar/v3/freeBusy' => function (Request $request) {
                $ids = array_column($request->data()['items'], 'id');
                sort($ids);
                $this->assertSame(['holds@example.com', 'primary@example.com'], $ids);

                return Http::response([
                    'calendars' => [
                        'primary@example.com' => ['busy' => []],
                        'holds@example.com' => [
                            'busy' => [
                                ['start' => '2026-12-09T14:00:00Z', 'end' => '2026-12-09T22:00:00Z'],
                            ],
                        ],
                    ],
                ]);
            },
        ]);

        $response = $this->getJson('/api/availability?month=2026-12')->assertOk();
        $status = collect($response->json('days'))->firstWhere('date', '2026-12-09')['status'];

        $this->assertSame('held', $status);
    }

    public function test_oauth_refresh_token_is_used_when_no_service_account_is_configured(): void
    {
        config([
            'availability.calendar_ids' => ['primary@example.com'],
            'availability.google.client_id' => 'client-id',
            'availability.google.client_secret' => 'client-secret',
            'availability.google.refresh_token' => 'refresh-token',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'oauth-token', 'expires_in' => 3600]),
            'www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => [
                    'primary@example.com' => ['busy' => []],
                ],
            ]),
        ]);

        $this->getJson('/api/availability?month=2026-12')->assertOk();

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return false;
            }

            $body = $request->data();

            return ($body['grant_type'] ?? null) === 'refresh_token'
                && ($body['refresh_token'] ?? null) === 'refresh-token'
                && ! array_key_exists('assertion', $body);
        });
    }

    public function test_a_calendar_error_does_not_publish_open_days(): void
    {
        $this->configureServiceAccount();
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            'www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => [
                    'primary@example.com' => [
                        'errors' => [['domain' => 'global', 'reason' => 'notFound']],
                        'busy' => [],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/availability?month=2026-12')->assertStatus(503);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->assertArrayNotHasKey('days', $response->json());
        $this->assertStringNotContainsString('"status":"open"', $response->getContent());
    }

    public function test_service_account_json_can_be_read_from_a_file_path(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'availability-');
        $this->assertNotFalse($path);
        file_put_contents($path, $this->serviceAccountJson());

        config([
            'availability.calendar_ids' => ['primary@example.com'],
            'availability.google.service_account_json' => $path,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            'www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => ['primary@example.com' => ['busy' => []]],
            ]),
        ]);

        try {
            $this->getJson('/api/availability?month=2026-12')->assertOk();
        } finally {
            unlink($path);
        }
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

    private function configureServiceAccount(): void
    {
        config([
            'availability.calendar_ids' => ['primary@example.com', 'studio@example.com'],
            'availability.hold_calendar_id' => 'holds@example.com',
            'availability.google.service_account_json' => $this->serviceAccountJson(),
        ]);
    }

    private function serviceAccountJson(): string
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($key);
        $this->assertTrue(openssl_pkey_export($key, $private));

        return json_encode([
            'client_email' => 'availability@example.iam.gserviceaccount.com',
            'private_key' => $private,
        ], JSON_THROW_ON_ERROR);
    }
}
