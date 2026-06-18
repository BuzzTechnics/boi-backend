<?php

namespace Boi\Backend\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Server-to-server client to boi-api integration routes (BOI / Rubikon). When URL+key are unset, returns null and callers use package services locally.
 */
final class BoiIntegrationsClient
{
    public static function http(): ?PendingRequest
    {
        $base = rtrim((string) config('boi_proxy.url'), '/');
        $key = (string) config('boi_proxy.key');
        if ($base === '' || $key === '') {
            return null;
        }

        $headers = [];
        // Forward the originating app (and user) so boi-api attributes recorded
        // calls (eDoc, BVN/NIN) to e.g. 'glow' rather than the boi-api host.
        $app = (string) config('boi_proxy.app', 'app');
        if ($app !== '') {
            $headers[(string) config('boi_proxy.app_header', 'X-Boi-App')] = $app;
        }
        $userHeader = (string) config('boi_proxy.user_header', 'X-Boi-User');
        $userId = function_exists('auth') ? auth()->id() : null;
        if ($userId !== null) {
            $headers[$userHeader] = (string) $userId;
        }

        return Http::baseUrl($base)
            ->timeout((int) config('boi_proxy.timeout', 120))
            ->withToken($key)
            ->withHeaders($headers)
            ->acceptJson();
    }
}
