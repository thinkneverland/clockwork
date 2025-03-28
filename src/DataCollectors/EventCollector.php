<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\DataCollectors;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

class EventCollector extends AbstractDataCollector
{
    /**
     * @var array<int, array<string, mixed>> Collected events
     */
    protected array $events = [];

    /**
     * @var array<string, bool> Event names to exclude from collection
     */
    protected array $excludedEvents = [
        'Illuminate\Log\Events\MessageLogged' => true,
        'Illuminate\Database\Events\QueryExecuted' => true,
    ];

    /**
     * Start collecting events.
     */
    public function startCollecting(): void
    {
        /** @var Dispatcher $dispatcher */
        $dispatcher = App::make(Dispatcher::class);

        $dispatcher->listen('*', function (string $eventName, array $payload) {
            $this->recordEvent($eventName, $payload);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        return [
            'events' => $this->events,
            'total_events' => count($this->events),
        ];
    }

    /**
     * Record a dispatched event.
     *
     * @param string $eventName
     * @param array<int, mixed> $payload
     */
    protected function recordEvent(string $eventName, array $payload): void
    {
        // Skip excluded events to avoid redundancy
        if (isset($this->excludedEvents[$eventName])) {
            return;
        }

        // Skip Tapped-related events to avoid infinite loops
        if (Str::startsWith($eventName, 'ThinkNeverland\Tapped')) {
            return;
        }

        // Format payload for serialization
        $formattedPayload = $this->formatPayload($payload);

        // Get backtrace to determine event origin
        $backtrace = $this->getBacktrace();

        $this->events[] = [
            'event' => $eventName,
            'payload' => $formattedPayload,
            'time' => microtime(true),
            'file' => $backtrace[0]['file'] ?? null,
            'line' => $backtrace[0]['line'] ?? null,
        ];
    }

    /**
     * Format event payload for serialization.
     *
     * @param array<int, mixed> $payload
     * @return array<int, mixed>
     */
    protected function formatPayload(array $payload): array
    {
        $formattedPayload = [];

        foreach ($payload as $index => $item) {
            // If the payload item is an event object, extract its data
            if (is_object($item) && method_exists($item, 'broadcastWith')) {
                $formattedPayload[$index] = [
                    '__class' => get_class($item),
                    'data' => $this->safeSerialize($item->broadcastWith())
                ];
            } else {
                $formattedPayload[$index] = $this->safeSerialize($item);
            }
        }

        return $formattedPayload;
    }

    /**
     * Get a filtered backtrace for the event.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getBacktrace(): array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 50);
        
        // Remove internal Laravel event code from the trace
        $backtrace = array_filter($backtrace, function ($trace) {
            if (!isset($trace['file'])) {
                return false;
            }
            
            $file = $trace['file'];
            
            // Exclude framework files
            if (Str::contains($file, [
                '/vendor/laravel/framework/',
                '/vendor/illuminate/',
                '/Illuminate/Events/',
                '/vendor/thinkneverland/tapped/',
            ])) {
                return false;
            }
            
            return true;
        });
        
        return array_values($backtrace);
    }
}
