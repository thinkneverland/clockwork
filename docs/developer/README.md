# Tapped Developer Documentation

This guide is for developers who want to understand Tapped's architecture, extend its functionality, or contribute to the project.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Core Components](#core-components)
3. [Data Collectors](#data-collectors)
4. [Extension Points](#extension-points)
5. [Development Setup](#development-setup)
6. [Testing](#testing)
7. [Contribution Guidelines](#contribution-guidelines)
8. [Code Style](#code-style)
9. [Extension Development](#extension-development)

## Architecture Overview

Tapped follows a modular architecture built on several key components:

```
┌─────────────────────────────────────┐
│           Laravel Application        │
└───────────────┬─────────────────────┘
                │
┌───────────────▼─────────────────────┐
│         Tapped ServiceProvider       │
└───────────────┬─────────────────────┘
                │
    ┌───────────┴──────────┐
    │                      │
┌───▼───────┐      ┌───────▼─────┐
│Middleware │      │Data Collectors│
└───┬───────┘      └───────┬─────┘
    │                      │
    │     ┌───────────────┐│
    └────►│  WebSocket    ├┘
          │   Server      │
          └───┬───────────┘
              │
┌─────────────▼────────────┐
│    Browser Extension      │
└──────────────────────────┘
```

The Tapped package consists of:

1. **Core Services**: Central functionality and management
2. **Data Collectors**: Components that gather specific types of debug data
3. **WebSocket Server**: Real-time communication channel
4. **HTTP Controllers**: API endpoints for data access
5. **Middleware**: Integrates with the Laravel request lifecycle
6. **Browser Extension**: UI for end users
7. **IDE Integrations**: VS Code, JetBrains, and CLI tools

## Core Components

### ServiceProvider

`ThinkNeverland\Tapped\TappedServiceProvider` is the main entry point that registers all components, routes, commands, and middleware.

### Middleware

`ThinkNeverland\Tapped\Middleware\TappedMiddleware` collects data during the request lifecycle and passes it to the WebSocket server.

### WebSocket Server

`ThinkNeverland\Tapped\Server\WebSocketServer` handles real-time communication with browser extensions.

### Data Manager

`ThinkNeverland\Tapped\DataManager` orchestrates the collection, processing, and storage of debug data.

### API Controllers

Controllers in `ThinkNeverland\Tapped\Http\Controllers\Api` handle API requests for retrieving and manipulating debug data.

### Services

- `DebugStateSerializer`: Handles serialization of debug data
- `ScreenshotService`: Manages screenshot capture and processing
- `WebhookService`: Handles webhook notifications
- `DebugInfoCollector`: Gathers comprehensive system information

## Data Collectors

Tapped uses a collector pattern to gather different types of debug data:

### LivewireCollector

`ThinkNeverland\Tapped\DataCollectors\LivewireCollector` captures Livewire component information, including:

- Component instances and class names
- Component properties and methods
- Property updates and state changes
- Mount, hydrate, and update events

### QueryCollector

`ThinkNeverland\Tapped\DataCollectors\QueryCollector` monitors database queries:

- SQL queries with bindings
- Execution time
- Connection information
- N+1 query detection

### EventCollector

`ThinkNeverland\Tapped\DataCollectors\EventCollector` tracks events:

- Livewire lifecycle events
- Custom events
- Event timing and payloads

### RequestCollector

`ThinkNeverland\Tapped\DataCollectors\RequestCollector` gathers HTTP request data:

- Request method and URI
- Headers and payload
- Response status and time
- AJAX request detection

### Creating Custom Collectors

You can create custom collectors by implementing the `CollectorInterface`:

```php
namespace App\Collectors;

use ThinkNeverland\Tapped\Contracts\CollectorInterface;

class CustomCollector implements CollectorInterface
{
    protected $data = [];

    public function getName()
    {
        return 'custom';
    }

    public function collect($request = null)
    {
        // Collect your custom data
        $this->data[] = [
            'timestamp' => microtime(true),
            'custom_value' => 'Your collected data',
        ];
    }

    public function getData()
    {
        return $this->data;
    }

    public function reset()
    {
        $this->data = [];
    }
}
```

Register your custom collector in a service provider:

```php
use ThinkNeverland\Tapped\Facades\Tapped;

public function boot()
{
    Tapped::addCollector(new \App\Collectors\CustomCollector());
}
```

## Extension Points

Tapped provides several extension points:

### Custom Data Collectors

As shown above, you can create custom data collectors to gather specific debug information.

### Custom Storage Adapters

For specialized storage needs, implement the `StorageAdapterInterface`:

```php
namespace App\Storage;

use ThinkNeverland\Tapped\Contracts\StorageAdapterInterface;

class CustomStorageAdapter implements StorageAdapterInterface
{
    public function store($path, $contents)
    {
        // Store file contents
    }

    public function get($path)
    {
        // Get file contents
    }

    public function delete($path)
    {
        // Delete file
    }

    public function exists($path)
    {
        // Check if file exists
    }
}
```

Register your storage adapter:

```php
// config/tapped.php
'storage_adapters' => [
    'custom' => \App\Storage\CustomStorageAdapter::class,
],

'storage' => [
    'driver' => 'custom',
    // ...
],
```

### Middleware Extensions

You can extend the Tapped middleware by creating your own middleware that manipulates Tapped's behavior:

```php
namespace App\Http\Middleware;

use Closure;
use ThinkNeverland\Tapped\Facades\Tapped;

class CustomTappedMiddleware
{
    public function handle($request, Closure $next)
    {
        // Add custom data before request processing
        Tapped::addData('custom', ['pre_request' => true]);
        
        $response = $next($request);
        
        // Add custom data after request processing
        Tapped::addData('custom', ['post_request' => true]);
        
        return $response;
    }
}
```

### Event Listeners

Listen for Tapped events to extend functionality:

```php
use ThinkNeverland\Tapped\Events\SnapshotCreated;

Event::listen(SnapshotCreated::class, function ($event) {
    // Custom handling of snapshot creation
});
```

## Development Setup

To set up a local development environment:

1. Clone the repository:

```bash
git clone https://github.com/thinkneverland/tapped.git
cd tapped
```

2. Install Composer dependencies:

```bash
composer install
```

3. Install npm dependencies for the browser extension:

```bash
cd extension
npm install
```

4. Link the package to a Laravel project for testing:

```bash
# In your Laravel project
composer config repositories.tapped path "/path/to/tapped"
composer require thinkneverland/tapped:dev-main
```

5. Build the browser extension for development:

```bash
cd extension
npm run dev
```

## Testing

Tapped uses several testing approaches:

### Unit Tests

Run PHPUnit tests for the PHP components:

```bash
composer test
```

### Browser Extension Tests

Test the browser extension components:

```bash
cd extension
npm test
```

### End-to-End Tests

Test the full integration between Laravel, Tapped, and the browser extension:

```bash
composer test:e2e
```

### Manual Testing

For manual testing, use the included example app:

```bash
cd example-app
composer install
php artisan serve
```

Then load the development version of the browser extension.

## Contribution Guidelines

We welcome contributions to Tapped! Here's how to contribute:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests (`composer test`)
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

Before submitting a pull request:

- Ensure all tests pass
- Update documentation to reflect your changes
- Follow the code style guidelines
- Write or update tests for your changes

## Code Style

Tapped follows PSR-12 coding standards and uses Laravel coding conventions:

- Use camelCase for methods and variables
- Use StudlyCase for classes
- Use snake_case for configuration keys
- Add appropriate docblocks to all classes and methods
- Use type hints where appropriate

We use PHP CS Fixer to maintain code style:

```bash
composer cs:fix
```

## Extension Development

### Browser Extension

The browser extension is built with Vue.js and follows a modular structure:

```
extension/
├── src/
│   ├── components/   # Vue components
│   ├── store/        # Vuex store modules
│   ├── devtools/     # Chrome/Firefox/Edge devtools panels
│   ├── background/   # Extension background script
│   └── services/     # Helper services
└── dist/             # Built extensions
```

To build for development:

```bash
npm run dev
```

To build production versions:

```bash
npm run build:chrome
npm run build:firefox
npm run build:edge
```

### IDE Extensions

#### VS Code Extension

The VS Code extension is in `ide-integrations/vscode/`:

```
vscode/
├── extension.js      # Main extension code
├── package.json      # Extension metadata and config
└── webviews/         # UI components
```

To build:

```bash
cd ide-integrations/vscode
npm install
npm run compile
```

#### JetBrains Plugin

The JetBrains plugin is in `ide-integrations/jetbrains/`:

```
jetbrains/
├── src/              # Plugin source code
├── build.gradle      # Gradle build file
└── resources/        # Plugin resources
```

To build:

```bash
cd ide-integrations/jetbrains
./gradlew buildPlugin
```

For more detailed information on extending and contributing to Tapped, refer to the [official documentation](https://github.com/thinkneverland/tapped).
