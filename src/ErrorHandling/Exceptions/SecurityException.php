<?php

namespace ThinkNeverland\Tapped\ErrorHandling\Exceptions;

use Throwable;

/**
 * Exception for security-related errors
 */
class SecurityException extends TappedException
{
    /**
     * Create a new security exception
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
        // Set default options specific to security exceptions
        $options = array_merge([
            'error_code' => 'TPD-SEC-' . substr(md5($message), 0, 8),
            'user_message' => 'A security issue was detected. Please contact support if this persists.',
            'recoverable' => false, // Security exceptions are typically not recoverable by the user
        ], $options);
        
        parent::__construct($message, $options, $previous);
    }
}
