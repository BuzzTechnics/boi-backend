<?php

namespace Boi\Backend\Support;

use Illuminate\Support\Facades\Log;

/**
 * Opt-in structured logs for file upload delegation and bucket resolution ({@see config('boi_files.trace')}).
 */
final class BoiFilesTrace
{
    public static function enabled(): bool
    {
        return (bool) config('boi_files.trace');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(string $phase, array $context = []): void
    {
        if (! self::enabled()) {
            return;
        }

        Log::info('[boi-files] '.$phase, $context);
    }
}
