<?php

namespace Boi\Backend\Support;

/**
 * Query keys for file routes (avoid obvious names like `bucket` in browser URLs).
 */
final class BoiFileQueryParams
{
    /**
     * Target storage scope for dynamic S3 view (same value as {@see BoiFileHeaders::TARGET_BUCKET}).
     */
    public const TID = 'tid';
}
