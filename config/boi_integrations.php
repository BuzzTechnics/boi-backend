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

        // Per-fund credentials for the IdentityVerification.API gateway
        // (cac_verify_base_url / :8280 — CAC, BVN and NIN). boi-api is
        // multi-tenant: the fund is derived per-request from the caller's app
        // slug (X-Boi-App header), the same signal that stamps bvn_nin_calls.project.
        // BOI provisions one gateway account per consuming application, so a
        // mapped caller authenticates as its own account and BOI attributes the
        // activity to that fund instead of lumping it under the default account.
        // Map app slug => ['username' => ..., 'password' => ...]. A caller with
        // no entry (or blank credentials) uses the 'default' profile below —
        // i.e. username_prod/password_prod — so existing funds are unchanged.
        'identity_profiles' => [
            'adf' => [
                'username' => env('BOI_ADF_USERNAME'),
                'password' => env('BOI_ADF_PASSWORD'),
            ],
        ],
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
