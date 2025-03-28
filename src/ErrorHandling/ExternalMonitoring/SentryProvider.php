<?php

namespace ThinkNeverland\Tapped\ErrorHandling\ExternalMonitoring;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Sentry\State\Scope;
use Sentry\SentrySdk;
use Sentry\Severity;

/**
 * Sentry integration for external error monitoring
 */
class SentryProvider implements MonitoringProviderInterface
{
    /**
     * @var array Context data
     */
    protected $context = [];
    
    /**
     * @var array User data
     */
    protected $userData = [];
    
    /**
     * @var bool Whether Sentry is properly initialized
     */
    protected $initialized = false;
    
    /**
     * Initialize Sentry with configuration
     *
     * @param array $config Configuration options
     * @return bool Whether initialization was successful
     */
    public function initialize(array $config = []): bool
    {
        // Skip if already initialized
        if ($this->initialized) {
            return true;
        }
        
        try {
            // Get DSN from config or environment
            $dsn = $config['dsn'] ?? Config::get('error-handling.external_monitoring.sentry.dsn');
            
            if (empty($dsn)) {
                Log::warning('Sentry DSN not configured');
                return false;
            }
            
            // Initialize Sentry with the DSN
            \Sentry\init([
                'dsn' => $dsn,
                'environment' => Config::get('app.env', 'production'),
                'release' => Config::get('error-handling.external_monitoring.sentry.release', '1.0.0'),
                'traces_sample_rate' => Config::get('error-handling.external_monitoring.sentry.traces_sample_rate', 0.1),
                'max_breadcrumbs' => Config::get('error-handling.external_monitoring.sentry.max_breadcrumbs', 50),
            ]);
            
            $this->initialized = true;
            return true;
        } catch (Throwable $e) {
            Log::error('Failed to initialize Sentry: ' . $e->getMessage(), [
                'exception' => get_class($e)
            ]);
            
            return false;
        }
    }
    
    /**
     * Set context data for all error reports
     *
     * @param array $context Context data
     * @return self
     */
    public function setContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        
        // If initialized, apply context to current scope
        if ($this->initialized) {
            \Sentry\configureScope(function (Scope $scope) use ($context) {
                foreach ($context as $key => $value) {
                    $scope->setContext($key, $value);
                }
            });
        }
        
        return $this;
    }
    
    /**
     * Set user data for all error reports
     *
     * @param array $userData User data
     * @return self
     */
    public function setUser(array $userData): self
    {
        $this->userData = $userData;
        
        // If initialized, apply user data to current scope
        if ($this->initialized) {
            \Sentry\configureScope(function (Scope $scope) {
                $scope->setUser($this->userData);
            });
        }
        
        return $this;
    }
    
    /**
     * Report an exception to Sentry
     *
     * @param Throwable $exception The exception to report
     * @param array $context Additional context data
     * @return bool Whether reporting was successful
     */
    public function reportException(Throwable $exception, array $context = []): bool
    {
        // Initialize if not already
        if (!$this->initialized && !$this->initialize()) {
            return false;
        }
        
        try {
            // Capture the exception with context
            \Sentry\withScope(function (Scope $scope) use ($exception, $context) {
                // Add global context
                foreach ($this->context as $key => $value) {
                    $scope->setContext($key, $value);
                }
                
                // Add user data if any
                if (!empty($this->userData)) {
                    $scope->setUser($this->userData);
                }
                
                // Add exception-specific context
                foreach ($context as $key => $value) {
                    if (is_string($key) && !empty($key)) {
                        if (is_array($value)) {
                            $scope->setContext($key, $value);
                        } else {
                            $scope->setTag($key, (string) $value);
                        }
                    }
                }
                
                // Set error category if available
                if (isset($context['category'])) {
                    $scope->setTag('category', $context['category']);
                }
                
                // Capture the exception
                \Sentry\captureException($exception);
            });
            
            return true;
        } catch (Throwable $e) {
            Log::error('Failed to report exception to Sentry: ' . $e->getMessage(), [
                'exception' => get_class($e)
            ]);
            
            return false;
        }
    }
    
    /**
     * Report a message to Sentry
     *
     * @param string $message The message to report
     * @param string $level Error level (debug, info, warning, error, critical)
     * @param array $context Additional context data
     * @return bool Whether reporting was successful
     */
    public function reportMessage(string $message, string $level = 'error', array $context = []): bool
    {
        // Initialize if not already
        if (!$this->initialized && !$this->initialize()) {
            return false;
        }
        
        try {
            // Map our error levels to Sentry levels
            $sentryLevel = $this->mapErrorLevel($level);
            
            // Capture the message with context
            \Sentry\withScope(function (Scope $scope) use ($message, $sentryLevel, $context) {
                // Add global context
                foreach ($this->context as $key => $value) {
                    $scope->setContext($key, $value);
                }
                
                // Add user data if any
                if (!empty($this->userData)) {
                    $scope->setUser($this->userData);
                }
                
                // Set the error level
                $scope->setLevel($sentryLevel);
                
                // Add message-specific context
                foreach ($context as $key => $value) {
                    if (is_string($key) && !empty($key)) {
                        if (is_array($value)) {
                            $scope->setContext($key, $value);
                        } else {
                            $scope->setTag($key, (string) $value);
                        }
                    }
                }
                
                // Set error category if available
                if (isset($context['category'])) {
                    $scope->setTag('category', $context['category']);
                }
                
                // Capture the message
                \Sentry\captureMessage($message);
            });
            
            return true;
        } catch (Throwable $e) {
            Log::error('Failed to report message to Sentry: ' . $e->getMessage(), [
                'exception' => get_class($e)
            ]);
            
            return false;
        }
    }
    
    /**
     * Map our error levels to Sentry levels
     *
     * @param string $level Our error level
     * @return \Sentry\Severity Sentry severity level
     */
    protected function mapErrorLevel(string $level): \Sentry\Severity
    {
        $mapping = [
            'debug' => \Sentry\Severity::debug(),
            'info' => \Sentry\Severity::info(),
            'notice' => \Sentry\Severity::info(),
            'warning' => \Sentry\Severity::warning(),
            'error' => \Sentry\Severity::error(),
            'critical' => \Sentry\Severity::fatal(),
            'alert' => \Sentry\Severity::fatal(),
            'emergency' => \Sentry\Severity::fatal(),
        ];
        
        return $mapping[strtolower($level)] ?? \Sentry\Severity::error();
    }
}
