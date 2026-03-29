<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Filesystem disk for uploads (see FileController, FileService)
    |--------------------------------------------------------------------------
    |
    | Always the `s3` disk — configure `filesystems.disks.s3` (AWS_BUCKET, keys, region).
    |
    */
    'disk' => 's3',

    /*
    |--------------------------------------------------------------------------
    | Default S3 key prefix for uploads (host app’s Storage::disk('s3'))
    |--------------------------------------------------------------------------
    */
    'default_folder' => env('BOI_FILES_DEFAULT_FOLDER', env('FILES_DEFAULT_FOLDER', 'documents')),

    /*
    |--------------------------------------------------------------------------
    | Upload limits (kilobytes)
    |--------------------------------------------------------------------------
    */
    'upload' => [
        'max_size_kb' => (int) env('BOI_FILES_MAX_SIZE_KB', env('FILES_MAX_SIZE_KB', 10240)),
        'contexts' => [
            'bank_statement' => (int) env('BOI_FILES_BANK_STATEMENT_MAX_KB', env('FILES_BANK_STATEMENT_MAX_KB', 20480)),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Delegate uploads/views to boi-api (server-to-server)
    |--------------------------------------------------------------------------
    |
    | When boi_proxy.url + boi_proxy.key are set: null = delegate; false = never; true = delegate.
    |
    */
    'delegate_to_boi_api' => ($v = env('BOI_FILES_DELEGATE_TO_API')) === null ? null : filter_var($v, FILTER_VALIDATE_BOOLEAN),

    /*
    | Optional S3 bucket name sent to boi-api as X-Boi-Files-Bucket.
    | Leave empty so delegated uploads use boi-api’s default bucket even when this app’s AWS_BUCKET differs.
    */
    'target_bucket' => env('BOI_FILES_TARGET_BUCKET', ''),

    /*
    | Optional override for POST /api/files/upload when context=bank_statement (boi-ui EDOC manual PDF).
    | When empty, the browser proxy omits X-Boi-Files-Bucket so boi-api uses its default bucket (boiapi).
    | Set only if bank statements must land in a different bucket than boi-api’s AWS_BUCKET.
    */
    'bank_statement_target_bucket' => trim((string) env('BOI_FILES_BANK_STATEMENT_BUCKET', '')),

    'delegate_timeout' => (int) env('BOI_FILES_DELEGATE_TIMEOUT', 300),

    /*
    |--------------------------------------------------------------------------
    | Trusted alternate bucket (boi-api only; never enable on browser-facing apps)
    |--------------------------------------------------------------------------
    */
    'accept_target_bucket' => filter_var(env('BOI_FILES_ACCEPT_TARGET_BUCKET', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Trace logging (local debugging)
    |--------------------------------------------------------------------------
    |
    | Set BOI_FILES_TRACE=true to log delegation and bucket resolution at INFO
    | (look for prefix [boi-files] in storage/logs/laravel.log).
    |
    */
    'trace' => filter_var(env('BOI_FILES_TRACE', false), FILTER_VALIDATE_BOOLEAN),

];
