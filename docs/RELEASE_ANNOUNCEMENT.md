# Announcing Tapped 1.0.0 🚀

We're excited to announce the release of Tapped 1.0.0, a powerful debugging tool for Laravel Livewire applications!

## What is Tapped?

Tapped is a comprehensive debugging tool that provides real-time component state inspection, database query monitoring, event tracking, and more for Laravel Livewire applications. It includes a browser extension, IDE integrations, and an API for extending its functionality.

![Tapped Browser Extension Demo](images/tapped-demo.png)

## Key Features

- **Real-time Livewire Component Inspection**: Monitor components, properties, and methods as they change
- **Inline State Editing**: Modify component properties directly from the browser extension
- **Lifecycle and Event Logging**: Track Livewire lifecycle events and custom events
- **Time-travel Debugging**: Capture and restore component state snapshots
- **AJAX Request Tracking**: Monitor Livewire AJAX requests with detailed information
- **Database Query Monitoring**: Track queries with automatic N+1 detection
- **Visual Debugging**: Capture screenshots and screen recordings with annotations
- **IDE Integrations**: Connect with VS Code, JetBrains IDEs, and CLI tools

## Getting Started

Installation is simple:

```bash
composer require thinkneverland/tapped
php artisan vendor:publish --provider="ThinkNeverland\Tapped\TappedServiceProvider"
```

Then add the middleware to your `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'web' => [
        // ... other middleware
        \ThinkNeverland\Tapped\Middleware\TappedMiddleware::class,
    ],
];
```

For full installation instructions, visit our [Installation Guide](https://github.com/thinkneverland/tapped/blob/main/docs/installation/README.md).

## Documentation

Comprehensive documentation is available:

- [Installation Guide](https://github.com/thinkneverland/tapped/blob/main/docs/installation/README.md)
- [Configuration Reference](https://github.com/thinkneverland/tapped/blob/main/docs/configuration/README.md)
- [API Documentation](https://github.com/thinkneverland/tapped/blob/main/docs/api/README.md)
- [User Guides](https://github.com/thinkneverland/tapped/blob/main/docs/user-guides/README.md)
- [Developer Documentation](https://github.com/thinkneverland/tapped/blob/main/docs/developer/README.md)

## Browser Extensions

Tapped browser extensions are available for:

- [Chrome Web Store](https://chrome.google.com/webstore/detail/tapped/tapped-extension-id)
- [Firefox Add-ons](https://addons.mozilla.org/en-US/firefox/addon/tapped/)
- [Microsoft Edge Add-ons](https://microsoftedge.microsoft.com/addons/detail/tapped/tapped-extension-id)

## IDE Integrations

Tapped seamlessly integrates with your development environment:

- [VS Code Extension](https://marketplace.visualstudio.com/items?itemName=thinkneverland.tapped)
- [JetBrains Plugin](https://plugins.jetbrains.com/plugin/12345-tapped)
- [CLI Tool](https://github.com/thinkneverland/tapped/tree/main/ide-integrations/cli)

## Video Tutorials

Check out our [video tutorials](https://github.com/thinkneverland/tapped/tree/main/docs/videos) to learn how to use Tapped effectively.

## Contributing

We welcome contributions! Please see our [contributing guide](https://github.com/thinkneverland/tapped/blob/main/CONTRIBUTING.md) for details.

## License

Tapped is open-sourced software licensed under the [MIT license](https://github.com/thinkneverland/tapped/blob/main/LICENSE.md).

## Acknowledgements

- Thank you to all our contributors
- Special thanks to the [Clockwork](https://github.com/itsgoingd/clockwork) project for inspiration
