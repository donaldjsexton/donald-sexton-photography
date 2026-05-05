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
     * Calendar sync. Returns null when the inquiry isn't booked or has
     * no event date (nothing to put on the calendar yet).
     */
    public function syncFromInquiry(Inquiry $inquiry): ?BookedJob
    {
        if ($inquiry->status !== 'booked' || $inquiry->event_date === null) {
            return null;
        }

        $coupleNames = trim(implode(' & ', array_filter([
            $inquiry->primary_name,
            $inquiry->partner_name,
        ])));

        $summary = $coupleNames.' — '.Str::headline((string) $inquiry->event_type);

        return BookedJob::updateOrCreate(
            ['inquiry_id' => $inquiry->id],
            [
                'google_event_id' => $inquiry->calendar_event_id,
                'summary' => Str::limit($summary, 255),
                'couple_names' => $coupleNames !== '' ? Str::limit($coupleNames, 255) : null,
                'event_date' => $inquiry->event_date->toDateString(),
                'location' => Str::limit($inquiry->venue_name ?? $inquiry->location_city ?? '', 255) ?: null,
                'status' => 'confirmed',
                'synced_at' => now(),
            ]
        );
    }
}
