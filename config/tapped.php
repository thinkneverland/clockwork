<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tapped Enabled
    |--------------------------------------------------------------------------
    |
    | This option controls whether Tapped is enabled for your application.
    | When enabled, Tapped will listen for requests and collect data
    | for debugging and profiling. It's recommended to disable Tapped in
    | production environments for optimal performance.
    |
    */
    'enabled' => env('TAPPED_ENABLED', env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | Extensive Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, Tapped will collect comprehensive data about your
    | application, including detailed stack traces, serialized objects, and
    | more. This can be useful for debugging but may affect performance.
    |
    */
    'extensive_logging' => env('TAPPED_EXTENSIVE_LOGGING', false),

    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | This option determines where the collected Tapped data will be stored.
    | By default, it uses the Laravel storage path with a 'tapped' subdirectory.
    |
    */
    'storage_path' => storage_path('tapped'),

    /*
    |--------------------------------------------------------------------------
    | Storage Lifetime
    |--------------------------------------------------------------------------
    |
    | This option controls how long (in minutes) Tapped should retain data
    | before it's automatically purged. Set to 0 to keep data indefinitely.
    |
    */
    'storage_lifetime' => env('TAPPED_STORAGE_LIFETIME', 1440), // 24 hours

    /*
    |--------------------------------------------------------------------------
    | MCP Server Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the Model Context Protocol server that powers
    | the real-time communication between your application and browser extension.
    |
    */
    'mcp_server' => [
        'host' => env('TAPPED_MCP_HOST', '127.0.0.1'),
        'port' => env('TAPPED_MCP_PORT', 8888),
        'authentication' => [
            'enabled' => env('TAPPED_MCP_AUTH_ENABLED', false),
            'password' => env('TAPPED_MCP_AUTH_PASSWORD'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Collect Data
    |--------------------------------------------------------------------------
    |
    | Control which types of data Tapped should collect.
    |
    */
    'collect' => [
        'database_queries' => env('TAPPED_COLLECT_DATABASE_QUERIES', true),
        'models' => env('TAPPED_COLLECT_MODELS', true),
        'livewire_components' => env('TAPPED_COLLECT_LIVEWIRE_COMPONENTS', true),
        'events' => env('TAPPED_COLLECT_EVENTS', true),
        'cache' => env('TAPPED_COLLECT_CACHE', true),
        'redis' => env('TAPPED_COLLECT_REDIS', true),
        'views' => env('TAPPED_COLLECT_VIEWS', true),
        'routes' => env('TAPPED_COLLECT_ROUTES', true),
        'logs' => env('TAPPED_COLLECT_LOGS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | N+1 Query Detection
    |--------------------------------------------------------------------------
    |
    | When enabled, Tapped will detect and report potential N+1 query
    | problems in your application.
    |
    */
    'detect_n_plus_1_queries' => env('TAPPED_DETECT_N_PLUS_1_QUERIES', true),

    /*
    |--------------------------------------------------------------------------
    | Ignored Paths
    |--------------------------------------------------------------------------
    |
    | Tapped will not collect data for requests matching these patterns.
    | Specify URI patterns that should be ignored (using regex format).
    |
    */
    'ignored_paths' => [
        '#^telescope#',       // Laravel Telescope
        '#^horizon#',         // Laravel Horizon
        '#^nova#',            // Laravel Nova
        '#^livewire/(?!message|update)#', // Livewire assets but keep the message/update endpoints
        '#^_debugbar#',       // PHP DebugBar
        '#^.+\.(js|css|png|jpg|jpeg|gif|webp|svg|ico|woff|woff2|ttf|otf|eot)$#', // Assets
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Route
    |--------------------------------------------------------------------------
    |
    | The URI where the Tapped dashboard will be available.
    | Set to null to disable the dashboard.
    |
    */
    'dashboard_route' => env('TAPPED_DASHBOARD_ROUTE', 'tapped'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Middleware
    |--------------------------------------------------------------------------
    |
    | The middleware to apply to the Tapped dashboard routes.
    | This is a good place to add authorization for accessing the dashboard.
    |
    */
    'middleware' => env('TAPPED_MIDDLEWARE', 'web'),
];
