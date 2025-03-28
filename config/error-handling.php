<?php

/**
 * Helper function to get environment variables with fallbacks
 * This is used in case the Laravel env() helper is not available
 *
 * @param string $key The environment variable key
 * @param mixed $default Default value if not found
 * @return mixed
 */
if (!function_exists('tapped_env')) {
    function tapped_env($key, $default = null) {
        // If Laravel's env() function exists, use it
        if (function_exists('env')) {
            return env($key, $default);
        }
        
        // Otherwise, use getenv() directly
        $value = getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Handle special values
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
        }
        
        return $value;
    }
}

/**
 * Get a nested configuration value from Laravel's config if available
 *
 * @param string $key The configuration key
 * @param mixed $default Default value if not found
 * @return mixed
 */
if (!function_exists('tapped_config')) {
    function tapped_config($key, $default = null) {
        // If Laravel's config() function exists, use it
        if (function_exists('config')) {
            return config($key, $default);
        }
        
        return $default;
    }
}

return [
    /*
    |--------------------------------------------------------------------------
    | Error Logging Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for the standardized error handling system.
    |
    */

    // Maximum error history to keep in memory
    'max_error_history' => tapped_env('TAPPED_MAX_ERROR_HISTORY', 50),

    // Whether to include detailed information in error responses
    // Set to false in production for security
    'debug_mode' => tapped_env('TAPPED_ERROR_DEBUG_MODE', tapped_config('app.debug', false)),
    
    // Default error logging channel
    'log_channel' => tapped_env('TAPPED_ERROR_LOG_CHANNEL', tapped_config('logging.default', 'stack')),
    
    // Error reporting for JavaScript clients
    'client_reporting' => [
        // Whether to accept error reports from JavaScript clients
        'enabled' => tapped_env('TAPPED_CLIENT_ERROR_REPORTING', true),
        
        // Rate limiting for client error reports (per minute)
        'rate_limit' => tapped_env('TAPPED_CLIENT_ERROR_RATE_LIMIT', 60),
        
        // Maximum number of errors per batch
        'max_batch_size' => tapped_env('TAPPED_CLIENT_ERROR_MAX_BATCH', 50),
    ],
    
    // Notification thresholds for errors
    'notifications' => [
        // Whether to send error notifications
        'enabled' => tapped_env('TAPPED_ERROR_NOTIFICATIONS', false),
        
        // Channels to use for notifications (mail, slack, etc.)
        'channels' => explode(',', tapped_env('TAPPED_ERROR_NOTIFICATION_CHANNELS', 'mail')),
        
        // Minimum error level to trigger notifications
        'min_level' => tapped_env('TAPPED_ERROR_NOTIFICATION_MIN_LEVEL', 'critical'),
        
        // Notification email address(es)
        'email' => tapped_env('TAPPED_ERROR_NOTIFICATION_EMAIL', tapped_config('mail.from.address', null)),
        
        // Slack webhook URL
        'slack_webhook' => tapped_env('TAPPED_ERROR_NOTIFICATION_SLACK_WEBHOOK'),
    ],
    
    // Categories of errors to track
    'categories' => [
        'database' => [
            'level' => tapped_env('TAPPED_ERROR_LEVEL_DATABASE', 'error'),
            'description' => 'Database and query errors',
        ],
        'network' => [
            'level' => tapped_env('TAPPED_ERROR_LEVEL_NETWORK', 'warning'),
            'description' => 'Network and API connection errors',
        ],
        'security' => [
            'level' => tapped_env('TAPPED_ERROR_LEVEL_SECURITY', 'critical'),
            'description' => 'Security-related errors',
        ],
        'websocket' => [
            'level' => tapped_env('TAPPED_ERROR_LEVEL_WEBSOCKET', 'warning'),
            'description' => 'WebSocket connection errors',
        ],
        'livewire' => [
            'level' => tapped_env('TAPPED_ERROR_LEVEL_LIVEWIRE', 'error'),
            'description' => 'Livewire component errors',
        ],
        'internal' => [
            'level' => tapped_env('TAPPED_ERROR_LEVEL_INTERNAL', 'error'),
            'description' => 'Internal application errors',
        ],
        'unknown' => [
            'level' => tapped_env('TAPPED_ERROR_LEVEL_UNKNOWN', 'error'),
            'description' => 'Unclassified errors',
        ],
    ],
    
    // Custom human-readable error messages
    'messages' => [
        'database' => tapped_env('TAPPED_ERROR_MESSAGE_DATABASE', 'A database error occurred. Please try again later.'),
        'network' => tapped_env('TAPPED_ERROR_MESSAGE_NETWORK', 'A network error occurred. Please check your connection and try again.'),
        'security' => tapped_env('TAPPED_ERROR_MESSAGE_SECURITY', 'A security issue was detected. Please contact support if this persists.'),
        'websocket' => tapped_env('TAPPED_ERROR_MESSAGE_WEBSOCKET', 'The realtime connection was interrupted. Please refresh the page.'),
        'livewire' => tapped_env('TAPPED_ERROR_MESSAGE_LIVEWIRE', 'There was an issue with a page component. Please refresh the page.'),
        'internal' => tapped_env('TAPPED_ERROR_MESSAGE_INTERNAL', 'An internal server error occurred. Please try again later.'),
        'unknown' => tapped_env('TAPPED_ERROR_MESSAGE_UNKNOWN', 'An unexpected error occurred. Please try again later.'),
    ],
    
    // External error monitoring configuration
    'external_monitoring' => [
        // Whether to enable external monitoring
        'enabled' => tapped_env('TAPPED_EXTERNAL_MONITORING_ENABLED', false),
        
        // Default provider to use (sentry, null)
        'provider' => tapped_env('TAPPED_EXTERNAL_MONITORING_PROVIDER', 'null'),
        
        // Minimum error level to send to external monitoring
        'min_level' => tapped_env('TAPPED_EXTERNAL_MONITORING_MIN_LEVEL', 'warning'),
        
        // Sentry-specific configuration
        'sentry' => [
            // Sentry DSN (required for Sentry)
            'dsn' => tapped_env('TAPPED_SENTRY_DSN', ''),
            
            // Release version for tracking in Sentry
            'release' => tapped_env('TAPPED_SENTRY_RELEASE', tapped_config('app.version', '1.0.0')),
            
            // Traces sample rate (percentage of transactions to sample)
            'traces_sample_rate' => tapped_env('TAPPED_SENTRY_TRACES_SAMPLE_RATE', 0.1),
            
            // Maximum breadcrumbs to record
            'max_breadcrumbs' => tapped_env('TAPPED_SENTRY_MAX_BREADCRUMBS', 50),
        ],
    ],
];
