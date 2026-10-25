<?php

/*
|--------------------------------------------------------------------------
| Legacy WordPress paths that are not posts
|--------------------------------------------------------------------------
|
| Post and wedding-story URLs get their redirects from the `redirects` table
| (written by the importers). The paths below are the structural ones the old
| WordPress/Elementor site exposed: standalone pages, the blog index, archives
| and XML sitemaps. A Redirect row or a published Page with the same path
| always wins over anything here.
|
| Each exact entry is "old path" => [destination, HTTP status].
|
*/

return [

    'exact' => [
        '/contact' => ['/inquire', 301],
        '/documentary-wedding-photographer' => ['/weddings', 301],
        '/engagement-photography' => ['/weddings', 301],
        '/minis' => ['/inquire', 301],
        '/preferred-vendors' => ['/venues', 301],
        '/your-story' => ['/about', 301],
    ],

];
