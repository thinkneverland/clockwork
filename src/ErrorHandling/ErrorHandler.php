<?php

namespace ThinkNeverland\Tapped\ErrorHandling;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Standardized error handling for Tapped PHP components
 */
class ErrorHandler
{
    // Error severity levels
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    // Error categories
    const CATEGORY_DATABASE = 'database';
    const CATEGORY_NETWORK = 'network';
    const CATEGORY_SECURITY = 'security';
    const CATEGORY_WEBSOCKET = 'websocket';
    const CATEGORY_LIVEWIRE = 'livewire';
    const CATEGORY_INTERNAL = 'internal';
    const CATEGORY_UNKNOWN = 'unknown';

    /**
     * In-memory error history for debugging (limited size)
     * @var array
     */
    protected static $errorHistory = [];

    /**
     * Maximum number of errors to keep in history
     * @var int
     */
    protected static $maxErrorHistory = 50;

    /**
     * Report an exception with full context
     *
     * @param Throwable $exception The exception to report
     * @param string $level Error level
     * @param string $category Error category
     * @param array $context Additional context
     * @return array Error record
     */
    public static function reportException(
        Throwable $exception, 
        string $level = self::LEVEL_ERROR, 
        string $category = self::CATEGORY_UNKNOWN, 
        array $context = []
    ): array {
        // Format the error
        $errorRecord = self::formatError($exception, $level, $category, $context);
        
        // Add to history
        self::addToHistory($errorRecord);
        
        // Log to appropriate channel
        self::logError($errorRecord);
        
        return $errorRecord;
    }

    /**
     * Report a message as an error
     *
     * @param string $message Error message
     * @param string $level Error level
     * @param string $category Error category
     * @param array $context Additional context
     * @return array Error record
     */
    public static function reportMessage(
        string $message, 
        string $level = self::LEVEL_INFO, 
        string $category = self::CATEGORY_UNKNOWN, 
        array $context = []
    ): array {
        // Create a simple exception to maintain stack trace
        $exception = new \Exception($message);
        
        return self::reportException($exception, $level, $category, $context);
    }

    /**
     * Handle an exception and return a user-friendly response
     *
     * @param Throwable $exception The exception to handle
     * @param string|null $userMessage User-friendly message to show
     * @param string $category Error category
     * @param array $context Additional context
     * @return array Response with user message and status
     */
    public static function handleException(
        Throwable $exception, 
        ?string $userMessage = null, 
        string $category = self::CATEGORY_UNKNOWN, 
        array $context = []
    ): array {
        // Report the exception
        $errorRecord = self::reportException($exception, self::LEVEL_ERROR, $category, $context);
        
        // Determine user-friendly message
        $message = $userMessage ?? self::getUserFriendlyMessage($exception, $category);
        
        // Return standardized response
        return [
            'success' => false,
            'message' => $message,
            'error_code' => $errorRecord['error_code'] ?? 'unknown_error',
        ];
    }

    /**
     * Format an error for consistent logging
     *
     * @param Throwable $exception The exception
     * @param string $level Error level
     * @param string $category Error category
     * @param array $context Additional context
     * @return array Formatted error record
     */
    protected static function formatError(
        Throwable $exception, 
        string $level, 
        string $category, 
        array $context
    ): array {
        $timestamp = date('Y-m-d H:i:s');
        $errorCode = self::generateErrorCode($exception, $category);
        
        return [
            'message' => $exception->getMessage(),
            'level' => $level,
            'category' => $category,
            'timestamp' => $timestamp,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'error_code' => $errorCode,
            'context' => array_merge($context, [
                'request_id' => request()->header('X-Request-ID') ?? uniqid('req-'),
                'url' => request()->fullUrl(),
                'user_id' => auth()->id() ?? 'anonymous',
            ]),
        ];
    }

    /**
     * Generate a unique error code for tracking
     *
     * @param Throwable $exception The exception
     * @param string $category Error category
     * @return string Unique error code
     */
    protected static function generateErrorCode(Throwable $exception, string $category): string
    {
        $prefix = strtoupper(substr($category, 0, 3));
        $errorHash = substr(md5($exception->getMessage() . $exception->getFile() . $exception->getLine()), 0, 8);
        
        return "TPD-{$prefix}-{$errorHash}";
    }

    /**
     * Add error to in-memory history
     *
     * @param array $errorRecord Error record
     */
    protected static function addToHistory(array $errorRecord): void
    {
        // Add to history
        self::$errorHistory[] = $errorRecord;
        
        // Trim if exceeds max size
        if (count(self::$errorHistory) > self::$maxErrorHistory) {
            array_shift(self::$errorHistory);
        }
    }

    /**
     * Log error to appropriate channel based on level
     *
     * @param array $errorRecord Error record
     */
    protected static function logError(array $errorRecord): void
    {
        $message = "[{$errorRecord['error_code']}] [{$errorRecord['category']}] {$errorRecord['message']}";
        $context = [
            'file' => $errorRecord['file'],
            'line' => $errorRecord['line'],
            'context' => $errorRecord['context'],
        ];
        
        switch ($errorRecord['level']) {
            case self::LEVEL_DEBUG:
                Log::debug($message, $context);
                break;
            case self::LEVEL_INFO:
                Log::info($message, $context);
                break;
            case self::LEVEL_WARNING:
                Log::warning($message, $context);
                break;
            case self::LEVEL_ERROR:
                Log::error($message, $context);
                break;
            case self::LEVEL_CRITICAL:
                Log::critical($message, $context);
                break;
            default:
                Log::error($message, $context);
                break;
        }
    }

    /**
     * Get a user-friendly message for an exception
     *
     * @param Throwable $exception The exception
     * @param string $category Error category
     * @return string User-friendly message
     */
    protected static function getUserFriendlyMessage(Throwable $exception, string $category): string
    {
        // Default messages by category
        $defaultMessages = [
            self::CATEGORY_DATABASE => 'A database error occurred. Please try again later.',
            self::CATEGORY_NETWORK => 'A network error occurred. Please check your connection and try again.',
            self::CATEGORY_SECURITY => 'A security issue was detected. Please contact support if this persists.',
            self::CATEGORY_WEBSOCKET => 'The realtime connection was interrupted. Please refresh the page.',
            self::CATEGORY_LIVEWIRE => 'There was an issue with the Livewire component. Please refresh the page.',
            self::CATEGORY_INTERNAL => 'An internal server error occurred. Please try again later.',
            self::CATEGORY_UNKNOWN => 'An unexpected error occurred. Please try again later.'
        ];
        
        return $defaultMessages[$category] ?? 'An error occurred. Please try again later.';
    }

    /**
     * Get the current error history
     *
     * @param int|null $limit Maximum number of errors to return
     * @return array Error history
     */
    public static function getErrorHistory(?int $limit = null): array
    {
        $history = self::$errorHistory;
        
        if (!is_null($limit) && $limit > 0) {
            $history = array_slice($history, -$limit);
        }
        
        return $history;
    }

    /**
     * Clear the error history
     */
    public static function clearErrorHistory(): void
    {
        self::$errorHistory = [];
    }

    /**
     * Execute a callback with error handling
     *
     * @param callable $callback Function to execute
     * @param string $category Error category
     * @param callable|null $errorHandler Custom error handler
     * @return mixed Result of the callback
     * @throws Throwable If error handling fails or no error handler is provided
     */
    public static function withErrorHandling(
        callable $callback, 
        string $category = self::CATEGORY_UNKNOWN,
        ?callable $errorHandler = null
    ) {
        try {
            return $callback();
        } catch (Throwable $exception) {
            // Report the exception
            self::reportException($exception, self::LEVEL_ERROR, $category);
            
            // Use custom error handler if provided
            if ($errorHandler) {
                return $errorHandler($exception);
            }
            
            // Otherwise re-throw
            throw $exception;
        }
    }
}
