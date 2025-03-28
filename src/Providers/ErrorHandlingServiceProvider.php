<?php

namespace ThinkNeverland\Tapped\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use ThinkNeverland\Tapped\ErrorHandling\Middleware\ErrorHandlingMiddleware;

/**
 * Service provider for registering error handling components
 */
class ErrorHandlingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Register error views
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'tapped');
        
        // Merge error handling configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../config/error-handling.php', 'tapped.error-handling'
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Register global middleware
        $kernel = $this->app->make(Kernel::class);
        $kernel->pushMiddleware(ErrorHandlingMiddleware::class);

        // Publish views
        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/tapped'),
        ], 'tapped-views');
        
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/error-handling.php' => config_path('tapped/error-handling.php'),
        ], 'tapped-config');
    }
}
