<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public availability calendar
    |--------------------------------------------------------------------------
    |
    | The inquiry page reads free/busy time for the primary calendar on the
    | Google account connected in Admin → Settings, plus studio jobs already
    | stored here. Event titles, descriptions, and guests are never requested.
    |
    | Mock mode draws a repeating sample month so the grid can be previewed
    | without that connection. It is ignored when APP_ENV=production.
    |
    */

    'mock' => filter_var(env('AVAILABILITY_MOCK', false), FILTER_VALIDATE_BOOL),

    'timezone' => env('AVAILABILITY_TIMEZONE', 'America/New_York'),

    // Dates before today + this many days are unavailable. Today + 14 is the
    // first date that can be open when the value is 14.
    'lead_time_days' => (int) env('AVAILABILITY_LEAD_TIME_DAYS', 14),

    // Real elapsed busy time, after overlapping blocks are merged.
    // 6 or more hours, or a block that covers the whole local day, is booked.
    // At least 2 hours and under 6 is limited. Under 2 stays open.
    'booked_hours' => (float) env('AVAILABILITY_BOOKED_HOURS', 6),
    'limited_hours' => (float) env('AVAILABILITY_LIMITED_HOURS', 2),

    // Clamped to 5–10 minutes when the response is cached.
    'cache_seconds' => (int) env('AVAILABILITY_CACHE_SECONDS', 600),

    // Months after the current month that can still be viewed.
    'horizon_months' => (int) env('AVAILABILITY_HORIZON_MONTHS', 18),

];
