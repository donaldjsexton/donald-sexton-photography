<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Remote upload cap
    |--------------------------------------------------------------------------
    |
    | MCP clients send image bytes as base64 inside a JSON-RPC body. This cap
    | is the decoded size. It is intentionally below the admin form limit
    | because a remote JSON request cannot carry a 12 MB original comfortably.
    |
    */

    'max_upload_bytes' => 4_000_000,

    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ],

];
