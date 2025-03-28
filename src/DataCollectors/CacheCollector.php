<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\DataCollectors;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Facades\Event;

class CacheCollector extends AbstractDataCollector
{
    /**
     * @var array<int, array<string, mixed>> Collected cache operations
     */
    protected array $operations = [];

    /**
     * @var array<string, int> Statistics counters
     */
    protected array $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0,
    ];

    /**
     * Start collecting cache operations.
     */
    public function startCollecting(): void
    {
        // Listen for cache hits
        Event::listen(CacheHit::class, function (CacheHit $event) {
            $this->recordOperation('hit', $event->key, [
                'value' => $this->safeSerialize($event->value),
                'tags' => property_exists($event, 'tags') ? $event->tags : [],
            ]);
            $this->stats['hits']++;
        });

        // Listen for cache misses
        Event::listen(CacheMissed::class, function (CacheMissed $event) {
            $this->recordOperation('miss', $event->key, [
                'tags' => property_exists($event, 'tags') ? $event->tags : [],
            ]);
            $this->stats['misses']++;
        });

        // Listen for cache writes
        Event::listen(KeyWritten::class, function (KeyWritten $event) {
            $this->recordOperation('write', $event->key, [
                'value' => $this->safeSerialize($event->value),
                'expiration' => $event->seconds,
                'tags' => property_exists($event, 'tags') ? $event->tags : [],
            ]);
            $this->stats['writes']++;
        });

        // Listen for cache deletes
        Event::listen(KeyForgotten::class, function (KeyForgotten $event) {
            $this->recordOperation('delete', $event->key, [
                'tags' => property_exists($event, 'tags') ? $event->tags : [],
            ]);
            $this->stats['deletes']++;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        return [
            'operations' => $this->operations,
            'stats' => $this->stats,
            'total_operations' => count($this->operations),
            'hit_ratio' => $this->calculateHitRatio(),
        ];
    }

    /**
     * Record a cache operation.
     *
     * @param string $type
     * @param string $key
     * @param array<string, mixed> $metadata
     */
    protected function recordOperation(string $type, string $key, array $metadata = []): void
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        // Filter out the framework internal calls
        $backtrace = array_values(array_filter($backtrace, function ($trace) {
            return isset($trace['file']) && 
                !str_contains($trace['file'], '/vendor/laravel/') && 
                !str_contains($trace['file'], '/vendor/illuminate/') &&
                !str_contains($trace['file'], '/vendor/thinkneverland/tapped/');
        }));

        $this->operations[] = array_merge([
            'type' => $type,
            'key' => $key,
            'time' => microtime(true),
            'file' => $backtrace[0]['file'] ?? null,
            'line' => $backtrace[0]['line'] ?? null,
        ], $metadata);
    }

    /**
     * Calculate the cache hit ratio.
     */
    protected function calculateHitRatio(): float
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        
        if ($total === 0) {
            return 0;
        }
        
        return round(($this->stats['hits'] / $total) * 100, 2);
    }
}
