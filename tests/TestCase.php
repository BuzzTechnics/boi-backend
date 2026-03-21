<?php

namespace Boi\Backend\Tests;

use Boi\Backend\BoiBackendServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('edoc.route_middleware', []);
        $app['config']->set('edoc.url', 'https://edoc.example.com');
        $app['config']->set('edoc.client_id', 'test-client');
        $app['config']->set('edoc.client_secret', str_repeat('x', 32));
        $app['config']->set('files.route_middleware', []);
    }

    protected function getPackageProviders($app): array
    {
        return [BoiBackendServiceProvider::class];
    }
}
