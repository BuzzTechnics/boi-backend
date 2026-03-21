<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Upstream boi-api
    |--------------------------------------------------------------------------
    */
    'url' => rtrim((string) env('BOI_API_URL', ''), '/'),
    'key' => (string) env('BOI_API_KEY', ''),
    'timeout' => (int) env('BOI_API_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Headers sent to boi-api (must match boi-api config)
    |--------------------------------------------------------------------------
    */
    'user_header' => (string) env('BOI_USER_HEADER', 'X-Boi-User'),
    'app_header' => (string) env('BOI_APP_HEADER', 'X-Boi-App'),

    /** Short slug for this product (stored on bank_statements.app upstream), e.g. glow, portal */
    'app' => (string) env('BOI_APP', 'app'),

    /*
    |--------------------------------------------------------------------------
    | Path validation — proxied path must start with this (e.g. api/)
    |--------------------------------------------------------------------------
    */
    'path_prefix' => 'api/',

    /*
    |--------------------------------------------------------------------------
    | Route registration (use BoiBackend::proxyRoute() inside your middleware group)
    |--------------------------------------------------------------------------
    */
    'route_template' => 'api/boi-api/{path}',
    'route_name' => 'api.boi-api.proxy',

];
