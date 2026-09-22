<?php

namespace Boi\Backend;

use Boi\Backend\DocumentLibrary\Http\Controllers\DocumentLibraryController;
use Boi\Backend\Http\Controllers\BoiApiProxyController;
use Boi\Backend\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

/**
 * HTTP routes are registered by {@see BoiBackendServiceProvider}: proxy when
 * `config('boi_backend.register_routes')`, file routes when `config('boi_backend.register_file_routes')`.
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

    /**
     * Register the customer document-library endpoints relative to the current route
     * group. A fund mounts these behind its own auth, e.g.:
     *
     *   Route::middleware(['auth:sanctum', ...])->prefix('dashboard')->group(function () {
     *       BoiBackend::documentLibraryRoutes();
     *   });
     *
     * @param  array<int, string|\Closure|class-string>  $middleware
     */
    public static function documentLibraryRoutes(array $middleware = [], string $prefix = 'document-library'): void
    {
        Route::prefix($prefix)
            ->middleware($middleware)
            ->controller(DocumentLibraryController::class)
            ->name('document-library.')
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::post('{documentRequest}/documents/{document}/upload', 'upload')->name('upload');
                Route::post('{documentRequest}/submit', 'submit')->name('submit');
            });
    }
}
