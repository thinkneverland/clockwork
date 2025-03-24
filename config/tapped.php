<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tapped Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the Tapped debugger
    |
    */

    'enabled' => env('TAPPED_ENABLED', env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | Extensive Logging
    |--------------------------------------------------------------------------
    |
    | Toggle extensive logging for debugging during Tapped development
    |
    */
    'extensive_logging' => env('TAPPED_EXTENSIVE_LOGGING', false),

    /*
    |--------------------------------------------------------------------------
    | MCP Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the PHP-based MCP protocol server
    |
    */
    'mcp_server' => [
        'host' => env('TAPPED_MCP_HOST', '127.0.0.1'),
        'port' => env('TAPPED_MCP_PORT', 8888),
        'secure' => env('TAPPED_MCP_SECURE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how Tapped stores its data
    |
    */
    'storage' => [
        'driver' => env('TAPPED_STORAGE_DRIVER', 'file'),
        'path' => storage_path('tapped'),
        'expiration' => 60 * 24 * 7, // 1 week
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Configuration
    |--------------------------------------------------------------------------
    |
    | Enable/disable specific Tapped features
    |
    */
    'features' => [
        'livewire_state_inspection' => true,
        'state_editing' => true,
        'event_logging' => true,
        'state_snapshots' => true,
        'ajax_tracking' => true,
        'query_monitoring' => true,
        'n1_detection' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Integration Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI IDE/plugin integration hooks
    |
    */
    'ai_integration' => [
        'enabled' => true,
        'endpoints' => [
            'debug_info' => '/tapped/ai/debug-info',
            'state' => '/tapped/ai/state',
            'logs' => '/tapped/ai/logs',
            'screenshots' => '/tapped/ai/screenshots',
        ],
    ],
];
