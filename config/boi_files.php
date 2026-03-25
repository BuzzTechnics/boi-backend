<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Filesystem disk for uploads (see FileController, FileService)
    |--------------------------------------------------------------------------
    |
    | When null/empty, the package picks: s3 if AWS_BUCKET is set, or s3 outside
    | the local environment (tests use Storage::fake('s3')), else "public" in local
    | when the bucket is empty (dev without AWS).
    |
    */
    'disk' => env('BOI_FILES_DISK'),

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

];
