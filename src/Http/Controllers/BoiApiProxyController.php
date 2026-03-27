<?php

namespace Boi\Backend\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

final class BoiApiProxyController
{
    public function proxy(Request $request, string $path): Response
    {
        $base = rtrim((string) config('boi_proxy.url', ''), '/');
        $apiKey = (string) config('boi_proxy.key', '');

        if ($base === '' || $apiKey === '') {
            abort(503, 'BOI_API_URL and BOI_API_KEY must be set (config boi_proxy).');
        }

        $prefix = (string) config('boi_proxy.path_prefix', 'api/');
        if ($prefix !== '' && ! str_starts_with($path, $prefix)) {
            abort(404);
        }

        if (str_starts_with($path, 'api/banks/validate')) {
            abort(404, 'Use the host application validate endpoint (e.g. POST /api/validate).');
        }

        if (str_starts_with($path, 'api/integrations/')) {
            abort(404, 'Integration endpoints are server-to-server only.');
        }

        $this->assertUserOwnsLoanApplicationInPath($request, $path);

        $url = $base.'/'.$path;
        if ($query = $request->getQueryString()) {
            $url .= '?'.$query;
        }

        $timeout = (int) config('boi_proxy.timeout', 120);
        $userH = (string) config('boi_proxy.user_header', 'X-Boi-User');
        $appH = (string) config('boi_proxy.app_header', 'X-Boi-App');

        $user = $request->user();

        // Locations are non-sensitive lookup tables; allow unauthenticated requests so public
        // applications (signed URLs) can still render state/LGA selectors.
        $isLocationLookup = str_starts_with($path, 'api/states')
            || str_starts_with($path, 'api/lgas')
            || str_starts_with($path, 'api/all-lgas')
            || str_starts_with($path, 'api/cities')
            || str_starts_with($path, 'api/all-cities')
            // Banks list only (not POST /api/banks/validate — blocked above).
            || rtrim($path, '/') === 'api/banks';

        if ($user === null) {
            if (! $isLocationLookup) {
                abort(401, 'Unauthenticated.');
            }

            $headers = [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
                $appH => (string) config('boi_proxy.app', 'app'),
            ];
        } else {
            $headers = [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
                $userH => (string) $user->getAuthIdentifier(),
                $appH => (string) config('boi_proxy.app', 'app'),
            ];
        }

        if (count($request->allFiles()) > 0) {
            $pending = Http::timeout($timeout)
                ->withToken($apiKey)
                ->withHeaders($headers)
                ->asMultipart();
            foreach ($request->allFiles() as $key => $file) {
                if (is_array($file)) {
                    foreach ($file as $f) {
                        $pending = $pending->attach(
                            $key.'[]',
                            file_get_contents($f->getRealPath()),
                            $f->getClientOriginalName()
                        );
                    }
                } else {
                    $pending = $pending->attach(
                        $key,
                        file_get_contents($file->getRealPath()),
                        $file->getClientOriginalName()
                    );
                }
            }
            $response = $pending->post($url, $request->except(array_keys($request->allFiles())));
        } else {
            $method = strtoupper($request->method());
            $client = Http::timeout($timeout)->withToken($apiKey)->withHeaders($headers);

            if ($method === 'GET') {
                $response = $client->get($url);
            } elseif ($method === 'DELETE') {
                $response = $client->delete($url);
            } elseif ($request->isJson()) {
                $body = $request->getContent() !== '' ? $request->getContent() : '{}';
                $jsonClient = $client->withHeaders(['Content-Type' => 'application/json'])
                    ->withBody($body, 'application/json');
                $response = match ($method) {
                    'POST' => $jsonClient->post($url),
                    'PUT' => $jsonClient->put($url),
                    'PATCH' => $jsonClient->patch($url),
                    default => $jsonClient->send($method, $url),
                };
            } else {
                $data = $request->request->all();
                $response = match ($method) {
                    'POST' => $client->asForm()->post($url, $data),
                    'PUT' => $client->asForm()->put($url, $data),
                    'PATCH' => $client->asForm()->patch($url, $data),
                    default => $client->send($method, $url),
                };
            }
        }

        return response($response->body(), $response->status())
            ->withHeaders(array_filter([
                'Content-Type' => $response->header('Content-Type'),
            ]));
    }

    /**
     * Paths under api/loan-applications/{id}/… must belong to the authenticated user.
     */
    private function assertUserOwnsLoanApplicationInPath(Request $request, string $path): void
    {
        if (! preg_match('#^api/loan-applications/(\d+)#', $path, $m)) {
            return;
        }

        if (! class_exists(\App\Models\Application::class)) {
            return;
        }

        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $applicationId = (int) $m[1];
        $owns = \App\Models\Application::query()
            ->whereKey($applicationId)
            ->where('user_id', $user->getAuthIdentifier())
            ->exists();

        if (! $owns) {
            abort(403);
        }
    }
}
