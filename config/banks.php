<?php

return [
    'model' => env('BOI_BANK_MODEL', 'App\\Models\\Bank'),

    // Which provider the bank list is synced from: 'paystack' (default) or
    // 'monnify'. Used by Boi\Backend\Support\Banks::sync().
    'provider' => env('BOI_BANK_PROVIDER', 'paystack'),

    // Monnify (Moniepoint) credentials, required when provider = monnify.
    'monnify' => [
        'base_url' => env('MONNIFY_BASE_URL', 'https://api.monnify.com'),
        'api_key' => env('MONNIFY_KEY'),
        'secret_key' => env('MONNIFY_SECRET'),
    ],
];
