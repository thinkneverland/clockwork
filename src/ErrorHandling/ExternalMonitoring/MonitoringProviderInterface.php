<?php

namespace ThinkNeverland\Tapped\ErrorHandling\ExternalMonitoring;

use Throwable;

/**
 * Interface for external error monitoring providers
 */
interface MonitoringProviderInterface
{
    /**
     * Initialize the monitoring provider with configuration
     *
     * @param array $config Configuration options
     * @return bool Whether initialization was successful
     */
    public function initialize(array $config = []): bool;
    
    /**
     * Set context data for all error reports
     *
     * @param array $context Context data
     * @return self
     */
    public function setContext(array $context): self;
    
    /**
     * Set user data for all error reports
     *
     * @param array $userData User data
     * @return self
     */
    public function setUser(array $userData): self;
    
    /**
     * Report an exception to the monitoring service
     *
     * @param Throwable $exception The exception to report
     * @param array $context Additional context data
     * @return bool Whether reporting was successful
     */
    public function reportException(Throwable $exception, array $context = []): bool;
    
    /**
     * Report a message to the monitoring service
     *
     * @param string $message The message to report
     * @param string $level Error level (debug, info, warning, error, critical)
     * @param array $context Additional context data
     * @return bool Whether reporting was successful
     */
    public function reportMessage(string $message, string $level = 'error', array $context = []): bool;
}
