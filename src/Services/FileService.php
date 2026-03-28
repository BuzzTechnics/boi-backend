<?php

namespace Boi\Backend\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Stores uploads on the host app’s configured disk (typically {@see Storage::disk('s3')}).
 */
final class FileService
{
    public static function storeFile(UploadedFile $file, string $folder, string $disk = 's3'): string
    {
        $ext = $file->getClientOriginalExtension()
            ?: self::extensionFromMime($file->getMimeType());
        $filename = Str::random(10).'.'.$ext;
        $path = $file->storeAs($folder, $filename, $disk);
        if (! is_string($path) || $path === '') {
            throw new \RuntimeException(
                "Failed to store upload on disk [{$disk}]. Check filesystems.disks.s3 (AWS credentials, region, and bucket)."
            );
        }

        return $path;
    }

    public static function storeToFilesystem(UploadedFile $file, string $folder, Filesystem $disk): string
    {
        $ext = $file->getClientOriginalExtension()
            ?: self::extensionFromMime($file->getMimeType());
        $filename = Str::random(10).'.'.$ext;
        $folder = trim(str_replace('\\', '/', $folder), '/');
        $relativePath = $folder !== '' ? $folder.'/'.$filename : $filename;

        $binary = file_get_contents($file->getRealPath() ?: '');
        if ($binary === false) {
            throw new \RuntimeException('Could not read uploaded file.');
        }

        if (! $disk->put($relativePath, $binary)) {
            throw new \RuntimeException('Failed to store upload on filesystem.');
        }

        return $relativePath;
    }

    protected static function extensionFromMime(?string $mime): string
    {
        $map = [
            'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png',
            'image/gif' => 'gif', 'image/webp' => 'webp', 'application/pdf' => 'pdf',
            'text/plain' => 'txt', 'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/csv' => 'csv',
        ];

        return $map[$mime ?? ''] ?? 'bin';
    }
}
