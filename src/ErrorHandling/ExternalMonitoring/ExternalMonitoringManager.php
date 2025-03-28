<?php

namespace ThinkNeverland\Tapped\ErrorHandling\ExternalMonitoring;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Throwable;
use ThinkNeverland\Tapped\ErrorHandling\Exceptions\TappedException;

/**
 * Manages integration with external error monitoring services
 */
class ExternalMonitoringManager
{
    /**
     * @var array Available monitoring providers
     */
    protected $providers = [];
    
    /**
     * @var bool Whether external monitoring is enabled
     */
    protected $enabled;
    
    /**
     * @var string The active provider
     */
    protected $activeProvider;
    
    /**
     * @var array Error context data
     */
    protected $globalContext = [];
    
    /**
     * Initialize external monitoring manager
     */
    public function __construct()
    {
        $this->enabled = Config::get('error-handling.external_monitoring.enabled', false);
        $this->activeProvider = Config::get('error-handling.external_monitoring.provider', 'null');
        
        // Register built-in providers
        $this->registerProvider('sentry', SentryProvider::class);
        $this->registerProvider('null', NullProvider::class);
    }
    
    /**
     * Register a monitoring provider
     *
     * @param string $name Provider name
     * @param string $providerClass Provider class
     * @return self
     */
    public function registerProvider(string $name, string $providerClass): self
    {
        $this->providers[$name] = $providerClass;
        return $this;
    }
    
    /**
     * Set global context data for all error reports
     *
     * @param array $context Context data
     * @return self
     */
    public function setGlobalContext(array $context): self
    {
        $this->globalContext = array_merge($this->globalContext, $context);
        return $this;
    }
    
    /**
     * Get the active provider instance
     *
     * @return MonitoringProviderInterface
     */
    public function getProvider(): MonitoringProviderInterface
    {
        // If monitoring is disabled, use the null provider
        if (!$this->enabled || !array_key_exists($this->activeProvider, $this->providers)) {
            return new NullProvider();
        }
        
        // Get the provider class
        $providerClass = $this->providers[$this->activeProvider];
        
        // Create and initialize the provider
        $provider = new $providerClass();
        
        // Set any global context
        if (!empty($this->globalContext)) {
            $provider->setContext($this->globalContext);
        }
        
        return $provider;
    }
    
    /**
     * Report an exception to the external monitoring service
     *
     * @param Throwable $exception The exception to report
     * @param array $context Additional context data
     * @return bool Whether reporting was successful
     */
    public function reportException(Throwable $exception, array $context = []): bool
    {
        // Skip if monitoring is disabled
        if (!$this->enabled) {
            return false;
        }
        
        try {
            // Get the provider
            $provider = $this->getProvider();
            
            // Add app environment to context
            $context['environment'] = Config::get('app.env', 'production');
            
            // Map our exception to provider format
            return $provider->reportException($exception, $context);
        } catch (Throwable $e) {
            // Log but don't throw if the external service fails
            Log::warning('External error monitoring failed: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'provider' => $this->activeProvider
            ]);
            
            return false;
        }
    }
    
    /**
     * Report a message to the external monitoring service
     *
     * @param string $message The message to report
     * @param string $level Error level (debug, info, warning, error, critical)
     * @param array $context Additional context data
     * @return bool Whether reporting was successful
     */
    public function reportMessage(string $message, string $level = 'error', array $context = []): bool
    {
        // Skip if monitoring is disabled
        if (!$this->enabled) {
            return false;
        }
        
        try {
            // Get the provider
            $provider = $this->getProvider();
            
            // Add app environment to context
            $context['environment'] = Config::get('app.env', 'production');
            
            // Report the message
            return $provider->reportMessage($message, $level, $context);
        } catch (Throwable $e) {
            // Log but don't throw if the external service fails
            Log::warning('External error monitoring failed: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'provider' => $this->activeProvider
            ]);
            
            return false;
        }
    }
}
