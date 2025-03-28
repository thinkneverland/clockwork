<?php

namespace ThinkNeverland\Tapped\ErrorHandling\Exceptions;

use Throwable;

/**
 * Exception for timeout errors
 */
class TimeoutException extends NetworkException
{
    /**
     * The timeout duration in seconds
     * 
     * @var float|null
     */
    protected $timeoutSeconds;
    
    /**
     * The operation that timed out
     * 
     * @var string|null
     */
    protected $operation;
    
    /**
     * Create a new timeout exception
     * 
     * @param string $message Developer-oriented error message
     * @param array $options Additional options for the exception
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        array $options = [],
        ?Throwable $previous = null
    ) {
        // Set default options specific to timeout exceptions
        $options = array_merge([
            'error_code' => 'TPD-TIMEOUT-' . substr(md5($message), 0, 8),
            'user_message' => 'The operation timed out. Please try again later.',
        ], $options);
        
        parent::__construct($message, $options, $previous);
        
        // Store timeout-specific data
        $this->timeoutSeconds = $options['timeout_seconds'] ?? null;
        $this->operation = $options['operation'] ?? null;
    }
    
    /**
     * Get the timeout duration in seconds
     * 
     * @return float|null
     */
    public function getTimeoutSeconds(): ?float
    {
        return $this->timeoutSeconds;
    }
    
    /**
     * Get the operation that timed out
     * 
     * @return string|null
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }
}
