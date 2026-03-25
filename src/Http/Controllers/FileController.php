<?php

namespace Boi\Backend\Http\Controllers;

use Boi\Backend\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * S3 file upload and presigned redirect — uses the host application’s {@see config('filesystems.disks.s3')}.
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

        $disk = (string) config('boi_files.disk', 's3');
        $path = FileService::storeFile($request->file('file'), $folder, $disk);

        if (! is_string($path) || $path === '') {
            return response()->json(['success' => false, 'message' => 'Upload failed: empty storage path'], 500);
        }

        $storage = Storage::disk($disk);
        $url = method_exists($storage, 'temporaryUrl')
            ? $storage->temporaryUrl($path, now()->addMinutes(5))
            : $storage->url($path);

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => $url,
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

        $storage = Storage::disk((string) config('boi_files.disk', 's3'));
        if (! $storage->exists($path)) {
            abort(404, 'File not found');
        }

        $url = method_exists($storage, 'temporaryUrl')
            ? $storage->temporaryUrl($path, now()->addMinutes(5))
            : $storage->url($path);

        return redirect($url);
    }
}
