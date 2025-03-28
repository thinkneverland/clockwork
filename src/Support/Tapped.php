<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\Support;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Collect;
use ThinkNeverland\Tapped\Contracts\DataCollector;
use ThinkNeverland\Tapped\Contracts\StorageManager;

class Tapped
{
    /**
     * The current version of Tapped.
     */
    public const VERSION = '1.0.0';

    /**
     * @var Container The application container
     */
    protected Container $app;

    /**
     * @var Collection<int, DataCollector> The data collectors
     */
    protected Collection $collectors;

    /**
     * @var array<string, mixed> The collected data
     */
    protected array $data = [];

    /**
     * @var bool Whether the collectors have been started
     */
    protected bool $started = false;

    /**
     * Tapped constructor.
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->collectors = new Collection();
    }

    /**
     * Start all data collectors.
     */
    public function startCollectors(): void
    {
        if ($this->started) {
            return;
        }

        $this->collectors = Collection::make($this->app->tagged('tapped.collectors'));

        // Initialize data with metadata
        $this->data = [
            'id' => $this->generateRequestId(),
            'version' => self::VERSION,
            'collected_at' => microtime(true),
            'php_version' => PHP_VERSION,
            'laravel_version' => $this->app->version(),
        ];

        // Start each collector
        $this->collectors->each(function (DataCollector $collector) {
            if ($collector->shouldCollect()) {
                $collector->startCollecting();
            }
        });

        $this->started = true;
    }

    /**
     * Stop all data collectors and collect data.
     */
    public function stopCollectors(): void
    {
        if (!$this->started) {
            return;
        }

        // Stop each collector and gather data
        $this->collectors->each(function (DataCollector $collector) {
            if ($collector->shouldCollect()) {
                $collector->stopCollecting();
                $this->data[$collector->getName()] = $collector->collect();
            }
        });

        // Add end time
        $this->data['collected_end'] = microtime(true);
        $this->data['execution_time'] = round($this->data['collected_end'] - $this->data['collected_at'], 4);

        $this->started = false;
    }

    /**
     * Store the collected request data.
     *
     * @return string The request ID
     */
    public function storeRequest(): string
    {
        $requestId = $this->data['id'] ?? $this->generateRequestId();
        
        /** @var StorageManager $storage */
        $storage = $this->app->make(StorageManager::class);
        $storage->store($requestId, $this->data);
        
        return $requestId;
    }

    /**
     * Generate a unique request ID.
     */
    protected function generateRequestId(): string
    {
        return str_replace('.', '', uniqid('', true));
    }

    /**
     * Get a specific data collector.
     *
     * @template T of DataCollector
     * @param class-string<T> $collectorClass
     * @return T|null
     */
    public function getCollector(string $collectorClass)
    {
        return $this->collectors->first(function ($collector) use ($collectorClass) {
            return $collector instanceof $collectorClass;
        });
    }

    /**
     * Get all data collectors.
     *
     * @return Collection<int, DataCollector>
     */
    public function getAllCollectors(): Collection
    {
        return $this->collectors;
    }

    /**
     * Get the collected data.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Retrieve a stored request by ID.
     *
     * @param string $requestId
     * @return array<string, mixed>|null
     */
    public function retrieveRequest(string $requestId): ?array
    {
        /** @var StorageManager $storage */
        $storage = $this->app->make(StorageManager::class);
        
        return $storage->retrieve($requestId);
    }

    /**
     * List all stored requests.
     *
     * @param int|null $limit
     * @param int $offset
     * @return array<string>
     */
    public function listRequests(?int $limit = null, int $offset = 0): array
    {
        /** @var StorageManager $storage */
        $storage = $this->app->make(StorageManager::class);
        
        return $storage->list($limit, $offset);
    }
    
    /**
     * Get the storage manager instance.
     *
     * @return StorageManager
     */
    public function storage(): StorageManager
    {
        return $this->app->make(StorageManager::class);
    }
}
