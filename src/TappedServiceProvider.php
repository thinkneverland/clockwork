<?php

namespace ThinkNeverland\Tapped;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use ThinkNeverland\Tapped\Console\Commands\LaunchMcpServer;
use ThinkNeverland\Tapped\Services\LivewireStateManager;
use ThinkNeverland\Tapped\Services\EventLogger;

class TappedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/tapped.php',
            'tapped'
        );

        // Register the base service
        $this->app->singleton('tapped', function ($app) {
            return new Tapped($app);
        });

        // Register core services
        $this->app->singleton(LivewireStateManager::class);
        $this->app->singleton(EventLogger::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/tapped.php' => config_path('tapped.php'),
            ], 'tapped-config');

            $this->commands([
                LaunchMcpServer::class,
            ]);
        }

        if (!Config::get('tapped.enabled')) {
            return;
        }

        // Register middleware
        $this->app['router']->aliasMiddleware('tapped', \ThinkNeverland\Tapped\Middleware\TappedMiddleware::class);

        // Register routes for AI integration
        if (Config::get('tapped.ai_integration.enabled')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/ai.php');
        }
    }
}
