<?php

namespace ThinkNeverland\Tapped\ErrorHandling\Exceptions;

use Throwable;

/**
 * Exception for network-related errors
 */
class NetworkException extends TappedException
{
    /**
     * Create a new network exception
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
        // Set default options specific to network exceptions
        $options = array_merge([
            'error_code' => 'TPD-NET-' . substr(md5($message), 0, 8),
            'user_message' => 'A network error occurred. Please check your connection and try again.',
        ], $options);
        
        parent::__construct($message, $options, $previous);
    }
}
