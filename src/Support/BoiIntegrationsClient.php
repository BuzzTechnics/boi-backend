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

        return Http::baseUrl($base)
            ->timeout((int) config('boi_proxy.timeout', 120))
            ->withToken($key)
            ->acceptJson();
    }
}
