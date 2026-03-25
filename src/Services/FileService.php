<?php

namespace Boi\Backend\Services;

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
            throw new \RuntimeException('Failed to upload file');
        }

        return $path;
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
