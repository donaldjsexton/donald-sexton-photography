<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public availability calendar
    |--------------------------------------------------------------------------
    |
    | The inquiry page reads free/busy time only. Event titles, descriptions,
    | locations, and attendees are never requested. Share each calendar with
    | the credential using "See only free/busy" access.
    |
    | Mock mode draws a repeating sample month so the grid can be previewed
    | without credentials. It is ignored when APP_ENV=production.
    |
    */

    'mock' => filter_var(env('AVAILABILITY_MOCK', false), FILTER_VALIDATE_BOOL),

    'timezone' => env('AVAILABILITY_TIMEZONE', 'America/New_York'),

    // Comma-separated calendar IDs. Busy time on these calendars can mark a
    // day limited or booked. Leave empty to hide the grid.
    'calendar_ids' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('AVAILABILITY_CALENDAR_IDS', '')),
    ))),

    // Optional. Any busy time on this calendar marks the day held, unless the
    // allowlisted calendars already mark it booked.
    'hold_calendar_id' => env('AVAILABILITY_HOLD_CALENDAR_ID') ?: null,

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

    'google' => [
        // Preferred. The service account JSON as one line, or an absolute path
        // to the JSON file. The client requests only the free/busy scope.
        'service_account_json' => env('AVAILABILITY_GOOGLE_SERVICE_ACCOUNT_JSON'),

        // Alternative when a service account is not set. The refresh token
        // must have been issued for the free/busy scope only.
        'client_id' => env('AVAILABILITY_GOOGLE_CLIENT_ID'),
        'client_secret' => env('AVAILABILITY_GOOGLE_CLIENT_SECRET'),
        'refresh_token' => env('AVAILABILITY_GOOGLE_REFRESH_TOKEN'),
    ],

];
