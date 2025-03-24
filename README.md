# Tapped by ThinkNeverland

A powerful Laravel Livewire debugger with real-time browser extension, PHP-based MCP protocol server, and AI IDE/plugin integration hooks.

## Features

- Real-time Livewire component state inspection
- Inline editing of Livewire states directly from the extension
- Lifecycle and emitted event logging
- Component state snapshotting for time-travel debugging
- Detailed Livewire AJAX request tracking
- Database query monitoring with automatic N+1 query detection
- AI IDE/plugin integration hooks

## Requirements

- PHP 8.1 or higher
- Laravel 10.x
- Livewire (v1, v2, or v3+)

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
        // ...
        \ThinkNeverland\Tapped\Middleware\TappedMiddleware::class,
    ],
];
```

## Configuration

The package can be configured through the `config/tapped.php` file:

```php
return [
    'enabled' => env('TAPPED_ENABLED', env('APP_DEBUG', false)),
    'extensive_logging' => env('TAPPED_EXTENSIVE_LOGGING', false),
    // ... see config/tapped.php for all options
];
```

## Usage

### Starting the MCP Server

Start the MCP server using the provided Artisan command:

```bash
php artisan tapped:mcp-server
```

### Browser Extension

Install the Tapped browser extension:

- [Chrome Web Store](https://chrome.google.com/webstore/detail/tapped)
- [Firefox Add-ons](https://addons.mozilla.org/en-US/firefox/addon/tapped)

### AI IDE/Plugin Integration

Tapped exposes several endpoints for AI IDE/plugin integration:

- `/tapped/ai/debug-info` - Get current debug information
- `/tapped/ai/state` - Get component state
- `/tapped/ai/logs` - Get event logs
- `/tapped/ai/screenshots` - Store and retrieve screenshots

All endpoints require authentication via Laravel Sanctum.

## Security

- All debug endpoints are protected by Laravel Sanctum authentication
- Debug information is only collected when `TAPPED_ENABLED` is true
- Sensitive data can be filtered through configuration

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Credits

- Based on [Clockwork](https://github.com/itsgoingd/clockwork) (MIT License)
- Inspired by [Laravel Debugbar](https://github.com/barryvdh/laravel-debugbar)
- MCP Protocol based on AgentDesk browser-tools specification

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
