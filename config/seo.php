<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Open Graph Image
    |--------------------------------------------------------------------------
    |
    | URL or path used as the og:image / twitter:image fallback for any page
    | that does not declare its own image. Relative paths are resolved against
    | the application URL. Leave empty to omit the tags when no per-page image
    | is available.
    |
    */

    'default_og_image' => env('SEO_DEFAULT_OG_IMAGE', ''),

    /*
    |--------------------------------------------------------------------------
    | Public Page Edge Cache
    |--------------------------------------------------------------------------
    |
    | Seconds a CDN may serve a public, cookie-free page before asking the app
    | again (sent as s-maxage). Set to 0 to turn the header off.
    |
    */

    'public_cache_ttl' => (int) env('PUBLIC_CACHE_TTL', 300),

];
