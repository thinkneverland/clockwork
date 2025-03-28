<?php

namespace ThinkNeverland\Tapped\ErrorHandling\Exceptions;

use Throwable;

/**
 * Exception for database-related errors
 */
class DatabaseException extends TappedException
{
    /**
     * The SQL query that caused the error (if applicable)
     * 
     * @var string|null
     */
    protected $query;
    
    /**
     * Create a new database exception
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
        // Set default options specific to database exceptions
        $options = array_merge([
            'error_code' => 'TPD-DB-' . substr(md5($message), 0, 8),
            'user_message' => 'A database error occurred. Please try again later.',
        ], $options);
        
        parent::__construct($message, $options, $previous);
        
        // Store SQL query if provided (sanitized)
        $this->query = isset($options['query']) ? $this->sanitizeQuery($options['query']) : null;
    }
    
    /**
     * Get the SQL query that caused the error
     * 
     * @return string|null
     */
    public function getQuery(): ?string
    {
        return $this->query;
    }
    
    /**
     * Sanitize the SQL query to remove sensitive information
     * 
     * @param string $query The SQL query
     * @return string Sanitized query
     */
    protected function sanitizeQuery(string $query): string
    {
        // Simple sanitization - remove potential passwords
        $sanitized = preg_replace(
            '/password\s*=\s*[\'"][^\'"]*[\'"]|password\s*=\s*[^\s,)]*|passwd\s*=\s*[\'"][^\'"]*[\'"]|passwd\s*=\s*[^\s,)]*/i',
            'password=***REDACTED***',
            $query
        );
        
        return $sanitized;
    }
}
