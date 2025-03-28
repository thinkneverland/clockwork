<?php

namespace ThinkNeverland\Tapped\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;
use ThinkNeverland\Tapped\TappedServiceProvider;

class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [
            TappedServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Set up Tapped configuration
        $app['config']->set('tapped.enabled', true);
        $app['config']->set('tapped.websocket.enabled', false);
        $app['config']->set('tapped.api.auth.enabled', false);
    }
}
