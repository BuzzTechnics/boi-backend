<?php

namespace Boi\Backend;

use Illuminate\Support\ServiceProvider;

/**
 * BOI backend package: boi-api HTTP proxy, shared contracts, Paystack bank sync.
 */
class BoiBackendServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/boi_proxy.php', 'boi_proxy');
        $this->mergeConfigFrom(__DIR__.'/../config/boi_api.php', 'boi_api');
        $this->mergeConfigFrom(__DIR__.'/../config/boi_edoc.php', 'boi_edoc');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/banks.php' => config_path('banks.php'),
        ], 'boi-backend-config');

        $this->publishes([
            __DIR__.'/../config/boi_proxy.php' => config_path('boi_proxy.php'),
        ], 'boi-backend-proxy');

        $this->publishes([
            __DIR__.'/../config/boi_api.php' => config_path('boi_api.php'),
        ], 'boi-backend-boi-api');

        $this->publishes([
            __DIR__.'/../config/boi_edoc.php' => config_path('boi_edoc.php'),
        ], 'boi-backend-edoc');

        $this->mergeConfigFrom(__DIR__.'/../config/banks.php', 'banks');
    }
}
