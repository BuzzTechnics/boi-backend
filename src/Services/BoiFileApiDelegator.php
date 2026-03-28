<?php

namespace Boi\Backend\Services;

use Boi\Backend\Support\BoiFileHeaders;
use Boi\Backend\Support\BoiIntegrationsClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forwards multipart upload / view from a consuming app to boi-api when {@see config('boi_files.delegate_to_boi_api')}
 * is enabled and {@see BoiIntegrationsClient::http()} is configured.
 */
final class BoiFileApiDelegator
{
    public static function shouldDelegate(): bool
    {
        if (BoiIntegrationsClient::http() === null) {
            return false;
        }

        $raw = config('boi_files.delegate_to_boi_api');
        if ($raw === false) {
            return false;
        }
        if ($raw === null) {
            return true;
        }

        return (bool) filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    public static function targetBucketForDelegation(): ?string
    {
        $explicit = trim((string) config('boi_files.target_bucket', ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $diskName = (string) config('boi_files.disk', 's3');
        $bucket = config("filesystems.disks.{$diskName}.bucket");

        return is_string($bucket) && $bucket !== '' ? $bucket : null;
    }

    public static function forwardUpload(Request $request, UploadedFile $file, string $folder, ?string $context): ?JsonResponse
    {
        if (! self::shouldDelegate()) {
            return null;
        }

        $user = $request->user();
        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        $http = BoiIntegrationsClient::http();
        if ($http === null) {
            return null;
        }

        $userHeader = (string) config('boi_proxy.user_header', 'X-Boi-User');
        $appHeader = (string) config('boi_proxy.app_header', 'X-Boi-App');
        $timeout = (int) config('boi_files.delegate_timeout', config('boi_proxy.timeout', 300));

        $headers = array_filter([
            $userHeader => (string) $user->getAuthIdentifier(),
            $appHeader => (string) config('boi_proxy.app', 'app'),
            BoiFileHeaders::TARGET_BUCKET => self::targetBucketForDelegation(),
        ], fn ($v) => $v !== null && $v !== '');

        $body = array_filter(
            [
                'folder' => $folder,
                'context' => $context,
            ],
            fn ($v) => $v !== null && $v !== ''
        );

        $response = $http->timeout($timeout)
            ->withHeaders($headers)
            ->attach(
                'file',
                file_get_contents($file->getRealPath()) ?: '',
                $file->getClientOriginalName()
            )
            ->post('/api/files/upload', $body);

        if ($response->failed()) {
            return response()->json(
                $response->json() ?? ['message' => 'Upstream upload failed'],
                $response->status() ?: 502
            );
        }

        return response()->json($response->json(), $response->status());
    }

    public static function forwardView(Request $request, string $path): ?Response
    {
        if (! self::shouldDelegate()) {
            return null;
        }

        $user = $request->user();
        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        $http = BoiIntegrationsClient::http();
        if ($http === null) {
            return null;
        }

        $userHeader = (string) config('boi_proxy.user_header', 'X-Boi-User');
        $appHeader = (string) config('boi_proxy.app_header', 'X-Boi-App');
        $timeout = (int) config('boi_files.delegate_timeout', config('boi_proxy.timeout', 120));

        $headers = array_filter([
            $userHeader => (string) $user->getAuthIdentifier(),
            $appHeader => (string) config('boi_proxy.app', 'app'),
            BoiFileHeaders::TARGET_BUCKET => self::targetBucketForDelegation(),
        ], fn ($v) => $v !== null && $v !== '');

        $query = ['path' => $path];
        if ($b = self::targetBucketForDelegation()) {
            $query['bucket'] = $b;
        }

        $response = $http->timeout($timeout)
            ->withHeaders($headers)
            ->withoutRedirecting()
            ->get('/api/files/view', $query);

        if ($response->status() === 401) {
            abort(401, 'Unauthenticated.');
        }

        if ($response->status() === 404) {
            abort(404, 'File not found');
        }

        if ($response->status() === 422) {
            abort(422, $response->json('message') ?? 'Invalid request');
        }

        if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
            $location = $response->header('Location');
            if (is_string($location) && $location !== '') {
                return redirect()->away($location);
            }
        }

        if (! $response->successful()) {
            abort($response->status() ?: 502, 'Upstream view failed');
        }

        return null;
    }
}
