<?php

namespace ThinkNeverland\Tapped\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DebugInfoCollector
{
    /**
     * Collect comprehensive debug information about the application.
     *
     * @param bool $includeEnv Whether to include environment information
     * @param bool $includeDatabaseInfo Whether to include database schema information
     * @param bool $includeLivewireComponents Whether to include Livewire component analysis
     * @return array
     */
    public function collect(bool $includeEnv = true, bool $includeDatabaseInfo = true, bool $includeLivewireComponents = true): array
    {
        $info = [
            'collected_at' => now()->toIso8601String(),
            'app' => $this->collectAppInfo(),
        ];
        
        if ($includeEnv) {
            $info['environment'] = $this->collectEnvironmentInfo();
        }
        
        if ($includeDatabaseInfo) {
            $info['database'] = $this->collectDatabaseInfo();
        }
        
        if ($includeLivewireComponents) {
            $info['livewire'] = $this->collectLivewireInfo();
        }
        
        $info['routes'] = $this->collectRouteInfo();
        $info['errors'] = $this->collectErrorInfo();
        $info['optimization'] = $this->collectOptimizationSuggestions();
        
        return $info;
    }

    /**
     * Collect basic application information.
     *
     * @return array
     */
    protected function collectAppInfo(): array
    {
        return [
            'name' => config('app.name'),
            'env' => App::environment(),
            'debug' => config('app.debug'),
            'url' => config('app.url'),
            'version' => App::version(),
            'timezone' => config('app.timezone'),
            'locale' => App::getLocale(),
            'php_version' => PHP_VERSION,
        ];
    }

    /**
     * Collect environment information.
     *
     * @return array
     */
    protected function collectEnvironmentInfo(): array
    {
        // Only include non-sensitive environment variables
        $safeVars = [
            'APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_TIMEZONE',
            'DB_CONNECTION', 'CACHE_DRIVER', 'QUEUE_CONNECTION',
            'SESSION_DRIVER', 'FILESYSTEM_DISK',
        ];
        
        $envVars = [];
        foreach ($safeVars as $var) {
            if (env($var) !== null) {
                $envVars[$var] = env($var);
            }
        }
        
        return [
            'env_vars' => $envVars,
            'server' => [
                'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'protocol' => isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'Unknown',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            ],
            'php' => [
                'version' => PHP_VERSION,
                'extensions' => get_loaded_extensions(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ],
        ];
    }

    /**
     * Collect database information.
     *
     * @return array
     */
    protected function collectDatabaseInfo(): array
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        
        $tables = [];
        try {
            // Get all tables
            $tableNames = Schema::getConnection()->getDoctrineSchemaManager()->listTableNames();
            
            foreach ($tableNames as $tableName) {
                // Only include table structure, not the data
                $columns = Schema::getColumnListing($tableName);
                $columnInfo = [];
                
                foreach ($columns as $column) {
                    $type = Schema::getColumnType($tableName, $column);
                    $columnInfo[$column] = [
                        'type' => $type,
                    ];
                }
                
                $tables[$tableName] = [
                    'columns' => $columnInfo,
                    'indexes' => $this->getTableIndexes($tableName),
                ];
            }
        } catch (\Exception $e) {
            return [
                'connection' => $connection,
                'driver' => $driver,
                'error' => $e->getMessage(),
            ];
        }
        
        return [
            'connection' => $connection,
            'driver' => $driver,
            'tables' => $tables,
        ];
    }

    /**
     * Get indexes for a table.
     *
     * @param string $tableName
     * @return array
     */
    protected function getTableIndexes(string $tableName): array
    {
        try {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes($tableName);
            
            $result = [];
            foreach ($indexes as $name => $index) {
                $result[$name] = [
                    'columns' => $index->getColumns(),
                    'is_unique' => $index->isUnique(),
                    'is_primary' => $index->isPrimary(),
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Collect Livewire component information.
     *
     * @return array
     */
    protected function collectLivewireInfo(): array
    {
        // Check if Livewire is installed
        if (!class_exists('\Livewire\Livewire')) {
            return [
                'installed' => false,
            ];
        }
        
        try {
            $componentClasses = $this->discoverLivewireComponents();
            
            $components = [];
            foreach ($componentClasses as $class) {
                // Basic information only - no instantiation
                $reflection = new \ReflectionClass($class);
                
                $components[$class] = [
                    'name' => $this->getLivewireComponentName($class),
                    'file' => $reflection->getFileName(),
                    'namespace' => $reflection->getNamespaceName(),
                    'has_render_method' => $reflection->hasMethod('render'),
                ];
            }
            
            return [
                'installed' => true,
                'version' => $this->getLivewireVersion(),
                'components' => $components,
            ];
        } catch (\Exception $e) {
            return [
                'installed' => true,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get the Livewire version.
     *
     * @return string|null
     */
    protected function getLivewireVersion(): ?string
    {
        try {
            $composerLock = json_decode(file_get_contents(base_path('composer.lock')), true);
            
            foreach ($composerLock['packages'] ?? [] as $package) {
                if ($package['name'] === 'livewire/livewire') {
                    return $package['version'];
                }
            }
        } catch (\Exception $e) {
            // Fallback to class constant if available
            if (defined('\Livewire\Livewire::VERSION')) {
                return \Livewire\Livewire::VERSION;
            }
        }
        
        return null;
    }

    /**
     * Discover Livewire components in the application.
     *
     * @return array
     */
    protected function discoverLivewireComponents(): array
    {
        $appPath = app_path();
        $componentNamespace = app()->getNamespace();
        $componentPaths = [];
        
        // Check common locations for Livewire components
        $possiblePaths = [
            $appPath . '/Http/Livewire',
            $appPath . '/Livewire',
            $appPath . '/Components',
        ];
        
        foreach ($possiblePaths as $path) {
            if (is_dir($path)) {
                $componentPaths[] = $path;
            }
        }
        
        $components = [];
        
        foreach ($componentPaths as $path) {
            $components = array_merge(
                $components,
                $this->scanDirectoryForComponents($path, $componentNamespace)
            );
        }
        
        return $components;
    }

    /**
     * Scan a directory for Livewire components.
     *
     * @param string $path
     * @param string $namespace
     * @return array
     */
    protected function scanDirectoryForComponents(string $path, string $namespace): array
    {
        if (!is_dir($path)) {
            return [];
        }
        
        $components = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path)
        );
        
        $appPath = app_path();
        
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = str_replace($appPath, '', $file->getPathname());
                $relativePath = trim(str_replace('/', '\\', $relativePath), '\\');
                $className = $namespace . substr($relativePath, 0, -4); // Remove .php
                
                if (class_exists($className)) {
                    $reflection = new \ReflectionClass($className);
                    // Check if it's a Livewire component
                    if ($reflection->isSubclassOf('\Livewire\Component')) {
                        $components[] = $className;
                    }
                }
            }
        }
        
        return $components;
    }

    /**
     * Get the component name from a Livewire class name.
     *
     * @param string $className
     * @return string
     */
    protected function getLivewireComponentName(string $className): string
    {
        // If Livewire is available, try to get the actual component name
        if (class_exists('\Livewire\Livewire')) {
            try {
                $componentClass = app($className);
                $name = method_exists($componentClass, 'getName')
                    ? $componentClass->getName()
                    : null;
                
                if ($name) {
                    return $name;
                }
            } catch (\Exception $e) {
                // Fall back to the simple method below
            }
        }
        
        // Simple fallback: convert class name to kebab case
        $baseName = class_basename($className);
        return Str::kebab($baseName);
    }

    /**
     * Collect route information.
     *
     * @return array
     */
    protected function collectRouteInfo(): array
    {
        $routes = Route::getRoutes();
        $routeInfo = [];
        
        foreach ($routes as $route) {
            $routeInfo[] = [
                'method' => implode('|', $route->methods()),
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'action' => $route->getActionName(),
                'middleware' => $route->middleware(),
            ];
        }
        
        return $routeInfo;
    }

    /**
     * Collect error information from logs.
     *
     * @param int $limit
     * @return array
     */
    protected function collectErrorInfo(int $limit = 10): array
    {
        $logPath = storage_path('logs');
        $errors = [];
        
        if (!is_dir($logPath)) {
            return ['error' => 'Log directory not found'];
        }
        
        // Get the most recent log file
        $logFiles = glob($logPath . '/laravel-*.log');
        if (empty($logFiles)) {
            return ['error' => 'No log files found'];
        }
        
        // Use the most recent log file
        usort($logFiles, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $latestLogFile = $logFiles[0];
        
        // Extract recent errors
        try {
            $logContent = file_get_contents($latestLogFile);
            $pattern = '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] (\w+)\.(\w+): (.*?)(\n\[|$)/s';
            
            if (preg_match_all($pattern, $logContent, $matches, PREG_SET_ORDER)) {
                $count = 0;
                foreach ($matches as $match) {
                    if ($count >= $limit) break;
                    
                    if (strtolower($match[2]) === 'error') {
                        $errors[] = [
                            'level' => $match[1] . '.' . $match[2],
                            'message' => trim($match[3]),
                            'time' => $this->extractTimeFromLogMatch($match[0]),
                        ];
                        $count++;
                    }
                }
            }
        } catch (\Exception $e) {
            return ['error' => 'Failed to parse log file: ' . $e->getMessage()];
        }
        
        return $errors;
    }

    /**
     * Extract timestamp from a log entry.
     *
     * @param string $logEntry
     * @return string|null
     */
    protected function extractTimeFromLogMatch(string $logEntry): ?string
    {
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $logEntry, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Collect optimization suggestions.
     *
     * @return array
     */
    protected function collectOptimizationSuggestions(): array
    {
        $suggestions = [];
        
        // Check for production environment with debug mode enabled
        if (App::environment('production') && config('app.debug')) {
            $suggestions[] = [
                'type' => 'security',
                'severity' => 'high',
                'message' => 'Debug mode is enabled in production. This can expose sensitive information.',
                'suggestion' => 'Set APP_DEBUG=false in your production environment.',
            ];
        }
        
        // Check for missing cache configuration
        if (config('cache.default') === 'file') {
            $suggestions[] = [
                'type' => 'performance',
                'severity' => 'medium',
                'message' => 'Using file cache driver which may not be optimal for production.',
                'suggestion' => 'Consider using Redis or Memcached for better performance.',
            ];
        }
        
        // Check for queue driver
        if (config('queue.default') === 'sync') {
            $suggestions[] = [
                'type' => 'performance',
                'severity' => 'medium',
                'message' => 'Using synchronous queue driver which blocks request processing.',
                'suggestion' => 'Consider using Redis, Database, or Amazon SQS for better performance.',
            ];
        }
        
        // Database indexing suggestions
        try {
            // This would be much more complex in a real implementation
            // We'd need to analyze query patterns to make intelligent suggestions
            $suggestions[] = [
                'type' => 'performance',
                'severity' => 'info',
                'message' => 'Database index analysis is available in the full version.',
                'suggestion' => 'Refer to the documentation for instructions on running a full database analysis.',
            ];
        } catch (\Exception $e) {
            // Ignore any errors
        }
        
        return $suggestions;
    }
}
