# Tapped API Reference

This document provides detailed information about the Tapped API, including services, middleware, and integration points.

## Core Services

### LivewireStateManager

The `LivewireStateManager` service handles component state management and inspection.

```php
use ThinkNeverland\Tapped\Services\LivewireStateManager;

public function __construct(LivewireStateManager $stateManager)
{
    $this->stateManager = $stateManager;
}
```

#### Methods

- `getState(?string $componentId)`: Get the current state of a component or all components
- `updateState(?string $componentId, array $updates)`: Update component state
- `createSnapshot(?string $componentId)`: Create a state snapshot
- `registerComponent($component)`: Register a new component for tracking

### EventLogger

The `EventLogger` service manages event logging and retrieval.

```php
use ThinkNeverland\Tapped\Services\EventLogger;

public function __construct(EventLogger $eventLogger)
{
    $this->eventLogger = $eventLogger;
}
```

#### Methods

- `log(string $event, array $data = [])`: Log a new event
- `getEvents(int $limit = null)`: Retrieve logged events
- `getEventsByType(string $type, int $limit = null)`: Get events of a specific type
- `getEventsBetween(Carbon $start, Carbon $end, int $limit = null)`: Get events within a time range
- `clear()`: Clear all logged events

## MCP Protocol Server

The MCP (Monitor and Control Protocol) server handles real-time communication between your application and the browser extension.

### Server Configuration

```php
'mcp_server' => [
    'host' => env('TAPPED_MCP_HOST', '127.0.0.1'),
    'port' => env('TAPPED_MCP_PORT', 8888),
    'secure' => env('TAPPED_MCP_SECURE', false),
]
```

### Message Types

#### State Messages

- `state_request`: Request component state
- `state_response`: Component state data
- `state_changed`: State update notification
- `state_update`: Update component state

#### Event Messages

- `event_log`: Log a new event
- `event_logged`: Event logged notification

#### Snapshot Messages

- `snapshot_request`: Request state snapshot
- `snapshot_response`: Snapshot data

## AI Integration

### Available Endpoints

All endpoints require authentication via Laravel Sanctum.

```php
Route::middleware(['api', 'auth:sanctum'])->prefix('tapped/ai')->group(function () {
    Route::get('debug-info', [AiController::class, 'getDebugInfo']);
    Route::get('state', [AiController::class, 'getState']);
    Route::get('logs', [AiController::class, 'getLogs']);
    Route::post('screenshots', [AiController::class, 'storeScreenshot']);
});
```

### Endpoint Details

#### GET /tapped/ai/debug-info

Returns current debugging information including:

- Active components
- Recent events
- Performance metrics

#### GET /tapped/ai/state

Returns component state information:

- Component hierarchy
- Current state values
- State history

#### GET /tapped/ai/logs

Returns event logs:

- Event type
- Timestamp
- Associated data
- Context information

#### POST /tapped/ai/screenshots

Store debugging screenshots:

- Component state
- Visual state
- Error conditions

## Middleware

The `TappedMiddleware` handles request interception and debug information injection.

```php
protected function injectDebugInformation($response): void
{
    if (!method_exists($response, 'getData')) {
        return;
    }

    $data = $response->getData(true);
    $data['tapped'] = [
        'states' => $this->stateManager->getState(null),
        'timestamp' => Carbon::now()->toIso8601String(),
    ];
    $response->setData($data);
}
```

## Storage

### Available Drivers

- `file`: Local file storage (default)
- `redis`: Redis storage
- `database`: SQL database storage

### Configuration

```php
'storage' => [
    'driver' => env('TAPPED_STORAGE_DRIVER', 'file'),
    'path' => storage_path('tapped'),
    'expiration' => 60 * 24 * 7, // 1 week
]
```

## Events

### System Events

- `tapped.component.registered`: Fired when a component is registered
- `tapped.state.updated`: Fired when component state changes
- `tapped.snapshot.created`: Fired when a snapshot is created
- `tapped.event.logged`: Fired when an event is logged

### Event Data Structure

```php
[
    'event' => string,
    'data' => array,
    'timestamp' => string,
    'context' => array
]
```

## Security

### Authentication

The AI integration endpoints use Laravel Sanctum for authentication. Ensure proper token management and access control.

### WebSocket Security

For secure WebSocket connections:

1. Enable SSL/TLS
2. Configure allowed origins
3. Implement token-based authentication

### Data Privacy

- Configure data retention policies
- Use environment-specific settings
- Implement data filtering for sensitive information
