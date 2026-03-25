<?php

namespace Boi\Backend;

use Boi\Backend\Http\Middleware\TrustedSources;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * BOI backend package: boi-api HTTP proxy, shared contracts, Paystack bank sync.
 */
class BoiBackendServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/boi_backend.php', 'boi_backend');
        $this->mergeConfigFrom(__DIR__.'/../config/boi_proxy.php', 'boi_proxy');
        $this->mergeConfigFrom(__DIR__.'/../config/boi_api.php', 'boi_api');
        $this->mergeConfigFrom(__DIR__.'/../config/boi_edoc.php', 'boi_edoc');
        $this->mergeConfigFrom(__DIR__.'/../config/boi_files.php', 'boi_files');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/banks.php' => config_path('banks.php'),
        ], 'boi-backend-config');

        $this->publishes([
            __DIR__.'/../config/boi_backend.php' => config_path('boi_backend.php'),
        ], 'boi-backend');

        $this->publishes([
            __DIR__.'/../config/boi_proxy.php' => config_path('boi_proxy.php'),
        ], 'boi-backend-proxy');

        $this->publishes([
            __DIR__.'/../config/boi_api.php' => config_path('boi_api.php'),
        ], 'boi-backend-boi-api');

        $this->publishes([
            __DIR__.'/../config/boi_edoc.php' => config_path('boi_edoc.php'),
        ], 'boi-backend-edoc');

        $this->publishes([
            __DIR__.'/../config/boi_files.php' => config_path('boi_files.php'),
        ], 'boi-backend-files');

        $this->mergeConfigFrom(__DIR__.'/../config/banks.php', 'banks');

        $this->app->booted(function (): void {
            $this->registerPackageRoutes();
        });
    }

    /**
     * Registers package routes: boi-api proxy when {@see config('boi_backend.register_routes')},
     * file upload/view when {@see config('boi_backend.register_file_routes')} (host app S3).
     */
    private function registerPackageRoutes(): void
    {
        if (config('boi_backend.register_routes', true)) {
            Route::middleware($this->proxyMiddleware())
                ->group(function (): void {
                    BoiBackend::proxyRoute();
                });
        }

        if (config('boi_backend.register_file_routes', true)) {
            Route::middleware($this->apiMiddleware())
                ->prefix('api')
                ->group(function (): void {
                    BoiBackend::fileRoutes([]);
                });
        }
    }

    /**
     * @return array<int, string|\Closure>
     */
    private function proxyMiddleware(): array
    {
        $middleware = [
            'web',
            'auth:sanctum',
        ];

        if (class_exists('Laravel\\Jetstream\\Http\\Middleware\\AuthenticateSession')) {
            $middleware[] = 'Laravel\\Jetstream\\Http\\Middleware\\AuthenticateSession';
        }

        if (class_exists('Laravel\\Jetstream\\Jetstream')) {
            $middleware[] = 'verified';
        }

        return array_merge($middleware, (array) config('boi_backend.extra_proxy_middleware', []));
    }

    /**
     * @return array<int, string|\Closure>
     */
    private function apiMiddleware(): array
    {
        $middleware = ['api'];

        if (class_exists('Laravel\\Jetstream\\Http\\Middleware\\AuthenticateSession')) {
            $middleware[] = 'Laravel\\Jetstream\\Http\\Middleware\\AuthenticateSession';
        }

        $middleware[] = TrustedSources::class;

        return array_merge($middleware, (array) config('boi_backend.extra_api_middleware', []));
    }
}
