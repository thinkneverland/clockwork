# Getting Started with Tapped

This guide will walk you through the process of setting up and using Tapped in your Laravel Livewire application.

## Installation

1. Install the package via Composer:

```bash
composer require thinkneverland/tapped
```

2. Publish the configuration file:

```bash
php artisan vendor:publish --tag=tapped-config
```

3. Add the middleware to your `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'web' => [
        // ... other middleware
        \ThinkNeverland\Tapped\Middleware\TappedMiddleware::class,
    ],
];
```

## Configuration

The package can be configured through the `config/tapped.php` file. Here are the key configuration options:

```php
return [
    // Enable/disable Tapped (defaults to APP_DEBUG value)
    'enabled' => env('TAPPED_ENABLED', env('APP_DEBUG', false)),

    // Enable extensive logging for debugging
    'extensive_logging' => env('TAPPED_EXTENSIVE_LOGGING', false),

    // MCP Server Configuration
    'mcp_server' => [
        'host' => env('TAPPED_MCP_HOST', '127.0.0.1'),
        'port' => env('TAPPED_MCP_PORT', 8888),
        'secure' => env('TAPPED_MCP_SECURE', false),
    ],

    // Storage Configuration
    'storage' => [
        'driver' => env('TAPPED_STORAGE_DRIVER', 'file'),
        'path' => storage_path('tapped'),
        'expiration' => 60 * 24 * 7, // 1 week
    ],
];
```

## Setting Up the MCP Server

1. Start the MCP server using the provided Artisan command:

```bash
php artisan tapped:mcp-server
```

2. The server will start on the configured host and port (default: 127.0.0.1:8888)

## Installing the Browser Extension

1. Install the Tapped browser extension from your browser's extension store:
   - [Chrome Web Store](https://chrome.google.com/webstore/detail/tapped)
   - [Firefox Add-ons](https://addons.mozilla.org/en-US/firefox/addon/tapped)

2. Once installed, you'll see the Tapped icon in your browser's toolbar

## Basic Usage

### Component State Inspection

1. Open your Laravel application in the browser
2. Click the Tapped extension icon
3. Navigate to the Components tab to see all active Livewire components
4. Click on a component to inspect its state

### State Editing

1. In the component inspector, find the property you want to edit
2. Click the edit icon next to the property
3. Modify the value and press Enter to save
4. The component will automatically update in real-time

### Event Logging

1. Open the Events tab in the Tapped extension
2. All Livewire events will be logged here in real-time
3. Click on an event to see its details and payload

### Query Monitoring

1. Navigate to the Queries tab
2. View all database queries executed by your components
3. Identify N+1 query issues highlighted in red

## Security Considerations

1. Only enable Tapped in development environments
2. Use the `TAPPED_ENABLED` environment variable to control access
3. Configure secure WebSocket connections in production if needed
4. Use authentication for AI integration endpoints

## Next Steps

- Explore the [API Reference](api-reference.md) for advanced usage
- Learn about AI IDE integration
- Configure custom storage drivers
- Set up automated debugging workflows
