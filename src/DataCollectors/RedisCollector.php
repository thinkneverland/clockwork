<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\DataCollectors;

use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Facades\Event;

class RedisCollector extends AbstractDataCollector
{
    /**
     * @var array<int, array<string, mixed>> Collected Redis commands
     */
    protected array $commands = [];

    /**
     * @var array<string, int> Command statistics
     */
    protected array $stats = [];

    /**
     * @var float Total execution time
     */
    protected float $totalTime = 0;

    /**
     * Start collecting Redis commands.
     */
    public function startCollecting(): void
    {
        Event::listen(CommandExecuted::class, function (CommandExecuted $event) {
            $this->recordCommand($event);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        return [
            'commands' => $this->commands,
            'stats' => $this->stats,
            'total_commands' => count($this->commands),
            'total_time' => $this->totalTime,
        ];
    }

    /**
     * Record a Redis command.
     */
    protected function recordCommand(CommandExecuted $event): void
    {
        $command = strtoupper($event->command);
        $time = $event->time;
        
        // Update statistics
        if (!isset($this->stats[$command])) {
            $this->stats[$command] = 0;
        }
        $this->stats[$command]++;
        $this->totalTime += $time;
        
        // Get backtrace to determine command origin
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        // Filter out the framework internal calls
        $backtrace = array_values(array_filter($backtrace, function ($trace) {
            return isset($trace['file']) && 
                !str_contains($trace['file'], '/vendor/laravel/') && 
                !str_contains($trace['file'], '/vendor/illuminate/') &&
                !str_contains($trace['file'], '/vendor/predis/') &&
                !str_contains($trace['file'], '/vendor/thinkneverland/tapped/');
        }));

        // Store command data
        $this->commands[] = [
            'command' => $command,
            'parameters' => $this->formatParameters($event->parameters),
            'time' => $time,
            'connection' => $event->connectionName,
            'timestamp' => microtime(true),
            'file' => $backtrace[0]['file'] ?? null,
            'line' => $backtrace[0]['line'] ?? null,
        ];
    }

    /**
     * Format command parameters for display.
     *
     * @param array<int, mixed> $parameters
     * @return array<int, mixed>
     */
    protected function formatParameters(array $parameters): array
    {
        // For sensitive commands like AUTH, hide the password
        if (count($parameters) > 0 && strtoupper($parameters[0]) === 'AUTH' && isset($parameters[1])) {
            $parameters[1] = '********';
        }

        return $parameters;
    }
}
