<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mode
    |--------------------------------------------------------------------------
    |
    | Single switch that determines whether payment gateways operate against
    | their sandbox endpoints or live production endpoints. Set via the
    | PAYMENTS_MODE environment variable.
    |
    | Supported: "sandbox", "live"
    |
    */
    'mode' => env('PAYMENTS_MODE', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */
    'currency' => env('PAYMENTS_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Default tax rate
    |--------------------------------------------------------------------------
    |
    | Percentage value (e.g. 7.000 for 7%). Used to pre-fill new invoice line
    | items. Editable per line item at invoice time.
    |
    */
    'default_tax_rate' => env('PAYMENTS_DEFAULT_TAX_RATE', 7.000),

    /*
    |--------------------------------------------------------------------------
    | Invoice numbering
    |--------------------------------------------------------------------------
    */
    'invoice_number_prefix' => env('PAYMENTS_INVOICE_PREFIX', 'INV'),

    /*
    |--------------------------------------------------------------------------
    | Gateways
    |--------------------------------------------------------------------------
    |
    | Per-gateway credentials and feature flags. Square and PayPal credentials
    | are wired up in their respective phases; placeholders are kept here so
    | configuration lives in one place.
    |
    */
    'gateways' => [
        'square' => [
            'enabled' => env('SQUARE_ENABLED', false),
            'sandbox' => [
                'access_token' => env('SQUARE_SANDBOX_ACCESS_TOKEN'),
                'application_id' => env('SQUARE_SANDBOX_APPLICATION_ID'),
                'location_id' => env('SQUARE_SANDBOX_LOCATION_ID'),
                'webhook_signature_key' => env('SQUARE_SANDBOX_WEBHOOK_SIGNATURE_KEY'),
            ],
            'live' => [
                'access_token' => env('SQUARE_ACCESS_TOKEN'),
                'application_id' => env('SQUARE_APPLICATION_ID'),
                'location_id' => env('SQUARE_LOCATION_ID'),
                'webhook_signature_key' => env('SQUARE_WEBHOOK_SIGNATURE_KEY'),
            ],
        ],

        'paypal' => [
            'enabled' => env('PAYPAL_ENABLED', false),
            'sandbox' => [
                'client_id' => env('PAYPAL_SANDBOX_CLIENT_ID'),
                'client_secret' => env('PAYPAL_SANDBOX_CLIENT_SECRET'),
                'webhook_id' => env('PAYPAL_SANDBOX_WEBHOOK_ID'),
            ],
            'live' => [
                'client_id' => env('PAYPAL_CLIENT_ID'),
                'client_secret' => env('PAYPAL_CLIENT_SECRET'),
                'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
            ],
        ],
    ],
];
