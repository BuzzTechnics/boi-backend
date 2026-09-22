<?php

namespace Boi\Backend;

use Boi\Backend\Console\Commands\FixPostgresSequences;
use Boi\Backend\Console\Commands\SlaCheck;
use Boi\Backend\Sla\BusinessCalendar;
use Boi\Backend\Sla\Listeners\LogSlaNotification;
use Boi\Backend\Sla\Support\SlaDefinitions;
use Boi\Backend\Http\Middleware\TrustedSources;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
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
        $this->mergeConfigFrom(__DIR__.'/../config/boi_integrations.php', 'boi_integrations');
        $this->mergeConfigFrom(__DIR__.'/../config/boi_sla.php', 'boi_sla');
        $this->mergeConfigFrom(__DIR__.'/../config/boi_document_library.php', 'boi_document_library');

        // Definitions are read repeatedly while the engine walks its trackers, and
        // they cannot change mid-run.
        $this->app->singleton(SlaDefinitions::class);
        $this->app->singleton(BusinessCalendar::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                FixPostgresSequences::class,
                SlaCheck::class,
            ]);
        }

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

        $this->publishes([
            __DIR__.'/../config/boi_integrations.php' => config_path('boi_integrations.php'),
        ], 'boi-backend-integrations');

        $this->publishes([
            __DIR__.'/../config/boi_sla.php' => config_path('boi_sla.php'),
        ], 'boi-backend-sla');

        $this->publishes([
            __DIR__.'/../config/boi_document_library.php' => config_path('boi_document_library.php'),
        ], 'boi-backend-document-library');

        // BRD §5.4-01. Registered here rather than left to a portal, so no fund can
        // run the engine without its trail; the listener is a no-op for every other
        // notification.
        Event::listen(NotificationSent::class, LogSlaNotification::class);
        Event::listen(NotificationFailed::class, LogSlaNotification::class);

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

        // Inbound document-library webhooks (workflow → portal). Auth is the shared
        // secret inside the request, so these ride the bare 'api' middleware.
        if (config('boi_document_library.register_webhook_routes', true)) {
            Route::middleware('api')
                ->prefix((string) config('boi_document_library.webhook_prefix', 'api/webhooks/document-library'))
                ->controller(\Boi\Backend\DocumentLibrary\Http\Controllers\DocumentWebhookController::class)
                ->name('document-library.webhook.')
                ->group(function (): void {
                    Route::post('request', 'requestDocuments')->name('request');
                    Route::post('review', 'reviewDocuments')->name('review');
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
