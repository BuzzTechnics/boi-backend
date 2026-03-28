<?php

/**
 * BOI enterprise HTTP APIs (third-party BVN/NIN, CAC verify) and Rubikon customer API.
 *
 * Credentials use the same env vars as typical Glow `config/services.php` entries.
 *
 * @see \Boi\Backend\Services\BOI
 * @see \Boi\Backend\Services\Rubikon
 */
return [

    'boi_thirdparty' => [
        'api_base_url' => rtrim((string) env('BOI_THIRDPARTY_API_BASE', 'https://boiprodsvr01.boi.ng:8249'), '/'),
        'cac_verify_base_url' => rtrim((string) env('BOI_CAC_VERIFY_BASE', 'https://boibonstage01.boi.ng:8280'), '/'),
        'username' => env('BOI_USERNAME'),
        'password' => env('BOI_PASSWORD'),
        'username_prod' => env('BOI_PROD_USERNAME'),
        'password_prod' => env('BOI_PROD_PASSWORD'),
        'token_cache_ttl_hours' => (int) env('BOI_THIRDPARTY_TOKEN_CACHE_HOURS', 12),
        'http_timeout' => (int) env('BOI_THIRDPARTY_HTTP_TIMEOUT', 120),
    ],

    'rubikon' => [
        'api_base_url' => rtrim((string) env('RUBIKON_API_BASE', 'https://boiprodsvr01.boi.ng:8260'), '/'),
        'username' => env('RUBIKON_USERNAME'),
        'password' => env('RUBIKON_PASSWORD'),
        'username_prod' => env('RUBIKON_PROD_USERNAME'),
        'password_prod' => env('RUBIKON_PROD_PASSWORD'),
        'token_cache_ttl_hours' => (int) env('RUBIKON_TOKEN_CACHE_HOURS', 6),
        'http_timeout' => (int) env('RUBIKON_HTTP_TIMEOUT', 120),
    ],

];
