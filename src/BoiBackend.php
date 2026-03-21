<?php

namespace Boi\Backend;

use Boi\Backend\Http\Controllers\BoiApiProxyController;
use Illuminate\Support\Facades\Route;

/**
 * Register the HTTP proxy to boi-api inside your own route group (middleware, prefix, etc.).
 */
final class BoiBackend
{
    /**
     * Single catch-all route: forwards to BOI_API_URL with server key + X-Boi-User + X-Boi-App.
     *
     * @see config/boi_proxy.php
     */
    public static function proxyRoute(): void
    {
        $template = (string) config('boi_proxy.route_template', 'api/boi-api/{path}');

        Route::any($template, [BoiApiProxyController::class, 'proxy'])
            ->where('path', '.*')
            ->name((string) config('boi_proxy.route_name', 'api.boi-api.proxy'));
    }
}
