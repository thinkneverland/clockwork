# Tapped Configuration Reference

This document provides a comprehensive reference for configuring the Tapped package.

## Table of Contents

1. [Configuration File](#configuration-file)
2. [General Settings](#general-settings)
3. [WebSocket Server](#websocket-server)
4. [API Configuration](#api-configuration)
5. [Database Settings](#database-settings)
6. [Storage Settings](#storage-settings)
7. [Livewire Settings](#livewire-settings)
8. [Webhook Configuration](#webhook-configuration)
9. [Environment Variables](#environment-variables)
10. [Advanced Configuration](#advanced-configuration)

## Configuration File

After publishing the configuration file, you can find it at `config/tapped.php`. This file contains all the available configuration options for Tapped.

## General Settings

```php
// config/tapped.php

return [
    // Enable or disable Tapped entirely
    'enabled' => env('TAPPED_ENABLED', true),
    
    // The environment(s) where Tapped will be active
    'environments' => ['local', 'development', 'testing'],
    
    // Determine whether Tapped should auto-register its routes
    'register_routes' => true,
    
    // The URL path where Tapped will be accessible
    'path' => 'tapped',
    
    // Maximum number of requests to store
    'max_requests' => 100,
    
    // Maximum execution time in seconds for the WebSocket server
    'max_execution_time' => 0,
];
```

## WebSocket Server

```php
// config/tapped.php

return [
    'websocket' => [
        // Enable or disable the WebSocket server
        'enabled' => env('TAPPED_WEBSOCKET_ENABLED', true),
        
        // The host for the WebSocket server
        'host' => env('TAPPED_WEBSOCKET_HOST', '127.0.0.1'),
        
        // The port for the WebSocket server
        'port' => env('TAPPED_WEBSOCKET_PORT', 8084),
        
        // Set to 'true' to enable SSL for WebSocket server
        'ssl' => env('TAPPED_WEBSOCKET_SSL', false),
        
        // SSL certificate and key paths (required if SSL is enabled)
        'ssl_cert' => env('TAPPED_WEBSOCKET_SSL_CERT'),
        'ssl_key' => env('TAPPED_WEBSOCKET_SSL_KEY'),
        
        // Maximum number of concurrent connections
        'max_connections' => 100,
        
        // Ping interval in seconds (to keep connections alive)
        'ping_interval' => 30,
    ],
];
```

## API Configuration

```php
// config/tapped.php

return [
    'api' => [
        // Enable or disable the API
        'enabled' => true,
        
        // API authentication
        'auth' => [
            // Enable or disable API authentication
            'enabled' => env('TAPPED_API_AUTH_ENABLED', true),
            
            // Authentication token
            'token' => env('TAPPED_API_TOKEN'),
            
            // Token expiration in minutes (null for never)
            'expiration' => null,
        ],
        
        // API rate limiting
        'rate_limit' => [
            // Enable or disable rate limiting
            'enabled' => true,
            
            // Maximum number of requests per minute
            'max_requests' => 60,
            
            // Rate limit drivers: 'cache', 'redis', 'database'
            'driver' => 'cache',
        ],
        
        // CORS settings for API
        'cors' => [
            // Enable or disable CORS
            'enabled' => true,
            
            // Allowed origins (can be '*' for all)
            'allowed_origins' => ['*'],
            
            // Allowed methods
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            
            // Allowed headers
            'allowed_headers' => ['Content-Type', 'Authorization'],
            
            // Whether to allow credentials
            'allow_credentials' => false,
            
            // Max age for preflight requests (in seconds)
            'max_age' => 86400,
        ],
    ],
];
```

## Database Settings

```php
// config/tapped.php

return [
    'database' => [
        // Enable or disable database query collection
        'collect_queries' => true,
        
        // N+1 query detection
        'detect_n1_queries' => true,
        
        // Slow query threshold in milliseconds
        'slow_query_threshold' => 100,
        
        // Query log size (maximum number of queries to store)
        'max_queries' => 200,
        
        // Whether to collect query bindings
        'collect_bindings' => true,
        
        // Database connections to monitor (empty array means all connections)
        'connections' => [],
        
        // Whether to collect query callers (file and line where query was executed)
        'collect_callers' => true,
    ],
];
```

## Storage Settings

```php
// config/tapped.php

return [
    'storage' => [
        // Storage disk to use for screenshots, recordings, and snapshots
        'disk' => env('TAPPED_STORAGE_DISK', 'local'),
        
        // Base path within the disk
        'path' => 'tapped',
        
        // Subdirectories for different types of data
        'paths' => [
            'snapshots' => 'snapshots',
            'screenshots' => 'screenshots',
            'recordings' => 'recordings',
        ],
        
        // Maximum storage size in MB (0 for unlimited)
        'max_size' => 0,
        
        // Auto-cleanup settings
        'cleanup' => [
            // Enable auto-cleanup
            'enabled' => true,
            
            // Delete items older than X days
            'older_than_days' => 7,
            
            // Schedule cleanup task
            'schedule' => 'daily',
        ],
    ],
];
```

## Livewire Settings

```php
// config/tapped.php

return [
    'livewire' => [
        // Enable or disable Livewire component collection
        'enabled' => true,
        
        // Components to exclude from collection (using wildcard patterns)
        'exclude' => [
            // 'App\\Http\\Livewire\\Admin\\*',
        ],
        
        // Whether to collect component view data
        'collect_view_data' => true,
        
        // Whether to allow editing component properties
        'allow_property_editing' => env('TAPPED_ALLOW_PROPERTY_EDITING', true),
        
        // Whether to collect component render times
        'collect_render_times' => true,
        
        // Whether to monitor component updates
        'monitor_updates' => true,
        
        // Whether to monitor events
        'monitor_events' => true,
    ],
];
```

## Webhook Configuration

```php
// config/tapped.php

return [
    'webhooks' => [
        // Define webhook endpoints
        [
            // Webhook URL
            'url' => 'https://example.com/webhook',
            
            // Events to send to this webhook (empty array or ['*'] for all events)
            'events' => ['component.updated', 'query.executed'],
            
            // Secret key for signature verification
            'secret' => env('TAPPED_WEBHOOK_SECRET'),
            
            // Custom headers
            'headers' => [
                // 'X-Custom-Header' => 'value',
            ],
        ],
        // Add more webhook configurations as needed
    ],
];
```

## Environment Variables

Tapped can be configured using the following environment variables:

```env
# General settings
TAPPED_ENABLED=true

# WebSocket server settings
TAPPED_WEBSOCKET_ENABLED=true
TAPPED_WEBSOCKET_HOST=127.0.0.1
TAPPED_WEBSOCKET_PORT=8084
TAPPED_WEBSOCKET_SSL=false
TAPPED_WEBSOCKET_SSL_CERT=
TAPPED_WEBSOCKET_SSL_KEY=

# API settings
TAPPED_API_AUTH_ENABLED=true
TAPPED_API_TOKEN=your-secure-token-here
TAPPED_ALLOW_PROPERTY_EDITING=true

# Storage settings
TAPPED_STORAGE_DISK=local
```

## Advanced Configuration

### Custom Data Collectors

You can register custom data collectors to extend Tapped's functionality:

```php
// In a service provider
public function boot()
{
    $this->app->make('tapped')->addCollector(new CustomCollector());
}
```

### Middleware Configuration

You can customize the middleware behavior:

```php
// config/tapped.php

return [
    'middleware' => [
        // Additional middleware to apply to Tapped routes
        'web' => [],
        'api' => [],
        
        // Whether to exclude certain routes from monitoring
        'exclude' => [
            // Patterns to exclude
            'patterns' => [
                '#^api/documentation#',
                '#^nova-api#',
            ],
            
            // Methods to exclude
            'methods' => [
                'OPTIONS',
            ],
        ],
    ],
];
```

### Custom Storage Adapters

For advanced storage configurations:

```php
// config/tapped.php

return [
    'storage_adapters' => [
        // Register custom storage adapters
        'custom' => CustomStorageAdapter::class,
    ],
];
```

### Performance Optimization

For high-traffic applications:

```php
// config/tapped.php

return [
    'performance' => [
        // Sampling rate (0.0 - 1.0) to reduce overhead
        'sampling_rate' => 1.0,
        
        // Buffer size for WebSocket messages
        'websocket_buffer' => 1024 * 1024, // 1MB
        
        // Use a separate queue for processing
        'use_queue' => false,
        'queue_connection' => 'redis',
        'queue_name' => 'tapped',
    ],
];
```

For more detailed information, refer to the [official documentation](https://github.com/thinkneverland/tapped).
