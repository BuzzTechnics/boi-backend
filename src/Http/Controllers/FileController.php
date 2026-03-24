<?php

namespace Boi\Backend\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * S3 file upload and presigned redirect for viewing.
 *
 * Routes are registered by {@see BoiBackendServiceProvider} unless disabled in config.
 */
final class FileController extends Controller
{
    public function upload(Request $request)
    {
        $maxSizeKb = $request->input('context') === 'bank_statement' ? 20480 : 10240;

        $request->validate([
            'file' => ['required', 'file', "max:{$maxSizeKb}"],
            'folder' => 'string|nullable',
            'context' => 'string|nullable',
        ]);

        $path = $request->file('file')->store(
            $request->input('folder', 'documents'),
            's3'
        );

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
        $path = $request->validate(['path' => 'required|string'])['path'];

        try {
            $s3 = Storage::disk('s3');
            if (! $s3->exists($path)) {
                abort(404, 'File not found');
            }

            $url = method_exists($s3, 'temporaryUrl')
                ? $s3->temporaryUrl($path, now()->addMinutes(5))
                : $s3->url($path);

            return redirect($url);
        } catch (\Exception $e) {
            abort(404, 'File not found');
        }
    }
}
