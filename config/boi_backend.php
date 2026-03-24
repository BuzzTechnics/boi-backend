<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Register package HTTP routes
    |--------------------------------------------------------------------------
    |
    | When true, BoiBackendServiceProvider registers the proxy and file routes
    | with sensible defaults (Sanctum, Jetstream session + verified when present,
    | TrustedSources on API file routes). Set false only if you register routes manually.
    |
    */
    'register_routes' => (bool) env('BOI_BACKEND_REGISTER_ROUTES', true),

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
