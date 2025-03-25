<?php

namespace ThinkNeverland\Tapped;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use ThinkNeverland\Tapped\Console\Commands\LaunchMcpServer;
use ThinkNeverland\Tapped\Console\Commands\InstallJsDependencies;
use ThinkNeverland\Tapped\Console\Commands\TappedBootstrap;
use ThinkNeverland\Tapped\Services\LivewireStateManager;
use ThinkNeverland\Tapped\Services\EventLogger;
use Illuminate\Contracts\Http\Kernel;

class TappedServiceProvider extends ServiceProvider
{
    /**
     * JS dependencies from the extension's package.json
     */
    protected $jsDependencies = [
        '@sqltools/formatter' => '^1.2.2',
        '@vueuse/components' => '^12.4.0',
        '@vueuse/core' => '^12.4.0',
        'ansi-to-html' => '^0.7.2',
        'date-fns' => '^4.1.0',
        'feather-icons' => '^4.28.0',
        'just-clone' => '^6.2.0',
        'just-debounce-it' => '^3.2.0',
        'just-extend' => '^6.2.0',
        'just-intersect' => '^4.3.0',
        'just-is-empty' => '^3.4.1',
        'just-map-values' => '^3.2.0',
        'just-omit' => '^2.2.0',
        'just-pick' => '^4.2.0',
        'just-unique' => '^4.2.0',
        'linkify-html' => '^4.1.3',
        'lodash' => '^4.17.19',
        'prismjs' => '^1.24.1',
        'punycode' => '^2.3.1',
        'register-service-worker' => '^1.7.1',
        'sass' => '^1.55.0',
        'urijs' => '^1.19.2',
        'vue' => '^3.2.0',
        'vue-clipboard2' => '^0.3.1'
    ];

    /**
     * JS dev dependencies from the extension's package.json
     */
    protected $jsDevDependencies = [
        '@vitejs/plugin-vue' => '^5.1.4',
        'cpx2' => '^8.0.0',
        'del-cli' => '^6.0.0',
        'move-cli' => '^2.0.0',
        'vite' => '^5.4.8',
        'vite-plugin-vue-devtools' => '^7.7.0',
        'vite-svg-loader' => '^5.1.0',
        'jest' => '^29.7.0',
        '@babel/preset-env' => '^7.24.0',
        'babel-jest' => '^29.7.0',
        // Additional dev tools helpful for debugging
        '@vue/devtools' => '^6.5.1',
        'browser-sync' => '^3.0.2',
        'chokidar' => '^3.5.3',
        'concurrently' => '^8.2.2',
        'nodemon' => '^3.0.3'
    ];

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        // Always force enable Tapped
        Config::set('tapped.enabled', true);

        $this->mergeConfigFrom(
            __DIR__ . '/../config/tapped.php',
            'tapped'
        );

        // Register the base service
        $this->app->singleton('tapped', function ($app) {
            return new Tapped($app);
        });

        // Register core services with debugging enabled
        $this->app->singleton(LivewireStateManager::class);
        $this->app->singleton(EventLogger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register development environment commands and publishers
        if ($this->app->runningInConsole()) {
            // Publish development configuration
            $this->publishes([
                __DIR__ . '/../config/tapped.php' => config_path('tapped.php'),
            ], 'tapped-config');

            // Publish assets for development
            $this->publishDevAssets();

            // Register development commands
            $this->commands([
                LaunchMcpServer::class,
                InstallJsDependencies::class,
                TappedBootstrap::class,
            ]);
        }

        // Register middleware
        $this->app['router']->aliasMiddleware('tapped', \ThinkNeverland\Tapped\Middleware\TappedMiddleware::class);

        // Automatically add the middleware in development
        $kernel = $this->app->make(Kernel::class);
        $kernel->pushMiddleware(\ThinkNeverland\Tapped\Middleware\TappedMiddleware::class);

        // Register routes for AI integration
        if (Config::get('tapped.ai_integration.enabled')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/ai.php');
        }

        // Load routes for development UI
        $this->loadRoutesFrom(__DIR__ . '/../routes/assets.php');
    }

    /**
     * Publish development assets
     */
    protected function publishDevAssets(): void
    {
        // Only publish assets in development
        $this->publishes([
            __DIR__ . '/../extension/build' => public_path('vendor/tapped'),
        ], 'tapped-assets');

        // Development package.json with all dependencies
        $packageJson = json_encode([
            'name' => 'tapped-dev-dependencies',
            'private' => true,
            'description' => 'Development dependencies for Tapped',
            'dependencies' => $this->jsDependencies,
            'devDependencies' => $this->jsDevDependencies,
            'scripts' => [
                'dev' => 'concurrently "vite" "nodemon --watch src"',
                'watch' => 'vite build --watch',
                'build' => 'vite build',
                'serve' => 'browser-sync start --server "public" --files "public/**/*"',
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Create directory if it doesn't exist
        $vendorDir = base_path('vendor/tapped');
        if (!is_dir($vendorDir)) {
            mkdir($vendorDir, 0755, true);
        }

        // Write dependencies to file
        file_put_contents(
            $vendorDir . '/package.json',
            $packageJson
        );

        // Publish the source files for development and customization
        $this->publishes([
            __DIR__ . '/../extension/src' => resource_path('vendor/tapped/src'),
        ], 'tapped-source');
    }

    /**
     * Get JS dependencies for direct use in application
     * 
     * @return array
     */
    public function getJsDependencies(): array
    {
        return $this->jsDependencies;
    }

    /**
     * Get JS dev dependencies for direct use in application
     * 
     * @return array
     */
    public function getJsDevDependencies(): array
    {
        return $this->jsDevDependencies;
    }

    /**
     * Get all dependencies combined
     * 
     * @return array
     */
    public function getAllDependencies(): array
    {
        return [
            'dependencies' => $this->jsDependencies,
            'devDependencies' => $this->jsDevDependencies
        ];
    }

    /**
     * Check if CDN mode is enabled
     */
    public function isCdnModeEnabled(): bool
    {
        $cdnConfigPath = storage_path('app/tapped/cdn-config.json');

        if (file_exists($cdnConfigPath)) {
            $config = json_decode(file_get_contents($cdnConfigPath), true);
            return isset($config['enabled']) && $config['enabled'] === true;
        }

        return false;
    }

    /**
     * Get CDN libraries configuration
     */
    public function getCdnLibraries(): array
    {
        $cdnConfigPath = storage_path('app/tapped/cdn-config.json');

        if (file_exists($cdnConfigPath)) {
            $config = json_decode(file_get_contents($cdnConfigPath), true);
            return $config['libraries'] ?? [];
        }

        return [];
    }
}
