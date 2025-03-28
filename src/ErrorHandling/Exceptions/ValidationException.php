<?php

namespace ThinkNeverland\Tapped\ErrorHandling\Exceptions;

use Throwable;

/**
 * This is a placeholder interface that mimics Laravel's Validator interface
 * If the project uses Laravel, replace this with the actual import:
 * use Illuminate\Contracts\Validation\Validator;
 */
interface ValidatorInterface {
    /**
     * Get the validation errors
     *
     * @return object
     */
    public function errors(): object;
    
    /**
     * Get the failed validation rules
     *
     * @return array
     */
    public function failed(): array;
}

/**
 * Exception for validation errors
 */
class ValidationException extends TappedException
{
    /**
     * The validation errors
     * 
     * @var array
     */
    protected $errors;
    
    /**
     * Create a new validation exception
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
        // Set default options specific to validation exceptions
        $options = array_merge([
            'error_code' => 'TPD-VAL-' . substr(md5($message), 0, 8),
            'user_message' => 'Please check your input and try again.',
            'recoverable' => true, // Validation errors are always recoverable
        ], $options);
        
        parent::__construct($message, $options, $previous);
        
        // Store validation errors
        $this->errors = $options['errors'] ?? [];
    }
    
    /**
     * Create a validation exception from a validator instance
     * 
     * @param ValidatorInterface $validator The validator instance
     * @param string|null $message Custom message (optional)
     * @param array $options Additional options
     * @return self
     */
    public static function fromValidator(
        ValidatorInterface $validator,
        ?string $message = null,
        array $options = []
    ): self {
        $errors = (array)$validator->errors();
        $defaultMessage = 'The submitted data did not pass validation.';
        
        return new static(
            $message ?? $defaultMessage,
            array_merge($options, [
                'errors' => $errors,
                'context' => ['failed_rules' => $validator->failed()]
            ])
        );
    }
    
    /**
     * Get the validation errors
     * 
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Check if a specific field has validation errors
     * 
     * @param string $field Field name
     * @return bool
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }
    
    /**
     * Get validation errors for a specific field
     * 
     * @param string $field Field name
     * @return array
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }
}
