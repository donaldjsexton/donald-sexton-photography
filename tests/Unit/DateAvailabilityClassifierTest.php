<?php

namespace Tests\Unit;

use App\Services\Availability\AvailabilityStatus;
use App\Services\Availability\BusyInterval;
use App\Services\Availability\DateAvailabilityClassifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DateAvailabilityClassifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-04 15:00:00', 'America/New_York'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dates_inside_the_lead_time_are_unavailable_and_the_boundary_day_is_open(): void
    {
        $classifier = $this->classifier();

        $this->assertSame(AvailabilityStatus::Unavailable, $classifier->statusFor($this->date('2026-11-04'), [], []));
        $this->assertSame(AvailabilityStatus::Unavailable, $classifier->statusFor($this->date('2026-11-17'), [], []));
        $this->assertSame(AvailabilityStatus::Open, $classifier->statusFor($this->date('2026-11-18'), [], []));
    }

    public function test_past_dates_stay_unavailable_when_lead_time_is_zero(): void
    {
        $classifier = $this->classifier(leadTimeDays: 0);

        $this->assertSame(AvailabilityStatus::Unavailable, $classifier->statusFor($this->date('2026-11-03'), [], []));
        $this->assertSame(AvailabilityStatus::Open, $classifier->statusFor($this->date('2026-11-04'), [], []));
    }

    public function test_today_follows_new_york_when_utc_has_already_rolled_forward(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-05 03:30:00', 'UTC'));

        $classifier = $this->classifier(leadTimeDays: 0);

        $this->assertSame(AvailabilityStatus::Unavailable, $classifier->statusFor($this->date('2026-11-03'), [], []));
        $this->assertSame(AvailabilityStatus::Open, $classifier->statusFor($this->date('2026-11-04'), [], []));
    }

    #[DataProvider('hourThresholds')]
    public function test_busy_hours_use_the_configured_thresholds(string $start, string $end, AvailabilityStatus $expected): void
    {
        $status = $this->classifier()->statusFor(
            $this->date('2026-12-02'),
            [$this->span($start, $end)],
            [],
        );

        $this->assertSame($expected, $status);
    }

    /**
     * @return array<string, array{string, string, AvailabilityStatus}>
     */
    public static function hourThresholds(): array
    {
        return [
            'one hour fifty nine stays open' => ['2026-12-02 09:00:00', '2026-12-02 10:59:00', AvailabilityStatus::Open],
            'two hours is limited' => ['2026-12-02 09:00:00', '2026-12-02 11:00:00', AvailabilityStatus::Limited],
            'five hours fifty nine is limited' => ['2026-12-02 09:00:00', '2026-12-02 14:59:00', AvailabilityStatus::Limited],
            'six hours is booked' => ['2026-12-02 09:00:00', '2026-12-02 15:00:00', AvailabilityStatus::Booked],
            'eight hours is booked' => ['2026-12-02 09:00:00', '2026-12-02 17:00:00', AvailabilityStatus::Booked],
        ];
    }

    public function test_overlapping_blocks_are_merged_before_the_hour_count(): void
    {
        $status = $this->classifier()->statusFor($this->date('2026-12-02'), [
            $this->span('2026-12-02 09:00:00', '2026-12-02 13:00:00'),
            $this->span('2026-12-02 10:00:00', '2026-12-02 14:00:00'),
        ], []);

        $this->assertSame(AvailabilityStatus::Limited, $status);
    }

    public function test_adjacent_blocks_add_up(): void
    {
        $status = $this->classifier()->statusFor($this->date('2026-12-02'), [
            $this->span('2026-12-02 09:00:00', '2026-12-02 13:00:00'),
            $this->span('2026-12-02 13:00:00', '2026-12-02 17:00:00'),
        ], []);

        $this->assertSame(AvailabilityStatus::Booked, $status);
    }

    public function test_a_block_covering_the_local_day_is_booked_even_above_the_hour_threshold(): void
    {
        $day = $this->date('2026-12-04')->startOfDay();
        $status = $this->classifier(bookedSeconds: 48 * 3600)->statusFor(
            $day,
            [new BusyInterval($day, $day->addDay())],
            [],
        );

        $this->assertSame(AvailabilityStatus::Booked, $status);
    }

    public function test_hold_time_is_held_unless_the_day_is_already_booked(): void
    {
        $classifier = $this->classifier();
        $day = $this->date('2026-12-04');

        $this->assertSame(
            AvailabilityStatus::Held,
            $classifier->statusFor($day, [], [$this->span('2026-12-04 11:00:00', '2026-12-04 12:00:00')]),
        );

        $this->assertSame(
            AvailabilityStatus::Held,
            $classifier->statusFor(
                $day,
                [$this->span('2026-12-04 10:00:00', '2026-12-04 13:00:00')],
                [$this->span('2026-12-04 15:00:00', '2026-12-04 16:00:00')],
            ),
        );

        $this->assertSame(
            AvailabilityStatus::Booked,
            $classifier->statusFor(
                $day,
                [$this->span('2026-12-04 09:00:00', '2026-12-04 17:00:00')],
                [$this->span('2026-12-04 12:00:00', '2026-12-04 13:00:00')],
            ),
        );
    }

    public function test_an_all_day_hold_stays_held(): void
    {
        $day = $this->date('2026-12-04')->startOfDay();
        $status = $this->classifier(bookedSeconds: 48 * 3600)->statusFor(
            $day,
            [],
            [new BusyInterval($day, $day->addDay())],
        );

        $this->assertSame(AvailabilityStatus::Held, $status);
    }

    public function test_all_day_block_covers_the_23_hour_spring_forward_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-01 12:00:00', 'America/New_York'));

        $day = $this->date('2026-03-08')->startOfDay();
        $next = $day->addDay();

        $this->assertSame(23 * 3600, $next->getTimestamp() - $day->getTimestamp());
        $this->assertSame(
            AvailabilityStatus::Booked,
            $this->classifier(bookedSeconds: 48 * 3600)->statusFor($day, [new BusyInterval($day, $next)], []),
        );
    }

    public function test_all_day_block_covers_the_25_hour_fallback_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00', 'America/New_York'));

        $day = $this->date('2026-11-01')->startOfDay();
        $next = $day->addDay();

        $this->assertSame(25 * 3600, $next->getTimestamp() - $day->getTimestamp());
        $this->assertSame(
            AvailabilityStatus::Booked,
            $this->classifier(bookedSeconds: 48 * 3600)->statusFor($day, [new BusyInterval($day, $next)], []),
        );
    }

    public function test_a_utc_midnight_block_is_not_treated_as_a_new_york_all_day(): void
    {
        $status = $this->classifier(bookedSeconds: 20 * 3600)->statusFor(
            $this->date('2026-12-02'),
            [new BusyInterval(
                CarbonImmutable::parse('2026-12-02T00:00:00Z'),
                CarbonImmutable::parse('2026-12-03T00:00:00Z'),
            )],
            [],
        );

        $this->assertSame(AvailabilityStatus::Limited, $status);
    }

    public function test_month_lists_every_local_day_in_order(): void
    {
        $days = $this->classifier()->month($this->date('2026-11-01')->startOfMonth(), [], []);

        $this->assertCount(30, $days);
        $this->assertSame('2026-11-01', $days[0]['date']);
        $this->assertSame('unavailable', $days[0]['status']);
        $this->assertSame('2026-11-30', $days[29]['date']);
        $this->assertSame('open', $days[29]['status']);
        $this->assertSame(['date', 'status'], array_keys($days[0]));
    }

    public function test_empty_or_inverted_intervals_do_not_mark_a_day(): void
    {
        $status = $this->classifier()->statusFor($this->date('2026-12-02'), [
            $this->span('2026-12-02 12:00:00', '2026-12-02 12:00:00'),
            $this->span('2026-12-02 15:00:00', '2026-12-02 14:00:00'),
        ], []);

        $this->assertSame(AvailabilityStatus::Open, $status);
    }

    private function classifier(int $leadTimeDays = 14, int $bookedSeconds = 21600, int $limitedSeconds = 7200): DateAvailabilityClassifier
    {
        return new DateAvailabilityClassifier(
            timezone: 'America/New_York',
            leadTimeDays: $leadTimeDays,
            bookedSeconds: $bookedSeconds,
            limitedSeconds: $limitedSeconds,
        );
    }

    private function date(string $day): CarbonImmutable
    {
        return CarbonImmutable::parse($day, 'America/New_York');
    }

    private function span(string $start, string $end): BusyInterval
    {
        return new BusyInterval(
            CarbonImmutable::parse($start, 'America/New_York'),
            CarbonImmutable::parse($end, 'America/New_York'),
        );
    }
}
