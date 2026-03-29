<?php

namespace Boi\Backend\Support;

/**
 * Trusted server-to-server header for optional S3 bucket on boi-api (DNS-compliant name).
 */
final class BoiFileHeaders
{
    public const TARGET_BUCKET = 'X-Boi-Files-Bucket';
}
