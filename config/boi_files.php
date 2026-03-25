<?php

return [

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
