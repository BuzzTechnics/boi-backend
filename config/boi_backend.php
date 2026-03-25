<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Register boi-api proxy route (`/api/boi-api/{path}`)
    |--------------------------------------------------------------------------
    |
    | Independent of file routes. Set false on boi-api itself (no self-proxy).
    |
    */
    'register_routes' => true,

    /*
    |--------------------------------------------------------------------------
    | Register /api/files/upload and /api/files/view
    |--------------------------------------------------------------------------
    |
    | Uses the host app’s S3 disk. Host apps that register their own `/api/files/*`
    | (e.g. boi-api with auth.proxy) should set this to false in AppServiceProvider.
    |
    */
    'register_file_routes' => true,

    /*
    |--------------------------------------------------------------------------
    | Extra route middleware (optional)
    |--------------------------------------------------------------------------
    |
    | Appended to the package defaults. Publish this config only when you need
    | additional middleware.
    |
    */
    'extra_proxy_middleware' => [],

    'extra_api_middleware' => [],

];
