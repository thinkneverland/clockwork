<?php

namespace ThinkNeverland\Tapped\ErrorHandling\ExternalMonitoring;

use Throwable;

/**
 * Null implementation of error monitoring provider (does nothing)
 * Used when external monitoring is disabled
 */
class NullProvider implements MonitoringProviderInterface
{
    /**
     * Initialize the monitoring provider with configuration
     *
     * @param array $config Configuration options
     * @return bool Whether initialization was successful
     */
    public function initialize(array $config = []): bool
    {
        // Always successful since it does nothing
        return true;
    }
    
    /**
     * Set context data for all error reports
     *
     * @param array $context Context data
     * @return self
     */
    public function setContext(array $context): self
    {
        // Do nothing
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
        // Do nothing
        return $this;
    }
    
    /**
     * Report an exception to the monitoring service
     *
     * @param Throwable $exception The exception to report
     * @param array $context Additional context data
     * @return bool Whether reporting was successful
     */
    public function reportException(Throwable $exception, array $context = []): bool
    {
        // Do nothing, always successful
        return true;
    }
    
    /**
     * Report a message to the monitoring service
     *
     * @param string $message The message to report
     * @param string $level Error level (debug, info, warning, error, critical)
     * @param array $context Additional context data
     * @return bool Whether reporting was successful
     */
    public function reportMessage(string $message, string $level = 'error', array $context = []): bool
    {
        // Do nothing, always successful
        return true;
    }
}
