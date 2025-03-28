# Tapped Installation Guide

## Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
   - [Laravel Package Installation](#laravel-package-installation)
   - [Browser Extension Installation](#browser-extension-installation)
3. [Verification](#verification)
4. [Troubleshooting](#troubleshooting)

## Requirements

Before installing Tapped, ensure your environment meets the following requirements:

- PHP 8.0 or higher
- Laravel 8.0 or higher
- Livewire 2.0 or higher
- Composer
- Node.js 14+ and npm (for browser extension development)
- A modern browser (Chrome, Firefox, or Edge)

## Installation

### Laravel Package Installation

1. Install the package via Composer:

```bash
composer require thinkneverland/tapped
```

2. Publish the configuration file:

```bash
php artisan vendor:publish --provider="ThinkNeverland\Tapped\TappedServiceProvider"
```

3. Add the WebSocket server configuration to your `.env` file:

```
TAPPED_WEBSOCKET_ENABLED=true
TAPPED_WEBSOCKET_HOST=127.0.0.1
TAPPED_WEBSOCKET_PORT=8084
TAPPED_API_TOKEN=your-secure-token-here
```

5. Start the WebSocket server:

```bash
php artisan tapped:mcp-server
```

### Browser Extension Installation

#### Chrome

1. Download the latest Chrome extension from the [releases page](https://github.com/thinkneverland/tapped/releases) or build it from source:

```bash
# Navigate to the extension directory
cd extension

# Install dependencies
npm install

# Build for Chrome
npm run build:chrome
```

2. Open Chrome and navigate to `chrome://extensions/`

3. Enable "Developer mode" (toggle in the top-right corner)

4. Click "Load unpacked" and select the `/dist/chrome` directory or drag the `tapped-chrome.zip` file onto the page

#### Firefox

1. Download the latest Firefox extension from the [releases page](https://github.com/thinkneverland/tapped/releases) or build it from source:

```bash
# Navigate to the extension directory
cd extension

# Install dependencies
npm install

# Build for Firefox
npm run build:firefox
```

2. Open Firefox and navigate to `about:debugging#/runtime/this-firefox`

3. Click "Load Temporary Add-on..." and select the `/dist/firefox` directory or the `tapped-firefox.zip` file

#### Edge

1. Download the latest Edge extension from the [releases page](https://github.com/thinkneverland/tapped/releases) or build it from source:

```bash
# Navigate to the extension directory
cd extension

# Install dependencies
npm install

# Build for Edge
npm run build:edge
```

2. Open Edge and navigate to `edge://extensions/`

3. Enable "Developer mode" (toggle in the bottom-left)

4. Click "Load unpacked" and select the `/dist/edge` directory or drag the `tapped-edge.zip` file onto the page

## Verification

To verify that Tapped is correctly installed:

1. Start your Laravel application:

```bash
php artisan serve
```

2. Open your browser and navigate to your application (e.g., http://localhost:8000)

3. Open the browser's DevTools and look for the "Tapped" panel

4. If the Tapped panel displays and can connect to your application, the installation was successful

## Troubleshooting

### WebSocket Connection Issues

- Ensure the WebSocket server is running (`php artisan tapped:mcp-server`)
- Check that your `.env` configuration matches the WebSocket host and port settings
- Verify that no firewall is blocking the WebSocket port
- Check the Laravel logs for any WebSocket-related errors

### Extension Not Appearing

- Make sure you've reloaded the browser after installing the extension
- Verify that the extension is enabled in your browser's extension settings
- Try reinstalling the extension
- Check the browser console for any extension-related errors

### Laravel Package Issues

- Verify that the Tapped middleware is correctly registered
- Ensure that Livewire is properly installed and working
- Try clearing Laravel's cache:

```bash
php artisan config:clear
php artisan cache:clear
```

### N+1 Query Detection Not Working

- Make sure you have database query logging enabled in your Tapped configuration
- Verify that you're using Eloquent ORM for your database queries
- Check if your database connection is correctly configured

For further assistance, please [create an issue](https://github.com/thinkneverland/tapped/issues) on our GitHub repository.
