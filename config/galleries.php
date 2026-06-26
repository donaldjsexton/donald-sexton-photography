<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Client Gallery Storage Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk that client-gallery originals and their generated
    | WebP renditions are written to and streamed from. Defaults to the
    | S3-compatible `s3` disk, which is R2-ready via AWS_ENDPOINT. Point this
    | at any configured disk if galleries should live somewhere else.
    |
    */

    'disk' => env('GALLERY_DISK', 's3'),

];
