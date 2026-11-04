<?php

namespace App\Services\Availability;

use App\Models\BookedJob;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class AvailabilityCalendar
{
    public function __construct(private readonly GoogleFreeBusyGateway $freeBusy) {}

    public function isReady(): bool
    {
        if ($this->usesMock()) {
            return true;
        }

        return $this->freeBusy->configured();
    }

    /**
     * @return array{ready: bool, endpoint: string, month: string, min_month: string, max_month: string, timezone: string}
     */
    public function presentation(): array
    {
        $current = CarbonImmutable::now($this->timezone())->startOfMonth();

        return [
            'ready' => $this->isReady(),
            'endpoint' => route('availability.show'),
            'month' => $current->format('Y-m'),
            'min_month' => $current->format('Y-m'),
            'max_month' => $current->addMonths($this->horizonMonths())->format('Y-m'),
            'timezone' => $this->timezone(),
        ];
    }

    /**
     * @return array{month: string, days: list<array{date: string, status: string}>}
     */
    public function forMonth(string $month): array
    {
        $start = $this->monthStart($month);
        $this->assertInsideWindow($start);

        /** @var array{month: string, days: list<array{date: string, status: string}>} $payload */
        $payload = Cache::remember(
            $this->cacheKey($month),
            $this->cacheSeconds(),
            function () use ($month, $start): array {
                [$busy, $holds] = $this->intervalsFor($start);

                return [
                    'month' => $month,
                    'days' => $this->classifier()->month($start, $busy, $holds),
                ];
            },
        );

        return $payload;
    }

    public function cacheSeconds(): int
    {
        return max(300, min(600, (int) config('availability.cache_seconds', 600)));
    }

    /**
     * @return array{0: list<BusyInterval>, 1: list<BusyInterval>}
     */
    private function intervalsFor(CarbonImmutable $start): array
    {
        if ($this->usesMock()) {
            $mock = (new MockBusyIntervals)->forMonth($start);

            return [$mock['busy'], $mock['holds']];
        }

        $end = $start->addMonth();
        $busy = $this->freeBusy->busyRanges($start, $end);
        [$jobBusy, $holds] = $this->bookedJobIntervals($start, $end);

        return [array_merge($busy, $jobBusy), $holds];
    }

    /**
     * Studio jobs supply booked and held days without sending their names,
     * venues, or notes. Confirmed and completed jobs cover the local day.
     * A job that is only penciled in is a hold, unless free/busy time already
     * marks the day booked.
     *
     * @return array{0: list<BusyInterval>, 1: list<BusyInterval>}
     */
    private function bookedJobIntervals(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $jobs = BookedJob::query()
            ->whereNotNull('event_date')
            ->where('status', '!=', 'cancelled')
            ->whereDate('event_date', '>=', $start->toDateString())
            ->whereDate('event_date', '<', $end->toDateString())
            ->with(['contracts', 'invoices.installments'])
            ->get();

        $busy = [];
        $holds = [];

        foreach ($jobs as $job) {
            $day = CarbonImmutable::parse($job->event_date->toDateString(), $this->timezone())->startOfDay();
            if ($job->status === 'completed') {
                $busy[] = new BusyInterval($day, $day->addDay());

                continue;
            }

            $stage = $job->confirmationStage();

            if ($stage === 'confirmed') {
                $busy[] = new BusyInterval($day, $day->addDay());

                continue;
            }

            if ($stage === 'held' || $stage === 'tentative') {
                $holds[] = new BusyInterval($day->setTime(12, 0), $day->setTime(13, 0));
            }
        }

        return [$busy, $holds];
    }

    private function assertInsideWindow(CarbonImmutable $start): void
    {
        $current = CarbonImmutable::now($this->timezone())->startOfMonth();
        $last = $current->addMonths($this->horizonMonths());

        if ($start->lessThan($current) || $start->greaterThan($last)) {
            throw new AvailabilityOutOfRange('That month is outside the booking window.');
        }
    }

    private function monthStart(string $month): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01', $this->timezone());

        if (! $parsed instanceof CarbonImmutable) {
            throw new AvailabilityOutOfRange('Provide a month as YYYY-MM.');
        }

        return $parsed->startOfDay();
    }

    private function classifier(): DateAvailabilityClassifier
    {
        return new DateAvailabilityClassifier(
            timezone: $this->timezone(),
            leadTimeDays: max(0, (int) config('availability.lead_time_days', 14)),
            bookedSeconds: $this->seconds((float) config('availability.booked_hours', 6)),
            limitedSeconds: $this->seconds((float) config('availability.limited_hours', 2)),
        );
    }

    private function seconds(float $hours): int
    {
        return (int) round(max(0, $hours) * 3600);
    }

    private function horizonMonths(): int
    {
        return max(0, (int) config('availability.horizon_months', 18));
    }

    private function usesMock(): bool
    {
        return (bool) config('availability.mock') && ! app()->isProduction();
    }

    private function timezone(): string
    {
        $timezone = (string) config('availability.timezone', 'America/New_York');

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            return 'America/New_York';
        }

        return $timezone;
    }

    private function cacheKey(string $month): string
    {
        $fingerprint = hash('sha256', (string) json_encode([
            'mock' => $this->usesMock(),
            'calendar' => GoogleFreeBusyGateway::CALENDAR_ID,
            'lead' => (int) config('availability.lead_time_days', 14),
            'booked' => (float) config('availability.booked_hours', 6),
            'limited' => (float) config('availability.limited_hours', 2),
            'timezone' => $this->timezone(),
        ]));

        return 'availability:month:'.$month.':'.$fingerprint;
    }
}
