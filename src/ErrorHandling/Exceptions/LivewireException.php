<?php

namespace ThinkNeverland\Tapped\ErrorHandling\Exceptions;

use Throwable;

/**
 * Exception for Livewire-related errors
 */
class LivewireException extends TappedException
{
    /**
     * The component ID where the error occurred
     * 
     * @var string|null
     */
    protected $componentId;
    
    /**
     * The property name that caused the error (if applicable)
     * 
     * @var string|null
     */
    protected $property;
    
    /**
     * Create a new Livewire exception
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
        // Set default options specific to Livewire exceptions
        $options = array_merge([
            'error_code' => 'TPD-LW-' . substr(md5($message), 0, 8),
            'user_message' => 'There was a problem with a page component. Please refresh the page.',
        ], $options);
        
        parent::__construct($message, $options, $previous);
        
        // Store Livewire-specific data
        $this->componentId = $options['component_id'] ?? null;
        $this->property = $options['property'] ?? null;
    }
    
    /**
     * Get the component ID where the error occurred
     * 
     * @return string|null
     */
    public function getComponentId(): ?string
    {
        return $this->componentId;
    }
    
    /**
     * Get the property name that caused the error
     * 
     * @return string|null
     */
    public function getProperty(): ?string
    {
        return $this->property;
    }
}
