<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use ThinkNeverland\Tapped\Facades\Tapped;
use Illuminate\Support\Str;

// Routes for Tapped UI assets - Dev Environment Only
Route::prefix('_tapped')->middleware(['web'])->group(function () {
    // Metadata retrieval endpoints (required for the Tapped extension)
    Route::get('{id}', function ($id) {
        // Basic sample metadata for testing
        $metadata = [
            'id' => $id,
            'time' => microtime(true),
            'method' => request()->method(),
            'uri' => request()->path(),
            'controller' => 'App\\Http\\Controllers\\ExampleController@index',
            'responseStatus' => 200,
            'responseTime' => microtime(true),
            'responseDuration' => 150,
            'databaseDuration' => 50,
            'databaseQueries' => [],
            'log' => [
                [
                    'time' => microtime(true),
                    'level' => 'info',
                    'message' => 'Tapped is now collecting data!',
                    'context' => ['source' => 'Tapped']
                ]
            ],
            'timelineData' => [],
            'viewsData' => [],
            'userData' => [],
        ];

        return response()->json([$metadata])
            ->header('Content-Type', 'application/json')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    })->where('id', '[a-fA-F0-9-]+');

    // Latest metadata endpoint
    Route::get('latest', function () {
        $id = Str::uuid()->toString();

        // Basic sample metadata for testing
        $metadata = [
            'id' => $id,
            'time' => microtime(true),
            'method' => request()->method(),
            'uri' => request()->path(),
            'controller' => 'App\\Http\\Controllers\\ExampleController@index',
            'responseStatus' => 200,
            'responseTime' => microtime(true),
            'responseDuration' => 150,
            'databaseDuration' => 50,
            'databaseQueries' => [],
            'log' => [
                [
                    'time' => microtime(true),
                    'level' => 'info',
                    'message' => 'Tapped is now collecting data!',
                    'context' => ['source' => 'Tapped']
                ]
            ],
            'timelineData' => [],
            'viewsData' => [],
            'userData' => [],
        ];

        return response()->json([$metadata])
            ->header('Content-Type', 'application/json')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    });

    // Next requests after a given ID
    Route::get('{id}/next', function ($id) {
        return response()->json([]);
    })->where('id', '[a-fA-F0-9-]+');

    // Previous requests before a given ID
    Route::get('{id}/previous', function ($id) {
        return response()->json([]);
    })->where('id', '[a-fA-F0-9-]+');

    // Development mode status indicator
    Route::get('dev-status', function () {
        return response()->json([
            'status' => 'development',
            'version' => '1.0-dev',
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'environmentVariables' => [
                'APP_ENV' => env('APP_ENV'),
                'APP_DEBUG' => (bool) env('APP_DEBUG'),
            ],
            'serverTime' => now()->toIso8601String(),
            'isRunningInDocker' => file_exists('/.dockerenv'),
        ]);
    });

    // Serve the compiled assets with development-friendly headers
    Route::get('assets/{path}', function ($path) {
        $filePath = public_path("vendor/tapped/{$path}");

        if (!file_exists($filePath)) {
            // Attempt to look in extension/build directory for development
            $devPath = base_path("extension/build/{$path}");
            if (file_exists($devPath)) {
                $filePath = $devPath;
            } else {
                abort(404, "Asset not found: {$path}");
            }
        }

        $contentType = match (pathinfo($path, PATHINFO_EXTENSION)) {
            'js' => 'application/javascript',
            'css' => 'text/css',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'json' => 'application/json',
            default => 'text/plain',
        };

        // Add development-friendly headers
        $headers = [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Tapped-Development' => 'true'
        ];

        return response()->file($filePath, $headers);
    })->where('path', '.*');

    // API endpoint to get JavaScript dependencies
    Route::get('dependencies', function (Request $request) {
        $includeAll = $request->boolean('all', true);
        $format = $request->get('format', 'json');

        // Get dependencies using the Tapped Facade
        $dependencies = Tapped::getJsDependencies($includeAll);

        if ($format === 'package-json') {
            // Return a complete package.json file
            return response()->json([
                'name' => 'tapped-dev-dependencies',
                'private' => true,
                'description' => 'Development dependencies for Tapped',
                'dependencies' => $dependencies['dependencies'] ?? $dependencies,
                'devDependencies' => $dependencies['devDependencies'] ?? [],
                'scripts' => [
                    'dev' => 'concurrently "vite" "nodemon --watch src"',
                    'watch' => 'vite build --watch',
                    'build' => 'vite build',
                    'serve' => 'browser-sync start --server "public" --files "public/**/*"',
                ]
            ]);
        }

        // Default: return just the dependencies
        return response()->json($dependencies);
    });

    // List all available templates (for development)
    Route::get('templates', function () {
        $templatesDir = base_path('extension/src/components');

        if (!is_dir($templatesDir)) {
            return response()->json(['error' => 'Templates directory not found'], 404);
        }

        $files = File::files($templatesDir);
        $templates = [];

        foreach ($files as $file) {
            $templates[] = [
                'name' => $file->getFilenameWithoutExtension(),
                'path' => $file->getRelativePathname(),
                'size' => $file->getSize(),
                'modified' => date('Y-m-d H:i:s', $file->getMTime()),
            ];
        }

        return response()->json([
            'count' => count($templates),
            'templates' => $templates
        ]);
    });

    // Reload detector endpoint (for dev mode auto-refresh)
    Route::get('reload-check', function () {
        return response()->json([
            'timestamp' => time(),
            'buildHash' => md5_file(public_path('vendor/tapped/assets/index.js')),
        ]);
    });

    // Serve the Tapped UI entry point with development mode enabled
    Route::get('/', function () {
        if (!file_exists(public_path('vendor/tapped/index.html'))) {
            return response()->view('tapped::dev-mode-missing', [
                'error' => 'Missing Tapped UI files',
                'setupCommand' => 'php artisan vendor:publish --tag=tapped-assets'
            ], 404);
        }

        $html = file_get_contents(public_path('vendor/tapped/index.html'));

        // Inject base path
        $html = str_replace(
            '<head>',
            '<head>
            <base href="' . url('__tapped/') . '">
            <meta name="x-tapped-dev-mode" content="true">
            <script>window.TAPPED_DEV_MODE = true;</script>',
            $html
        );

        // Check if CDN mode is enabled
        $provider = app()->make(ThinkNeverland\Tapped\TappedServiceProvider::class);

        if ($provider->isCdnModeEnabled()) {
            // Inject CDN libraries
            $cdnLibraries = $provider->getCdnLibraries();
            $cdnImports = '';

            foreach ($cdnLibraries as $library => $config) {
                $cdnImports .= '<script src="' . $config['url'] . '"></script>' . PHP_EOL;
            }

            $html = str_replace('</head>', $cdnImports . '</head>', $html);

            // Add script to make CDN libraries available to ES modules
            $cdnGlobals = '<script>window.TappedCDN = true;';
            foreach ($cdnLibraries as $library => $config) {
                $cdnGlobals .= 'window.' . $library . ' = window.' . $config['global'] . ';';
            }
            $cdnGlobals .= '</script>';

            $html = str_replace('</head>', $cdnGlobals . '</head>', $html);
        }

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Tapped-Development-Mode' => 'enabled'
        ]);
    });
});
