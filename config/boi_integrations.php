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

    // BOI Active Directory staff list (GET /api/Employee/SearchUsersActiveDirectory).
    // Used to provision internal/admin users from the authoritative staff directory
    // instead of a hand-maintained list. AD returns service/machine accounts too, so
    // \Boi\Backend\Support\ActiveDirectoryStaff filters to real, assignable people.
    'active_directory' => [
        // Host that serves the Employee endpoints and issues its token. Falls back to
        // the third-party API base (:8249) when unset — the same host in most envs.
        'base_url' => env('BOI_AD_API_BASE') ? rtrim((string) env('BOI_AD_API_BASE'), '/') : null,
        // Auth for that host; falls back to the shared third-party prod credentials.
        'username' => env('BOI_AD_USERNAME'),
        'password' => env('BOI_AD_PASSWORD'),
        'token_cache_ttl_hours' => (int) env('BOI_AD_TOKEN_CACHE_HOURS', 6),
        'search_min_length' => (int) env('BOI_AD_SEARCH_MIN_LENGTH', 3),
        'search_cache_ttl_minutes' => (int) env('BOI_AD_SEARCH_CACHE_MINUTES', 10),
        'default_limit' => (int) env('BOI_AD_SEARCH_DEFAULT_LIMIT', 25),

        // Free-text AD job title => app role. Ordered; the first role whose patterns
        // match wins, and no match returns null ("AD has no opinion"). Consuming funds
        // publish this config and set their own role names (e.g. GLOW uses "Supervisor"
        // where SPAF uses "Group Head"). Nothing here revokes a role — provisioning is
        // additive by design.
        'role_map' => [
            [
                'role' => 'Project Officer',
                'patterns' => ['/\bproject officer\b/', '/\bte(?:am|an) me(?:mb|m|b)er\b/', '/^tm\b/'],
            ],
            [
                'role' => 'Group Head',
                'patterns' => ['/\bstate manager\b/', '/\bgroup head\b/', '/^sm\b/', '/^gh\b/'],
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
