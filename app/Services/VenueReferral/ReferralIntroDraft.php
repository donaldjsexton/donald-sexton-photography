<?php

namespace App\Services\VenueReferral;

use App\Models\Inquiry;
use Illuminate\Support\Str;

class ReferralIntroDraft
{
    /**
     * Build an editable, couple-facing intro draft for a referral lead.
     * Mirrors the tone of the VenueReferralIntro email but as plain text
     * Donald can edit before sending from the admin reply box.
     */
    public function for(Inquiry $inquiry): string
    {
        $firstName = trim(Str::before((string) $inquiry->primary_name, ' '));
        $greeting = $firstName !== '' ? "Hi {$firstName}," : 'Hi there,';

        $venueName = trim((string) ($inquiry->venue?->name ?: $inquiry->venue_name));
        $contact = trim((string) $inquiry->venue?->referral_contact_name);

        $referrer = match (true) {
            $contact !== '' && $venueName !== '' => "{$contact} at {$venueName}",
            $venueName !== '' => "The team at {$venueName}",
            default => 'Your venue',
        };

        $location = $venueName !== '' ? " at {$venueName}" : '';
        $date = $inquiry->event_date ? ' on '.$inquiry->event_date->format('F j, Y') : '';

        $lines = [
            $greeting,
            '',
            "{$referrer} passed your details along — congratulations, and I'd love to be part of your wedding{$location}{$date}.",
            '',
            "I'll follow up with planning details and next steps shortly. In the meantime, a few recent wedding stories on my site are the easiest way to get a feel for how I work.",
            '',
            'If anything comes up — a question about coverage, the timeline, or anything else — just reply here. I read every message myself.',
            '',
            'Warmly,',
            'Donald Sexton',
            'Donald Sexton Photography',
        ];

        return implode("\n", $lines);
    }
}
