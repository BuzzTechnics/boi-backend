<?php

namespace Boi\Backend\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Builds and caches S3 disks that share credentials with the app’s configured upload disk
 * ({@see config('boi_files.disk')}) but use a different bucket, when that bucket is allow-listed
 * or {@see config('boi_files.allow_any_target_bucket')} is enabled (boi-api).
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
     * Disk used for file routes ({@see config('boi_files.disk')}, usually `s3`).
     */
    private static function uploadDiskName(): string
    {
        return (string) config('boi_files.disk', 's3');
    }

    /**
     * Normalized bucket name for the upload disk (canonical “default” for allow-list checks).
     */
    private static function defaultBucket(): string
    {
        $name = self::uploadDiskName();

        return self::normalizeBucketName((string) config("filesystems.disks.{$name}.bucket", ''));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function uploadS3DiskConfig(): ?array
    {
        $cfg = config('filesystems.disks.'.self::uploadDiskName());
        if (! is_array($cfg) || ($cfg['driver'] ?? '') !== 's3') {
            return null;
        }

        return $cfg;
    }

    /**
     * @param  string|null  $bucket  null or empty → default {@see config('boi_files.disk')}.
     */
    public static function diskForBucket(?string $bucket): Filesystem
    {
        $defaultBucket = self::defaultBucket();
        $bucket = $bucket !== null ? self::normalizeBucketName($bucket) : '';

        /*
         * No bucket name in config (e.g. AWS_BUCKET unset): we cannot tell whether a header matches
         * “the default”, so ignore X-Boi-Files-Bucket / ?bucket= and use the configured disk.
         */
        if ($defaultBucket === '') {
            BoiFilesTrace::log('dynamic_s3.branch', [
                'branch' => 'default_bucket_empty_use_disk',
                'upload_disk' => self::uploadDiskName(),
                'requested_bucket_raw' => $bucket,
            ]);

            return Storage::disk(self::uploadDiskName());
        }

        if ($bucket === '' || $bucket === $defaultBucket) {
            BoiFilesTrace::log('dynamic_s3.branch', [
                'branch' => 'use_default_disk',
                'upload_disk' => self::uploadDiskName(),
                'default_bucket' => $defaultBucket,
                'requested_normalized' => $bucket !== '' ? $bucket : '(empty)',
            ]);

            return Storage::disk(self::uploadDiskName());
        }

        if (! self::bucketIsAllowed($bucket)) {
            BoiFilesTrace::log('dynamic_s3.branch', [
                'branch' => 'reject_not_allowed',
                'requested' => $bucket,
                'default_bucket' => $defaultBucket,
                'allowed_extras' => config('boi_files.allowed_target_buckets', []),
                'allow_any' => (bool) config('boi_files.allow_any_target_bucket', false),
            ]);

            abort(422, 'The requested storage bucket is not allowed.');
        }

        if (! isset(self::$cache[$bucket])) {
            $base = self::uploadS3DiskConfig();
            if ($base === null) {
                abort(500, 'S3 is not configured.');
            }

            BoiFilesTrace::log('dynamic_s3.branch', [
                'branch' => 'build_alternate_disk',
                'bucket' => $bucket,
            ]);

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
        $bucket = self::normalizeBucketName($bucket);
        if ($bucket === '') {
            return false;
        }

        if (filter_var(config('boi_files.allow_any_target_bucket', false), FILTER_VALIDATE_BOOLEAN)) {
            return self::isDnsCompliantS3BucketLabel($bucket);
        }

        $extra = config('boi_files.allowed_target_buckets', []);
        if (! is_array($extra)) {
            $extra = [];
        }

        $default = self::defaultBucket();
        $normalizedExtras = array_map(self::normalizeBucketName(...), $extra);
        $all = array_values(array_unique(array_filter([$default, ...$normalizedExtras])));

        return in_array($bucket, $all, true);
    }

    /**
     * S3 DNS-style bucket name rules (simplified): 3–63 chars, lowercase alnum, dot, hyphen; labels separated by dots.
     */
    private static function isDnsCompliantS3BucketLabel(string $bucket): bool
    {
        $len = strlen($bucket);
        if ($len < 3 || $len > 63) {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/', $bucket);
    }

    private static function normalizeBucketName(string|int|float $bucket): string
    {
        return strtolower(trim((string) $bucket));
    }
}
