<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\DataCollectors;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class ModelCollector extends AbstractDataCollector
{
    /**
     * @var array<int, array<string, mixed>> Collected model events
     */
    protected array $modelEvents = [];

    /**
     * @var array<string, int> Count model operations by type
     */
    protected array $operationCounts = [
        'created' => 0,
        'updated' => 0,
        'deleted' => 0,
        'restored' => 0,
    ];

    /**
     * Start collecting model events.
     */
    public function startCollecting(): void
    {
        // Listen for model created event
        Event::listen('eloquent.created:*', function ($event, $models) {
            foreach ($models as $model) {
                $this->recordModelEvent('created', $model);
            }
        });

        // Listen for model updated event
        Event::listen('eloquent.updated:*', function ($event, $models) {
            foreach ($models as $model) {
                $this->recordModelEvent('updated', $model);
            }
        });

        // Listen for model deleted event
        Event::listen('eloquent.deleted:*', function ($event, $models) {
            foreach ($models as $model) {
                $this->recordModelEvent('deleted', $model);
            }
        });

        // Listen for model restored event (for soft deletes)
        Event::listen('eloquent.restored:*', function ($event, $models) {
            foreach ($models as $model) {
                $this->recordModelEvent('restored', $model);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        return [
            'model_events' => $this->modelEvents,
            'operation_counts' => $this->operationCounts,
            'total_operations' => array_sum($this->operationCounts),
        ];
    }

    /**
     * Record a model event.
     *
     * @param string $operation Type of operation (created, updated, deleted, restored)
     * @param Model $model The Eloquent model instance
     */
    protected function recordModelEvent(string $operation, Model $model): void
    {
        // Increment the operation count
        $this->operationCounts[$operation]++;

        // Extract model attributes
        $attributes = $model->getAttributes();
        
        // If it's an update, get the original attributes to show changes
        $original = [];
        $changes = [];
        
        if ($operation === 'updated' && method_exists($model, 'getOriginal')) {
            $original = $model->getOriginal();
            $changes = $this->extractChanges($attributes, $original);
        }

        // Get model identifiers
        $modelClass = get_class($model);
        $modelName = class_basename($modelClass);
        $modelId = $model->getKey();
        
        // Compute backtrace to determine origin
        $backtrace = $this->getBacktrace();

        // Add the event to our collection
        $this->modelEvents[] = [
            'operation' => $operation,
            'model_class' => $modelClass,
            'model_name' => $modelName,
            'model_id' => $modelId,
            'attributes' => $this->sanitizeData($attributes),
            'original' => $this->sanitizeData($original),
            'changes' => $changes,
            'timestamp' => microtime(true),
            'file' => $backtrace[0]['file'] ?? null,
            'line' => $backtrace[0]['line'] ?? null,
        ];
    }

    /**
     * Extract changes between current and original attributes.
     *
     * @param array<string, mixed> $current
     * @param array<string, mixed> $original
     * @return array<string, array<string, mixed>>
     */
    protected function extractChanges(array $current, array $original): array
    {
        $changes = [];
        
        foreach ($current as $key => $value) {
            // Skip primary key and timestamp fields from change tracking
            if ($key === 'id' || in_array($key, ['created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }
            
            // Compare current and original values
            if (isset($original[$key]) && $original[$key] !== $value) {
                $changes[$key] = [
                    'from' => $original[$key],
                    'to' => $value,
                ];
            }
        }
        
        return $changes;
    }

    /**
     * Get a backtrace that excludes vendor and framework code.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getBacktrace(): array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 50);
        
        // Remove Database and internal framework code from the trace
        $backtrace = array_filter($backtrace, function ($trace) {
            if (!isset($trace['file'])) {
                return false;
            }
            
            $file = $trace['file'];
            
            // Exclude framework files
            if (Str::contains($file, [
                '/vendor/laravel/framework/',
                '/vendor/illuminate/',
                '/Database/Eloquent/',
                '/vendor/thinkneverland/tapped/',
            ])) {
                return false;
            }
            
            return true;
        });
        
        return array_values($backtrace);
    }

    /**
     * Sanitize sensitive data from model attributes.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function sanitizeData(array $data): array
    {
        $sensitiveFields = [
            'password', 'secret', 'token', 'auth', 'key', 'api_key', 'apikey',
            'pass', 'secret_key', 'private', 'credential'
        ];
        
        foreach ($data as $key => $value) {
            foreach ($sensitiveFields as $sensitiveField) {
                if (Str::contains(strtolower($key), $sensitiveField)) {
                    $data[$key] = '[REDACTED]';
                    break;
                }
            }
        }
        
        return $data;
    }
}
