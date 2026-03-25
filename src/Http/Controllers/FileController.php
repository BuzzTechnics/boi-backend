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

        $path = FileService::storeFile($request->file('file'), $folder, 's3');

        if (! is_string($path) || $path === '') {
            return response()->json(['success' => false, 'message' => 'Upload failed: empty storage path'], 500);
        }

        $s3 = Storage::disk('s3');
        $url = method_exists($s3, 'temporaryUrl')
            ? $s3->temporaryUrl($path, now()->addMinutes(5))
            : $s3->url($path);

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

        $s3 = Storage::disk('s3');
        if (! $s3->exists($path)) {
            abort(404, 'File not found');
        }

        $url = method_exists($s3, 'temporaryUrl')
            ? $s3->temporaryUrl($path, now()->addMinutes(5))
            : $s3->url($path);

        return redirect($url);
    }
}
