<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Contract numbering
    |--------------------------------------------------------------------------
    |
    | Prefix used when auto-generating contract numbers. Final format is
    | "{prefix}-{YYYY}-{####}", e.g. CTR-2026-0001.
    |
    */
    'number_prefix' => env('CONTRACTS_NUMBER_PREFIX', 'CTR'),

    /*
    |--------------------------------------------------------------------------
    | Public links
    |--------------------------------------------------------------------------
    |
    | TTL (in days) for signed contract view URLs sent to clients.
    |
    */
    'signed_url_ttl_days' => env('CONTRACTS_LINK_TTL_DAYS', 90),
];
