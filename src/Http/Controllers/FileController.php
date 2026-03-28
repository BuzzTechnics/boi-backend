<?php

namespace Boi\Backend\Http\Controllers;

use Boi\Backend\Services\BoiFileApiDelegator;
use Boi\Backend\Services\FileService;
use Boi\Backend\Support\BoiFileHeaders;
use Boi\Backend\Support\BoiFilesTrace;
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

        BoiFilesTrace::log('upload.enter', [
            'folder' => $folder,
            'context' => $context,
            'user_id' => $request->user()?->getAuthIdentifier(),
            'will_try_delegate' => BoiFileApiDelegator::shouldDelegate(),
            'accept_target_bucket' => (bool) config('boi_files.accept_target_bucket'),
        ]);

        $delegated = BoiFileApiDelegator::forwardUpload($request, $file, $folder, $context);
        if ($delegated !== null) {
            return $delegated;
        }

        BoiFilesTrace::log('upload.local_store', ['folder' => $folder]);

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
            'bucket' => $this->bucketNameFromFilesystem($storage),
        ]);
    }

    private function bucketNameFromFilesystem(FilesystemAdapter $storage): ?string
    {
        try {
            $config = $storage->getConfig();
            if (is_array($config)) {
                $bucket = $config['bucket'] ?? null;
                if (is_string($bucket) && $bucket !== '') {
                    return $bucket;
                }
            }
        } catch (\Throwable) {
        }

        $diskName = (string) config('boi_files.disk', 's3');
        $fallback = config("filesystems.disks.{$diskName}.bucket");

        return is_string($fallback) && $fallback !== '' ? $fallback : null;
    }

    public function view(Request $request)
    {
        $path = trim((string) $request->validate([
            'path' => ['required', 'string', 'min:1', 'max:4096'],
        ])['path']);

        if ($path === '') {
            abort(422, 'Path is required');
        }

        BoiFilesTrace::log('view.enter', [
            'path_prefix' => strlen($path) > 80 ? substr($path, 0, 80).'…' : $path,
            'will_try_delegate' => BoiFileApiDelegator::shouldDelegate(),
        ]);

        $delegated = BoiFileApiDelegator::forwardView($request, $path);
        if ($delegated !== null) {
            return $delegated;
        }

        $storage = $this->resolveFilesystem($request);
        /** @var FilesystemAdapter $storage */
        $exists = $storage->exists($path);
        BoiFilesTrace::log('view.local', [
            'path_prefix' => strlen($path) > 80 ? substr($path, 0, 80).'…' : $path,
            'exists' => $exists,
        ]);

        if (! $exists) {
            abort(404, 'File not found');
        }

        $url = $this->resolveFileUrl($storage, $path);
        $locationHost = parse_url($url, PHP_URL_HOST);
        BoiFilesTrace::log('view.redirect', [
            'location_host' => is_string($locationHost) && $locationHost !== '' ? $locationHost : 'unknown',
        ]);

        return redirect($url);
    }

    private function resolveFilesystem(Request $request): Filesystem
    {
        if (config('boi_files.accept_target_bucket')) {
            $bucket = $request->header(BoiFileHeaders::TARGET_BUCKET) ?: $request->query('bucket');
            $bucket = is_string($bucket) ? trim($bucket) : '';

            BoiFilesTrace::log('resolve_filesystem', [
                'header_bucket' => $request->header(BoiFileHeaders::TARGET_BUCKET),
                'query_bucket' => $request->query('bucket'),
                'resolved_input' => $bucket !== '' ? $bucket : null,
                'boi_files_disk' => config('boi_files.disk'),
            ]);

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
