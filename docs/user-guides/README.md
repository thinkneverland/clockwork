# Tapped User Guide

This guide provides instructions for using Tapped, a powerful Laravel Livewire debugger with real-time browser extension, PHP-based protocol server, and AI IDE integration.

## Table of Contents

1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Browser Extension](#browser-extension)
   - [Overview](#overview)
   - [Component Inspector](#component-inspector)
   - [State Editor](#state-editor)
   - [Event Timeline](#event-timeline)
   - [Query Analyzer](#query-analyzer)
   - [Time-Travel Debugging](#time-travel-debugging)
4. [Debugging Techniques](#debugging-techniques)
   - [Real-time Component State Inspection](#real-time-component-state-inspection)
   - [Finding N+1 Query Issues](#finding-n1-query-issues)
   - [Event Monitoring](#event-monitoring)
   - [AJAX Request Tracking](#ajax-request-tracking)
5. [Advanced Features](#advanced-features)
   - [Component Snapshots](#component-snapshots)
   - [Screenshots and Recording](#screenshots-and-recording)
   - [Webhook Notifications](#webhook-notifications)
6. [IDE Integrations](#ide-integrations)
   - [VS Code Extension](#vs-code-extension)
   - [JetBrains Plugin](#jetbrains-plugin)
   - [CLI Tool](#cli-tool)
7. [AI-Assisted Debugging](#ai-assisted-debugging)
8. [Troubleshooting](#troubleshooting)

## Introduction

Tapped is a powerful debugging tool for Laravel Livewire applications. It provides real-time component state inspection, database query monitoring, event tracking, and AI-assisted debugging through IDE integrations.

Key features include:
- Real-time Livewire component state inspection
- Inline editing of component states
- Lifecycle and emitted event logging
- State snapshotting for time-travel debugging
- Detailed Livewire AJAX request tracking
- Database query monitoring with N+1 detection
- Screenshot and recording capabilities
- IDE integration with VS Code, JetBrains, and CLI

## Getting Started

After [installing Tapped](../installation/README.md) and configuring it according to the [configuration guide](../configuration/README.md), you can start using it right away.

1. Start your Laravel application with `php artisan serve`
2. Start the Tapped WebSocket server with `php artisan tapped:mcp-server`
3. Open your application in a browser with the Tapped extension installed
4. Open the browser DevTools and navigate to the "Tapped" panel

## Browser Extension

### Overview

The Tapped browser extension adds a new panel to your browser's developer tools. This panel contains several sections:

- **Components**: Lists all Livewire components on the current page
- **Queries**: Shows database queries executed by the current request
- **Events**: Displays events emitted by Livewire components
- **Requests**: Shows HTTP requests made by the application
- **Timeline**: Visualizes the component lifecycle and events over time
- **Screenshots**: Manages screenshots and recordings

### Component Inspector

The Component Inspector allows you to inspect the state and behavior of Livewire components:

1. Click on a component in the Components list to select it
2. The component's properties and methods will be displayed in the right panel
3. Properties are shown with their current values and types
4. Methods are listed with their parameters
5. You can also see the component's view template and render time

**Tip**: Hover over a component in the web page while holding the `Alt` key to highlight it and quickly access its debug information.

### State Editor

Tapped allows you to edit component properties in real-time:

1. Select a component in the Components list
2. Find the property you want to edit in the properties panel
3. Click on the value to edit it
4. Press Enter to save the change

The component will re-render with the new property value, allowing you to see the effect immediately.

**Note**: Editing is only possible for public properties that don't have getters/setters.

### Event Timeline

The Event Timeline visualizes component lifecycle events and emitted events:

1. Select the Timeline tab to see events over time
2. Events are color-coded by type (lifecycle, component updates, etc.)
3. Click on an event to see its details, including payload and originating component
4. Filter events by type using the dropdown menu
5. Search for specific events using the search box

### Query Analyzer

The Query Analyzer helps you identify and fix database performance issues:

1. Select the Queries tab to see all database queries
2. Queries are displayed with their execution time, bindings, and origin
3. Slow queries are highlighted in red
4. N+1 query issues are automatically detected and grouped
5. Hover over a query to see its full SQL with bindings replaced
6. Click the "Explain" button to run an `EXPLAIN` query (MySQL/PostgreSQL only)

### Time-Travel Debugging

Time-travel debugging allows you to capture and restore component states:

1. Click the "Capture Snapshot" button to save the current state
2. Enter a descriptive label for the snapshot
3. Navigate to the Snapshots tab to see all captured snapshots
4. Click a snapshot to view its details
5. Click "Restore" to apply the snapshot's state to the current components

**Tip**: Take snapshots before making significant changes to your application state to easily revert if needed.

## Debugging Techniques

### Real-time Component State Inspection

To effectively debug Livewire components:

1. Use the Component Inspector to view component properties
2. Watch how properties change during user interactions
3. Use the "Track Changes" feature to highlight property changes as they occur
4. Group properties by access level (public, protected, private) or alphabetically
5. Search for specific properties using the search box

### Finding N+1 Query Issues

N+1 query issues are a common performance problem. Tapped helps you identify and fix them:

1. Navigate to the Queries tab
2. Look for warnings in the "N+1 Query Issues" section
3. Each issue shows the query pattern, count, and affected component
4. Expand the issue to see all related queries
5. Use the suggested fix provided by Tapped to solve the issue

**Example**: If you see a pattern of multiple queries like `SELECT * FROM comments WHERE post_id = ?` executed in a loop, use eager loading with `Post::with('comments')->find($id)` instead.

### Event Monitoring

Monitoring events helps understand component communication:

1. Navigate to the Events tab
2. View all events, including Livewire lifecycle events and custom events
3. Filter events by type or component
4. Click on an event to see its payload and backtrace
5. Use the event timeline to understand the sequence of events

### AJAX Request Tracking

Track Livewire AJAX requests:

1. Navigate to the Requests tab
2. View all HTTP requests, including regular requests and Livewire updates
3. See request details including method, URL, payload, and response
4. Track request performance with response time metrics
5. Filter requests by type (Livewire, AJAX, regular)

## Advanced Features

### Component Snapshots

Snapshots allow you to save and restore component states:

1. Click "Capture Snapshot" in the Components tab
2. Enter a descriptive label
3. View snapshots in the Snapshots tab
4. Compare snapshots to see what changed
5. Restore snapshots to revert component state

You can also create snapshots programmatically:

```php
use ThinkNeverland\Tapped\Facades\Tapped;

Tapped::snapshot('Before updating user');
// Make changes...
Tapped::snapshot('After updating user');
```

### Screenshots and Recording

Capture visual state for debugging:

1. Click "Take Screenshot" to capture the current page
2. Use the selector input to capture specific elements
3. Start a recording session to capture a sequence of states
4. Add annotations to screenshots to highlight important details
5. Export recordings as GIFs for sharing

You can also capture screenshots programmatically:

```php
use ThinkNeverland\Tapped\Facades\Tapped;

Tapped::screenshot('homepage');
Tapped::startRecording('user-registration-flow');
// ... user registration process
Tapped::completeRecording();
```

### Webhook Notifications

Set up webhook notifications to integrate with external tools:

1. Configure webhooks in the `config/tapped.php` file
2. Specify events to monitor (component updates, query execution, etc.)
3. Receive notifications in real-time when events occur
4. Integrate with tools like Slack, Discord, or custom monitoring systems

## IDE Integrations

### VS Code Extension

The VS Code extension provides integration with Tapped:

1. Install the Tapped VS Code extension from the marketplace
2. Configure the extension with your Tapped API URL and token
3. Use the "Tapped: Connect" command to establish a connection
4. View components, queries, and events directly in VS Code
5. Jump to component definitions from debug data
6. Analyze N+1 queries with AI suggestions

### JetBrains Plugin

The JetBrains plugin brings Tapped functionality to PhpStorm:

1. Install the Tapped plugin from the JetBrains marketplace
2. Configure the plugin with your Tapped API URL and token
3. Use the Tapped tool window to view debug data
4. Navigate directly from debug data to source code
5. Analyze queries and get optimization suggestions

### CLI Tool

The CLI tool provides command-line access to Tapped:

1. Configure the CLI tool with your API URL and token
2. List components with `php tapped-cli.php components:list`
3. View queries with `php tapped-cli.php queries:list`
4. Detect N+1 issues with `php tapped-cli.php queries:n1-detect`
5. Capture screenshots with `php tapped-cli.php screenshot:capture`

## AI-Assisted Debugging

Tapped leverages AI to enhance the debugging experience:

1. Connect to OpenAI or other AI providers in IDE integrations
2. Get intelligent suggestions for fixing N+1 query issues
3. Generate optimized database queries based on your actual usage patterns
4. Receive component optimization recommendations
5. Translate debugging data into natural language explanations

Example using the VS Code extension:

1. Open a Livewire component in the editor
2. Right-click and select "Tapped: Analyze Component"
3. The AI will analyze the component and suggest improvements
4. Apply suggestions directly from the editor

## Troubleshooting

### The Tapped panel doesn't appear in DevTools

- Make sure the extension is installed and enabled
- Check that your Laravel application is running
- Verify that the Tapped middleware is registered in your `app/Http/Kernel.php`
- Check for errors in the browser console

### Component data is not updating in real-time

- Ensure the WebSocket server is running (`php artisan tapped:mcp-server`)
- Check WebSocket connection in the browser console
- Verify WebSocket port is accessible (default: 8084)
- Check for firewall issues blocking WebSocket connections

### N+1 query detection not working

- Make sure query logging is enabled in your Tapped config
- Verify you're using Eloquent for database queries
- Check that your database connection is properly configured

### Extension disconnects frequently

- Increase the ping interval in your WebSocket configuration
- Check for network stability issues
- Verify the WebSocket server has sufficient resources

### Slow performance when using Tapped

- Consider using the sampling rate configuration to reduce overhead
- Disable Tapped in production environments
- Limit the number of queries and events recorded
- Use the performance optimization settings in the configuration file

For additional support, consult the [official documentation](https://github.com/thinkneverland/tapped) or open an issue on GitHub.
