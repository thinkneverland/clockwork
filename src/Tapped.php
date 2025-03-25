<?php

namespace ThinkNeverland\Tapped;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use ThinkNeverland\Tapped\Services\LivewireStateManager;
use ThinkNeverland\Tapped\Services\EventLogger;

class Tapped
{
    /**
     * The application instance
     */
    protected Application $app;

    /**
     * The Livewire state manager
     */
    protected LivewireStateManager $stateManager;

    /**
     * The event logger
     */
    protected EventLogger $eventLogger;

    /**
     * Debug mode flag
     */
    protected bool $debugMode = false;

    /**
     * Create a new Tapped instance
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->stateManager = $app->make(LivewireStateManager::class);
        $this->eventLogger = $app->make(EventLogger::class);

        // Auto-enable debug mode in local development
        if ($app->environment('local')) {
            $this->enableDebugMode();
        }
    }

    /**
     * Get the state manager
     */
    public function getStateManager(): LivewireStateManager
    {
        return $this->stateManager;
    }

    /**
     * Get the event logger
     */
    public function getEventLogger(): EventLogger
    {
        return $this->eventLogger;
    }

    /**
     * Check if Tapped is enabled
     */
    public function isEnabled(): bool
    {
        return (bool) config('tapped.enabled');
    }

    /**
     * Check if extensive logging is enabled
     */
    public function extensiveLoggingEnabled(): bool
    {
        return (bool) config('tapped.extensive_logging');
    }

    /**
     * Enable debug mode for more detailed output
     */
    public function enableDebugMode(): self
    {
        $this->debugMode = true;
        return $this;
    }

    /**
     * Disable debug mode
     */
    public function disableDebugMode(): self
    {
        $this->debugMode = false;
        return $this;
    }

    /**
     * Check if debug mode is enabled
     */
    public function isDebugModeEnabled(): bool
    {
        return $this->debugMode;
    }

    /**
     * Get all JavaScript dependencies required by Tapped
     *
     * @param bool $includeDevDependencies
     * @return array
     */
    public function getJsDependencies(bool $includeDevDependencies = true): array
    {
        try {
            /** @var TappedServiceProvider $provider */
            $provider = $this->app->make(TappedServiceProvider::class);

            $dependencies = $provider->getJsDependencies();

            if ($includeDevDependencies) {
                return [
                    'dependencies' => $dependencies,
                    'devDependencies' => $provider->getJsDevDependencies()
                ];
            }

            return $dependencies;
        } catch (\Exception $e) {
            $this->logDebugMessage('Error getting dependencies: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get development environment information
     * 
     * @return array
     */
    public function getDevEnvironmentInfo(): array
    {
        return [
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => $this->app->version(),
            'environmentType' => $this->app->environment(),
            'isDebug' => (bool) config('app.debug'),
            'isTappedDebugMode' => $this->debugMode,
            'extensiveLoggingEnabled' => $this->extensiveLoggingEnabled(),
            'nodeVersion' => $this->getNodeVersion(),
            'availableExtensions' => get_loaded_extensions(),
            'serverInfo' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'operatingSystem' => PHP_OS,
        ];
    }

    /**
     * Get Node.js version if available
     * 
     * @return string|null
     */
    protected function getNodeVersion(): ?string
    {
        try {
            $output = shell_exec('node -v 2>/dev/null');
            return $output ? trim($output) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate a package.json content with Tapped dependencies
     *
     * @param bool $includeDevDependencies
     * @return string
     */
    public function generatePackageJson(bool $includeDevDependencies = true): string
    {
        $dependencies = $this->getJsDependencies($includeDevDependencies);

        $packageJson = [
            'name' => 'tapped-dev-dependencies',
            'private' => true,
            'description' => 'Development dependencies for Tapped',
            'version' => '1.0.0-dev',
            'dependencies' => $dependencies['dependencies'] ?? $dependencies,
        ];

        if ($includeDevDependencies && isset($dependencies['devDependencies'])) {
            $packageJson['devDependencies'] = $dependencies['devDependencies'];

            // Add helpful development scripts
            $packageJson['scripts'] = [
                'tapped:dev' => 'concurrently "vite" "nodemon --watch src"',
                'tapped:watch' => 'vite build --watch',
                'tapped:build' => 'vite build',
                'tapped:serve' => 'browser-sync start --server "public" --files "public/**/*"',
            ];
        }

        return json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Log debug message if debug mode is enabled
     * 
     * @param string $message
     * @param array $context
     */
    protected function logDebugMessage(string $message, array $context = []): void
    {
        if ($this->debugMode) {
            Log::debug('[Tapped] ' . $message, $context);
        }
    }
}
