<?php

namespace ThinkNeverland\Tapped\ErrorHandling\Exceptions;

use Throwable;

/**
 * Exception for WebSocket-specific errors
 */
class WebSocketException extends NetworkException
{
    /**
     * WebSocket connection code
     * 
     * @var int|null
     */
    protected $code;
    
    /**
     * WebSocket connection state when the error occurred
     * 
     * @var mixed
     */
    protected $connectionState;
    
    /**
     * Create a new WebSocket exception
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
        // Set default options specific to WebSocket exceptions
        $options = array_merge([
            'error_code' => 'TPD-WS-' . substr(md5($message), 0, 8),
            'user_message' => 'The realtime connection was interrupted. Please refresh the page.',
            'connection_state' => null
        ], $options);
        
        parent::__construct($message, $options, $previous);
        
        // Store WebSocket-specific data
        $this->connectionState = $options['connection_state'];
    }
    
    /**
     * Get the WebSocket connection state when the exception occurred
     * 
     * @return mixed
     */
    public function getConnectionState()
    {
        return $this->connectionState;
    }
}
