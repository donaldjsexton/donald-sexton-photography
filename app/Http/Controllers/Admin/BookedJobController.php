<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookedJob;
use App\Services\CalendarSync;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BookedJobController extends Controller
{
    /** Minimum seconds between on-demand syncs triggered by page loads. */
    private const SYNC_THROTTLE_SECONDS = 60;

    public function __construct(private readonly CalendarSync $calendarSync) {}

    public function index(Request $request): View
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $this->refreshIfStale();

        $jobs = BookedJob::query()
            ->inMonth($year, $month)
            ->orderBy('event_date')
            ->get();

        $upcoming = BookedJob::upcoming()->limit(5)->get();

        return view('admin.booked-jobs.index', [
            'year' => $year,
            'month' => $month,
            'jobs' => $jobs,
            'upcoming' => $upcoming,
            'calendarDays' => $this->buildCalendarDays($year, $month, $jobs),
            'lastSyncedAt' => BookedJob::query()->latest('synced_at')->first()?->synced_at,
        ]);
    }

    /**
     * Run a Google Calendar sync if the last on-demand attempt was more than
     * SYNC_THROTTLE_SECONDS ago. Cache::add is atomic so concurrent requests
     * cannot trigger overlapping syncs. Failures are swallowed so a Google
     * outage cannot take down the calendar page.
     */
    private function refreshIfStale(): void
    {
        if (! Cache::add('calendar:sync:throttle', true, self::SYNC_THROTTLE_SECONDS)) {
            return;
        }

        try {
            $this->calendarSync->sync();
        } catch (\Throwable $e) {
            Log::warning('On-demand calendar sync failed: '.$e->getMessage());
        }
    }

    public function show(BookedJob $bookedJob): View
    {
        return view('admin.booked-jobs.show', [
            'job' => $bookedJob,
        ]);
    }

    public function update(Request $request, BookedJob $bookedJob): RedirectResponse
    {
        $validated = $request->validate([
            'couple_names' => ['nullable', 'string', 'max:255'],
            'event_time' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'coordinator' => ['nullable', 'string', 'max:255'],
            'ceremony_notes' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:confirmed,cancelled,completed'],
        ]);

        $bookedJob->update($validated);

        return redirect()
            ->route('admin.booked-jobs.show', $bookedJob)
            ->with('status', 'Booked job updated.');
    }

    /**
     * @return array<int, array{day: int, date: string, inMonth: bool, jobs: Collection}>
     */
    private function buildCalendarDays(int $year, int $month, $jobs): array
    {
        $start = now()->setDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Pad to start of week (Sunday).
        $calStart = $start->copy()->startOfWeek(Carbon::SUNDAY);
        // Pad to end of week (Saturday).
        $calEnd = $end->copy()->endOfWeek(Carbon::SATURDAY);

        $jobsByDate = $jobs->groupBy(fn (BookedJob $job) => $job->event_date->toDateString());

        $days = [];
        $cursor = $calStart->copy();

        while ($cursor <= $calEnd) {
            $dateStr = $cursor->toDateString();
            $days[] = [
                'day' => $cursor->day,
                'date' => $dateStr,
                'inMonth' => $cursor->month === $month,
                'isToday' => $cursor->isToday(),
                'jobs' => $jobsByDate->get($dateStr, collect()),
            ];
            $cursor->addDay();
        }

        return $days;
    }
}
