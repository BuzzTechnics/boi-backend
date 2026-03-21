<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legacy: EDOC filesystem disk (unused in code)
    |--------------------------------------------------------------------------
    |
    | Statement CSVs and uploads use the **`s3`** disk everywhere. Keys such as
    | `edoc/statements/{uuid}.csv` live in the same bucket as `AWS_BUCKET` on `s3`.
    |
    */
    'filesystem_disk' => env('BOI_EDOC_FILESYSTEM_DISK', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Path prefix for EDOC statement CSV objects on S3
    |--------------------------------------------------------------------------
    |
    | Must match boi-api EDOC_STATEMENTS_PATH_PREFIX / TransferEdocFiles storage key.
    |
    */
    'statements_path_prefix' => env('BOI_EDOC_STATEMENTS_PATH_PREFIX', 'edoc/statements'),

    /*
    |--------------------------------------------------------------------------
    | Legacy: presign toggles (upload/view code uses fixed 5‑minute presign on `s3`)
    |--------------------------------------------------------------------------
    */
    'use_signed_urls' => filter_var(
        env('BOI_EDOC_S3_USE_SIGNED_URLS', 'true'),
        FILTER_VALIDATE_BOOLEAN
    ),

    'signed_url_ttl_minutes' => (int) env('BOI_EDOC_SIGNED_URL_TTL', 15),

];
