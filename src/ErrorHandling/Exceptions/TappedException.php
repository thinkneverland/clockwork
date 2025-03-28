<?php

namespace ThinkNeverland\Tapped\ErrorHandling\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception class for all Tapped-specific exceptions
 */
class TappedException extends Exception
{
    /**
     * Unique error code for tracking
     * 
     * @var string
     */
    protected $errorCode;
    
    /**
     * User-friendly error message
     * 
     * @var string
     */
    protected $userMessage;
    
    /**
     * Additional context for the error
     * 
     * @var array
     */
    protected $context;
    
    /**
     * Whether this exception is recoverable by the user
     * 
     * @var bool
     */
    protected $recoverable;
    
    /**
     * Create a new Tapped exception
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
        // Set default options
        $options = array_merge([
            'code' => 0,
            'error_code' => 'TPD-ERR-' . substr(md5($message), 0, 8),
            'user_message' => 'An unexpected error occurred. Please try again later.',
            'context' => [],
            'recoverable' => true
        ], $options);
        
        // Call parent constructor
        parent::__construct($message, $options['code'], $previous);
        
        // Set additional properties
        $this->errorCode = $options['error_code'];
        $this->userMessage = $options['user_message'];
        $this->context = $options['context'];
        $this->recoverable = $options['recoverable'];
    }
    
    /**
     * Get the unique error code
     * 
     * @return string
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
    
    /**
     * Get the user-friendly message
     * 
     * @return string
     */
    public function getUserMessage(): string
    {
        return $this->userMessage;
    }
    
    /**
     * Get additional context
     * 
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }
    
    /**
     * Check if this exception is recoverable
     * 
     * @return bool
     */
    public function isRecoverable(): bool
    {
        return $this->recoverable;
    }
    
    /**
     * Add additional context to the exception
     * 
     * @param array $context
     * @return self
     */
    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }
    
    /**
     * Set the exception as recoverable or not
     * 
     * @param bool $recoverable
     * @return self
     */
    public function setRecoverable(bool $recoverable): self
    {
        $this->recoverable = $recoverable;
        return $this;
    }
    
    /**
     * Convert the exception to an array
     * 
     * @param bool $includeTrace Whether to include stack trace
     * @return array
     */
    public function toArray(bool $includeTrace = false): array
    {
        $data = [
            'message' => $this->getMessage(),
            'user_message' => $this->getUserMessage(),
            'error_code' => $this->getErrorCode(),
            'recoverable' => $this->isRecoverable(),
            'context' => $this->getContext(),
            'file' => $this->getFile(),
            'line' => $this->getLine()
        ];
        
        if ($includeTrace) {
            $data['trace'] = $this->getTraceAsString();
        }
        
        return $data;
    }
}
