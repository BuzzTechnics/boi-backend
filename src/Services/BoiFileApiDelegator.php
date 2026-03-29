<?php

namespace Boi\Backend\Services;

use Boi\Backend\Support\BoiFileHeaders;
use Boi\Backend\Support\BoiFileQueryParams;
use Boi\Backend\Support\BoiFilesTrace;
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

    /**
     * Optional bucket forwarded to boi-api (DNS-compliant name; IAM must allow access).
     * Only {@see config('boi_files.target_bucket')} — not the app’s local {@see config('filesystems.disks.s3.bucket')},
     * so delegated uploads default to boi-api’s own bucket unless you explicitly target another.
     */
    public static function targetBucketForDelegation(): ?string
    {
        $explicit = trim((string) config('boi_files.target_bucket', ''));

        return $explicit !== '' ? $explicit : null;
    }

    public static function forwardUpload(Request $request, UploadedFile $file, string $folder, ?string $context): ?JsonResponse
    {
        if (! self::shouldDelegate()) {
            BoiFilesTrace::log('delegate.upload.skip', ['reason' => 'shouldDelegate_false_or_no_http']);

            return null;
        }

        $user = $request->user();
        if ($user === null) {
            BoiFilesTrace::log('delegate.upload.abort', ['reason' => 'no_user']);

            abort(401, 'Unauthenticated.');
        }

        $http = BoiIntegrationsClient::http();
        if ($http === null) {
            return null;
        }

        $userHeader = (string) config('boi_proxy.user_header', 'X-Boi-User');
        $appHeader = (string) config('boi_proxy.app_header', 'X-Boi-App');
        $timeout = (int) config('boi_files.delegate_timeout', config('boi_proxy.timeout', 300));
        $targetBucket = self::targetBucketForDelegation();
        $baseUrl = rtrim((string) config('boi_proxy.url', ''), '/');
        $upstreamHost = parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl;

        $headers = array_filter([
            $userHeader => (string) $user->getAuthIdentifier(),
            $appHeader => (string) config('boi_proxy.app', 'app'),
            BoiFileHeaders::TARGET_BUCKET => $targetBucket,
        ], fn ($v) => $v !== null && $v !== '');

        $body = array_filter(
            [
                'folder' => $folder,
                'context' => $context,
            ],
            fn ($v) => $v !== null && $v !== ''
        );

        BoiFilesTrace::log('delegate.upload.request', [
            'upstream_host' => $upstreamHost,
            'timeout' => $timeout,
            'header_keys' => array_keys($headers),
            'x_boi_user' => (string) $user->getAuthIdentifier(),
            'x_boi_app' => (string) config('boi_proxy.app', 'app'),
            'x_boi_files_bucket' => $targetBucket,
            'folder' => $folder,
            'context' => $context,
            'file_bytes' => $file->getSize(),
            'file_name' => $file->getClientOriginalName(),
        ]);

        $response = $http->timeout($timeout)
            ->withHeaders($headers)
            ->attach(
                'file',
                file_get_contents($file->getRealPath()) ?: '',
                $file->getClientOriginalName()
            )
            ->post('/api/files/upload', $body);

        $failed = $response->failed();
        BoiFilesTrace::log('delegate.upload.response', [
            'status' => $response->status(),
            'failed' => $failed,
            'message' => $failed ? ($response->json('message') ?? substr($response->body(), 0, 200)) : null,
        ]);

        if ($failed) {
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
            BoiFilesTrace::log('delegate.view.skip', ['reason' => 'shouldDelegate_false_or_no_http']);

            return null;
        }

        $user = $request->user();
        if ($user === null) {
            BoiFilesTrace::log('delegate.view.abort', ['reason' => 'no_user']);

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
            $query[BoiFileQueryParams::TID] = $b;
        }

        $baseUrl = rtrim((string) config('boi_proxy.url', ''), '/');
        $upstreamHost = parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl;

        BoiFilesTrace::log('delegate.view.request', [
            'upstream_host' => $upstreamHost,
            'query_keys' => array_keys($query),
            'tid_query' => $query[BoiFileQueryParams::TID] ?? null,
        ]);

        $response = $http->timeout($timeout)
            ->withHeaders($headers)
            ->withoutRedirecting()
            ->get('/api/files/view', $query);

        $locationHeader = $response->header('Location');
        $locationHost = is_string($locationHeader) && $locationHeader !== ''
            ? (parse_url($locationHeader, PHP_URL_HOST) ?: '')
            : '';

        BoiFilesTrace::log('delegate.view.response', [
            'status' => $response->status(),
            'location_set' => $locationHeader !== null && $locationHeader !== '',
            'location_host' => $locationHost !== '' ? $locationHost : null,
        ]);

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
                BoiFilesTrace::log('delegate.view.return_redirect', [
                    'location_host' => parse_url($location, PHP_URL_HOST) ?: '',
                ]);

                return redirect()->away($location);
            }
        }

        if (! $response->successful()) {
            abort($response->status() ?: 502, 'Upstream view failed');
        }

        return null;
    }
}
