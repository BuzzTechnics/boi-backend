<?php

namespace Boi\Backend\Http\Controllers;

use Boi\Backend\Services\BoiFileApiDelegator;
use Boi\Backend\Services\FileService;
use Boi\Backend\Support\BoiFileHeaders;
use Boi\Backend\Support\DynamicS3Filesystem;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * File upload and presigned redirect: optionally delegates to boi-api when {@see BoiFileApiDelegator::shouldDelegate()},
 * otherwise stores on the host disk. On boi-api, set {@see config('boi_files.accept_target_bucket')} and allow-listed buckets
 * to target alternate S3 buckets via trusted header/query.
 *
 * Registered by {@see BoiBackendServiceProvider} unless {@see config('boi_backend.register_file_routes')} is false.
 */
final class FileController extends Controller
{
    public function upload(Request $request)
    {
        $context = $request->input('context');
        $maxKb = config("boi_files.upload.contexts.{$context}", config('boi_files.upload.max_size_kb', 10240));

        $request->validate([
            'file' => ['required', 'file', "max:{$maxKb}"],
            'folder' => 'nullable|string',
            'context' => 'nullable|string',
        ]);

        $folder = $request->input('folder', config('boi_files.default_folder', 'documents'));
        $file = $request->file('file');

        $delegated = BoiFileApiDelegator::forwardUpload($request, $file, $folder, $context);
        if ($delegated !== null) {
            return $delegated;
        }

        $storage = $this->resolveFilesystem($request);
        $path = FileService::storeToFilesystem($file, $folder, $storage);

        if (! is_string($path) || $path === '') {
            return response()->json(['success' => false, 'message' => 'Upload failed: empty storage path'], 500);
        }

        /** @var FilesystemAdapter $storage */
        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => $this->resolveFileUrl($storage, $path),
        ]);
    }

    public function view(Request $request)
    {
        $path = trim((string) $request->validate([
            'path' => ['required', 'string', 'min:1', 'max:4096'],
        ])['path']);

        if ($path === '') {
            abort(422, 'Path is required');
        }

        $delegated = BoiFileApiDelegator::forwardView($request, $path);
        if ($delegated !== null) {
            return $delegated;
        }

        $storage = $this->resolveFilesystem($request);
        /** @var FilesystemAdapter $storage */
        if (! $storage->exists($path)) {
            abort(404, 'File not found');
        }

        return redirect($this->resolveFileUrl($storage, $path));
    }

    private function resolveFilesystem(Request $request): Filesystem
    {
        if (config('boi_files.accept_target_bucket')) {
            $bucket = $request->header(BoiFileHeaders::TARGET_BUCKET) ?: $request->query('bucket');
            $bucket = is_string($bucket) ? trim($bucket) : '';

            return DynamicS3Filesystem::diskForBucket($bucket !== '' ? $bucket : null);
        }

        return Storage::disk((string) config('boi_files.disk', 's3'));
    }

    private function resolveFileUrl(FilesystemAdapter $storage, string $path): string
    {
        if (config('boi_files.accept_target_bucket')) {
            return $storage->temporaryUrl($path, now()->addMinutes(5));
        }

        $diskName = (string) config('boi_files.disk', 's3');
        $driver = config("filesystems.disks.{$diskName}.driver");

        if ($driver === 's3' && method_exists($storage, 'temporaryUrl')) {
            return $storage->temporaryUrl($path, now()->addMinutes(5));
        }

        return $storage->url($path);
    }
}
