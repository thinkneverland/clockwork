# Tapped: Real-time Laravel Livewire Debugger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/thinkneverland/tapped.svg?style=flat-square)](https://packagist.org/packages/thinkneverland/tapped)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/thinkneverland/tapped/tests?label=tests)](https://github.com/thinkneverland/tapped/actions?query=workflow%3Atests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/thinkneverland/tapped/Check%20&%20fix%20styling?label=code%20style)](https://github.com/thinkneverland/tapped/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/thinkneverland/tapped.svg?style=flat-square)](https://packagist.org/packages/thinkneverland/tapped)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Tapped is a powerful debugging tool for Laravel Livewire applications. It provides real-time component state inspection, database query monitoring, event tracking, and AI-assisted debugging through IDE integrations.

![Tapped Browser Extension Demo](docs/images/tapped-demo.png)

## Features

- **Real-time Livewire Component Inspection**: Monitor components, properties, and methods as they change
- **Inline State Editing**: Modify component properties directly from the browser extension
- **Lifecycle and Event Logging**: Track Livewire lifecycle events and custom events
- **Time-travel Debugging**: Capture and restore component state snapshots
- **AJAX Request Tracking**: Monitor Livewire AJAX requests with detailed information
- **Database Query Monitoring**: Track queries with automatic N+1 detection
- **Visual Debugging**: Capture screenshots and screen recordings with annotations
- **IDE Integrations**: Connect with VS Code, JetBrains IDEs, and CLI tools
- **AI-Assisted Debugging**: Get intelligent suggestions for optimizing your code

## Installation

### Step 1: Install the package

```bash
composer require thinkneverland/tapped
```

### Step 2: Publish the configuration

```bash
php artisan vendor:publish --provider="ThinkNeverland\Tapped\TappedServiceProvider"
```

### Step 3: Configure environment variables

Add these settings to your `.env` file:

```
TAPPED_ENABLED=true
TAPPED_WEBSOCKET_ENABLED=true
TAPPED_WEBSOCKET_HOST=127.0.0.1
TAPPED_WEBSOCKET_PORT=8084
TAPPED_API_TOKEN=your-secure-token-here
```

### Step 5: Start the WebSocket server

```bash
php artisan tapped:mcp-server
```

### Step 6: Install the browser extension

- [Chrome Web Store](https://chrome.google.com/webstore/detail/tapped/tapped-extension-id)
- [Firefox Add-ons](https://addons.mozilla.org/en-US/firefox/addon/tapped/)
- [Microsoft Edge Add-ons](https://microsoftedge.microsoft.com/addons/detail/tapped/tapped-extension-id)

## Usage

### Browser Extension

1. Open your Laravel Livewire application in the browser
2. Open the browser's DevTools
3. Navigate to the "Tapped" panel
4. Explore your Livewire components, events, queries, and more

### IDE Integration

#### VS Code Extension

1. Install the Tapped VS Code extension
2. Configure the extension with your Tapped API URL and token
3. Use the command palette to access Tapped features

#### JetBrains Plugin

1. Install the Tapped plugin from the JetBrains marketplace
2. Configure the plugin settings with your API URL and token
3. Use the Tapped tool window to access debugging features

#### CLI Tool

```bash
php tapped-cli.php components:list
php tapped-cli.php queries:n1-detect
php tapped-cli.php screenshot:capture
```

## Configuration

See the [configuration documentation](docs/configuration/README.md) for detailed information about all available configuration options.

## API

Tapped provides a comprehensive API for integrating with external tools and AI assistants. See the [API documentation](docs/api/README.md) for details.

## Documentation

- [Installation Guide](docs/installation/README.md)
- [Configuration Reference](docs/configuration/README.md)
- [API Documentation](docs/api/README.md)
- [User Guides](docs/user-guides/README.md)
- [Developer Documentation](docs/developer/README.md)

## Contributing

Contributions are welcome! Please see our [contributing guide](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email security@thinkneverland.com instead of using the issue tracker. See [SECURITY.md](SECURITY.md) for details.

## Credits

- [Think Neverland](https://github.com/thinkneverland)
- [All Contributors](../../contributors)
- Forked from [itsgoingd/clockwork](https://github.com/itsgoingd/clockwork) (MIT License)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
