<?php

namespace Boi\Backend\Support;

/**
 * Trusted server-to-server header for optional S3 bucket on boi-api (allow-listed there).
 */
final class BoiFileHeaders
{
    public const TARGET_BUCKET = 'X-Boi-Files-Bucket';
}
