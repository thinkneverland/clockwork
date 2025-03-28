<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;

// Core components
use ThinkNeverland\Tapped\Support\Tapped as TappedSupport;
use ThinkNeverland\Tapped\Support\Serializer;
use ThinkNeverland\Tapped\Storage\FileStorage;
use ThinkNeverland\Tapped\Livewire\StateManager;
use ThinkNeverland\Tapped\Middleware\InjectTapped;
use ThinkNeverland\Tapped\Contracts\StorageManager;

// Console commands
use ThinkNeverland\Tapped\Console\StartMcpServerCommand;
use ThinkNeverland\Tapped\Console\Commands\McpServerCommand;

// Data collectors
use ThinkNeverland\Tapped\DataCollectors\RequestCollector;
use ThinkNeverland\Tapped\DataCollectors\DatabaseQueryCollector;
use ThinkNeverland\Tapped\DataCollectors\EventCollector;
use ThinkNeverland\Tapped\DataCollectors\LogCollector;
use ThinkNeverland\Tapped\DataCollectors\CacheCollector;
use ThinkNeverland\Tapped\DataCollectors\ModelCollector;
use ThinkNeverland\Tapped\DataCollectors\RedisCollector;
use ThinkNeverland\Tapped\DataCollectors\RouteCollector;
use ThinkNeverland\Tapped\DataCollectors\ViewCollector;
use ThinkNeverland\Tapped\DataCollectors\LivewireCollector;
use ThinkNeverland\Tapped\DataCollectors\LivewireEventCollector;
use ThinkNeverland\Tapped\DataCollectors\LivewireRequestCollector;

/**
 * Tapped service provider class.
 * 
 * This class is responsible for registering and booting Tapped services.
 * It handles dependency injection, middleware registration, and configuration.
 */
class TappedServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     * 
     * Order of registration is important:
     * 1. Core services (config, serializer)
     * 2. Storage-related services
     * 3. State management
     * 4. Data collectors
     */
    public function register(): void
    {
        // Register configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/tapped.php', 'tapped');
        
        // 1. Core utility services - register these first as they're dependencies for other services
        $this->registerCoreServices();
        
        // 2. Storage services - depends on core configuration
        $this->registerStorageServices();
        
        // 3. State management - depends on serializer
        $this->registerStateManagement();
        
        // 4. Data collectors - these are relatively independent
        $this->registerDataCollectors();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register package resources
        $this->registerResources();
        
        // Register console commands when running in console
        if ($this->app->runningInConsole()) {
            $this->registerPublishableAssets();
            $this->registerConsoleCommands();
        }
        
        // Register middleware if not running in console
        if (!$this->app->runningInConsole()) {
            $this->registerMiddleware();
        }
    }

    /**
     * Register core services that don't have dependencies.
     */
    private function registerCoreServices(): void
    {
        // Main Tapped service
        $this->app->singleton('tapped', function ($app) {
            return new TappedSupport($app);
        });
        
        // Serializer (used by multiple components)
        $this->app->singleton(Serializer::class, function () {
            return new Serializer();
        });
    }
    
    /**
     * Register storage-related services.
     */
    private function registerStorageServices(): void
    {
        // Storage manager
        $this->app->singleton(StorageManager::class, function () {
            $storagePath = \config('tapped.storage_path', \storage_path('tapped'));
            return new FileStorage($storagePath);
        });
    }
    
    /**
     * Register state management services.
     */
    private function registerStateManagement(): void
    {
        // Livewire state manager (depends on serializer)
        $this->app->singleton(StateManager::class, function ($app) {
            return new StateManager($app->make(Serializer::class));
        });
    }
    
    /**
     * Register data collectors.
     */
    private function registerDataCollectors(): void
    {
        // Group collectors by functionality
        $collectors = [
            // Request and routing collectors
            RequestCollector::class,
            RouteCollector::class,
            
            // Database and model collectors
            DatabaseQueryCollector::class,
            ModelCollector::class,
            
            // Event and log collectors
            EventCollector::class,
            LogCollector::class,
            
            // Cache and Redis collectors
            CacheCollector::class,
            RedisCollector::class,
            
            // View collectors
            ViewCollector::class,
            
            // Livewire collectors
            LivewireCollector::class,
            LivewireEventCollector::class,
            LivewireRequestCollector::class,
        ];

        foreach ($collectors as $collector) {
            $this->app->singleton($collector);
            $this->app->tag([$collector], 'tapped.collectors');
        }
    }
    
    /**
     * Register resources like views and routes.
     */
    private function registerResources(): void
    {
        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'tapped');
        
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }
    
    /**
     * Register middleware.
     */
    private function registerMiddleware(): void
    {
        $kernel = $this->app->make(Kernel::class);
        $kernel->pushMiddleware(InjectTapped::class);
    }
    
    /**
     * Register assets that can be published.
     */
    private function registerPublishableAssets(): void
    {
        // Configuration
        $this->publishes([
            __DIR__ . '/../config/tapped.php' => \config_path('tapped.php'),
        ], 'tapped-config');
        
        // Views
        $this->publishes([
            __DIR__ . '/../resources/views' => \resource_path('views/vendor/tapped'),
        ], 'tapped-views');
        
        // Public assets
        $this->publishes([
            __DIR__ . '/../resources/js' => \public_path('vendor/tapped/js'),
            __DIR__ . '/../resources/css' => \public_path('vendor/tapped/css'),
        ], 'tapped-assets');
    }
    
    /**
     * Register the console commands for the package.
     */
    private function registerConsoleCommands(): void
    {
        $this->commands([
            StartMcpServerCommand::class,
            McpServerCommand::class,
        ]);
    }
}
