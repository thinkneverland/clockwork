<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\DataCollectors;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class LogCollector extends AbstractDataCollector
{
    /**
     * @var array<int, array<string, mixed>> Collected log entries
     */
    protected array $logs = [];

    /**
     * @var array<string, int> Level statistics counters
     */
    protected array $levelCounts = [];

    /**
     * Start collecting log events.
     */
    public function startCollecting(): void
    {
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->recordLog($event);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        return [
            'logs' => $this->logs,
            'level_counts' => $this->levelCounts,
            'total_logs' => count($this->logs),
        ];
    }

    /**
     * Record a log event.
     */
    protected function recordLog(MessageLogged $event): void
    {
        $level = $event->level;
        
        // Update level statistics
        if (!isset($this->levelCounts[$level])) {
            $this->levelCounts[$level] = 0;
        }
        $this->levelCounts[$level]++;
        
        // Format context for safe serialization
        $context = $this->formatContext($event->context);
        
        // Get backtrace info
        $backtrace = $this->getBacktrace();
        
        $this->logs[] = [
            'message' => $event->message,
            'level' => $level,
            'level_class' => $this->getLevelClass($level),
            'context' => $context,
            'time' => microtime(true),
            'file' => $backtrace[0]['file'] ?? null,
            'line' => $backtrace[0]['line'] ?? null,
        ];
    }

    /**
     * Format log context for safe serialization.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function formatContext(array $context): array
    {
        // Handle exception specially
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $exception = $context['exception'];
            $context['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->formatExceptionTrace($exception),
            ];
        }
        
        return $this->safeSerialize($context);
    }

    /**
     * Format exception trace to be more readable.
     *
     * @param \Throwable $exception
     * @return array<int, array<string, mixed>>
     */
    protected function formatExceptionTrace(\Throwable $exception): array
    {
        $trace = $exception->getTrace();
        $formatted = [];
        
        foreach ($trace as $item) {
            $formatted[] = [
                'file' => $item['file'] ?? '[internal function]',
                'line' => $item['line'] ?? 0,
                'function' => isset($item['class']) ? "{$item['class']}{$item['type']}{$item['function']}" : $item['function'],
            ];
        }
        
        return $formatted;
    }

    /**
     * Get a backtrace that excludes logging-related frames.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getBacktrace(): array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 50);
        
        // Filter out logging-related frames
        $backtrace = array_filter($backtrace, function ($trace) {
            if (!isset($trace['file'])) {
                return false;
            }
            
            $file = $trace['file'];
            
            // Exclude framework files
            if (Str::contains($file, [
                '/vendor/laravel/framework/',
                '/vendor/illuminate/',
                '/Illuminate/Log/',
                '/vendor/monolog/',
                '/vendor/thinkneverland/tapped/',
            ])) {
                return false;
            }
            
            return true;
        });
        
        return array_values($backtrace);
    }

    /**
     * Get the CSS class for a log level.
     */
    protected function getLevelClass(string $level): string
    {
        return match (strtolower($level)) {
            'debug' => 'info',
            'info' => 'info',
            'notice' => 'info',
            'warning' => 'warning',
            'error' => 'danger',
            'critical' => 'danger',
            'alert' => 'danger',
            'emergency' => 'danger',
            default => 'info',
        };
    }
}
