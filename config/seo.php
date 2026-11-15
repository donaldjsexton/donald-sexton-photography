<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Open Graph Image
    |--------------------------------------------------------------------------
    |
    | URL or path used as the og:image / twitter:image fallback for any page
    | that does not declare its own image. Relative paths are resolved against
    | the application URL. The default is the journal photograph copied into
    | public media storage. Leave the env value empty to omit the tags when
    | no per-page image is available.
    |
    */

    'default_og_image' => env('SEO_DEFAULT_OG_IMAGE', '/storage/media/site/journal-share.jpg'),

    /*
    |--------------------------------------------------------------------------
    | Migrated Share Images
    |--------------------------------------------------------------------------
    |
    | Legacy WordPress upload paths that have been copied onto the public
    | media disk. Share tags and imported markup that still cite the old
    | path are rewritten to the new one.
    |
    */

    'migrated_share_images' => [
        '/wp-content/uploads/2023/11/DSCF7254-Enhanced-NR-copy.jpg' => '/storage/media/site/journal-share.jpg',
    ],

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
