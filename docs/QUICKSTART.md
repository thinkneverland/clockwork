# Tapped Quickstart Guide

This guide will help you get up and running with Tapped in just a few minutes.

## Installation

### Step 1: Install the Package

```bash
composer require thinkneverland/tapped
```

### Step 2: Add the Middleware

In your `app/Http/Kernel.php` file, add the Tapped middleware to your web middleware group:

```php
protected $middlewareGroups = [
    'web' => [
        // ... other middleware
        \ThinkNeverland\Tapped\Middleware\TappedMiddleware::class,
    ],
];
```

### Step 3: Publish the Configuration

```bash
php artisan vendor:publish --provider="ThinkNeverland\Tapped\TappedServiceProvider"
```

### Step 4: Configure Environment Variables

Add these settings to your `.env` file:

```
TAPPED_ENABLED=true
TAPPED_WEBSOCKET_ENABLED=true
TAPPED_WEBSOCKET_HOST=127.0.0.1
TAPPED_WEBSOCKET_PORT=8084
TAPPED_API_TOKEN=your-secure-token-here
```

### Step 5: Start the WebSocket Server

```bash
php artisan tapped:mcp-server
```

### Step 6: Install the Browser Extension

Install the Tapped extension for your browser:
- [Chrome Web Store](https://chrome.google.com/webstore/detail/tapped/tapped-extension-id)
- [Firefox Add-ons](https://addons.mozilla.org/en-US/firefox/addon/tapped/)
- [Microsoft Edge Add-ons](https://microsoftedge.microsoft.com/addons/detail/tapped/tapped-extension-id)

## Basic Usage

### 1. Open Your Application

- Start your Laravel application
- Navigate to a page with Livewire components

### 2. Open the Browser Extension

- Click on the Tapped icon in your browser toolbar
- Or open your browser's DevTools and navigate to the "Tapped" panel

### 3. Inspect Components

- View all Livewire components on the current page
- Examine component properties and state
- Monitor component lifecycle events

### 4. Edit Component State

- Click on any component property to edit its value
- Changes are reflected in real-time in your application

### 5. Track Events

- View Livewire events as they are emitted
- See event payloads and timing information
- Track the component that emitted each event

### 6. Monitor Database Queries

- View all database queries executed by your page
- Identify N+1 query issues
- See query timing and performance metrics

## IDE Integration

### VS Code

1. Install the Tapped VS Code extension
2. Open the Command Palette (Ctrl+Shift+P or Cmd+Shift+P)
3. Type "Tapped: Connect" to connect to your application
4. Use the Tapped panel to interact with your application

### JetBrains IDEs

1. Install the Tapped plugin from the JetBrains Marketplace
2. Configure the plugin with your Tapped API URL and token
3. Access Tapped features from the dedicated tool window

## Next Steps

- [Complete Configuration Reference](configuration/README.md)
- [Advanced Debugging Techniques](user-guides/README.md)
- [API Documentation](api/README.md)
- [Developer Documentation](developer/README.md)
