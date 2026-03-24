<?php

namespace Boi\Backend;

use Boi\Backend\Http\Controllers\BoiApiProxyController;
use Boi\Backend\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

/**
 * HTTP routes are registered automatically by {@see BoiBackendServiceProvider} unless
 * `config('boi_backend.register_routes')` is false. Use these helpers only for custom wiring.
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

    /**
     * Register `files/upload` and `files/view` relative to the current route group (use under `prefix('api')` for `/api/files/...`).
     *
     * @param  array<int, string|\Closure|class-string>  $middleware  e.g. `[config('jetstream.auth_session'), TrustedSources::class]`
     */
    public static function fileRoutes(array $middleware = []): void
    {
        Route::prefix('files')
            ->middleware($middleware)
            ->controller(FileController::class)
            ->group(function (): void {
                Route::post('upload', 'upload')->name('api.files.upload');
                Route::get('view', 'view')->name('api.files.view');
            });
    }
}
