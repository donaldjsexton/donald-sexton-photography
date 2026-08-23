<?php

namespace App\Services;

use App\Models\BookedJob;
use App\Models\Inquiry;
use Illuminate\Support\Str;

class BookedJobSync
{
    /**
     * Mirror a booked Inquiry into the booked_jobs table so it appears
     * on the calendar immediately, without waiting on the next Google
     * Calendar sync. A job is created even when no date has been agreed
     * yet — the booking is real, it simply is not on the calendar — so
     * contracts and invoices have something to attach to.
     *
     * The inquiry's date only ever seeds a new job. Once a job exists the
     * job owns its date, so a re-sync cannot revert a date the studio set
     * by hand.
     */
    public function syncFromInquiry(Inquiry $inquiry): ?BookedJob
    {
        if ($inquiry->status !== 'booked') {
            return null;
        }

        $coupleNames = trim(implode(' & ', array_filter([
            $inquiry->primary_name,
            $inquiry->partner_name,
        ])));

        $job = BookedJob::firstOrNew(['inquiry_id' => $inquiry->id]);

        $job->fill([
            'google_event_id' => $inquiry->calendar_event_id,
            'summary' => BookedJob::buildSummary($coupleNames, $inquiry->event_type),
            'couple_names' => $coupleNames !== '' ? Str::limit($coupleNames, 255) : null,
            'event_type' => $inquiry->event_type,
            'location' => Str::limit($inquiry->venue_name ?? $inquiry->location_city ?? '', 255) ?: null,
            'status' => $job->exists ? $job->status : 'confirmed',
            'synced_at' => now(),
        ]);

        if (! $job->exists || $job->event_date === null) {
            $job->event_date = $inquiry->event_date?->toDateString();
        }

        $job->save();

        return $job;
    }
}
