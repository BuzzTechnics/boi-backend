<?php

return [
    'model' => env('BOI_BANK_MODEL', 'App\\Models\\Bank'),

    // Which provider the bank list is synced from: 'paystack' (default) or
    // 'monnify'. Used by Boi\Backend\Support\Banks::sync().
    'provider' => env('BOI_BANK_PROVIDER', 'paystack'),

    // Mirror the provider on sync: remove codes the provider no longer returns
    // (so switching providers doesn't leave a stale union of both). Set false to
    // keep sync additive (e.g. if you hand-maintain extra bank rows).
    'prune' => env('BOI_BANK_PRUNE', true),

    // Monnify (Moniepoint) credentials, required when provider = monnify.
    'monnify' => [
        'base_url' => env('MONNIFY_BASE_URL', 'https://api.monnify.com'),
        'api_key' => env('MONNIFY_KEY'),
        'secret_key' => env('MONNIFY_SECRET'),
    ],
];
