<?php

namespace Boi\Backend\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Builds and caches S3 disks that share credentials with {@see config('filesystems.disks.s3')}
 * but use a different bucket, when that bucket is allow-listed.
 */
final class DynamicS3Filesystem
{
    /** @var array<string, Filesystem> */
    private static array $cache = [];

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * @param  string|null  $bucket  null or empty → default {@see config('boi_files.disk')} (usual single-bucket disk).
     */
    public static function diskForBucket(?string $bucket): Filesystem
    {
        $defaultBucket = (string) config('filesystems.disks.s3.bucket', '');
        $bucket = $bucket !== null ? trim($bucket) : '';

        if ($bucket === '' || ($defaultBucket !== '' && $bucket === $defaultBucket)) {
            return Storage::disk((string) config('boi_files.disk', 's3'));
        }

        if (! self::bucketIsAllowed($bucket)) {
            abort(422, 'The requested storage bucket is not allowed.');
        }

        if (! isset(self::$cache[$bucket])) {
            $base = config('filesystems.disks.s3');
            if (! is_array($base) || ($base['driver'] ?? '') !== 's3') {
                abort(500, 'S3 is not configured.');
            }

            self::$cache[$bucket] = Storage::build([
                'driver' => 's3',
                'key' => $base['key'] ?? '',
                'secret' => $base['secret'] ?? '',
                'region' => $base['region'] ?? '',
                'bucket' => $bucket,
                'url' => $base['url'] ?? null,
                'endpoint' => $base['endpoint'] ?? null,
                'use_path_style_endpoint' => $base['use_path_style_endpoint'] ?? false,
                'throw' => $base['throw'] ?? false,
            ]);
        }

        return self::$cache[$bucket];
    }

    public static function bucketIsAllowed(string $bucket): bool
    {
        if ($bucket === '') {
            return false;
        }

        $extra = config('boi_files.allowed_target_buckets', []);
        if (! is_array($extra)) {
            $extra = [];
        }

        $default = (string) config('filesystems.disks.s3.bucket', '');
        $all = array_values(array_unique(array_filter([$default, ...$extra])));

        return in_array($bucket, $all, true);
    }
}
