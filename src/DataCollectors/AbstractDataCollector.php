<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\DataCollectors;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use ReflectionClass;
use ThinkNeverland\Tapped\Contracts\DataCollector;

abstract class AbstractDataCollector implements DataCollector
{
    /**
     * @var array<string, mixed> The collected data
     */
    protected array $data = [];
    
    /**
     * @var int Stack counter to prevent recursion overflows
     */
    protected int $stackCounter = 0;
    
    /**
     * @var int Maximum stack depth to prevent recursion overflows
     */
    protected int $maxStackDepth = 100;
    
    /**
     * @var int Maximum depth for recursive serialization
     */
    protected int $maxRecursionDepth = 3;

    /**
     * Get the unique name for this collector.
     */
    public function getName(): string
    {
        // Get the short class name without namespace
        $reflection = new ReflectionClass(static::class);
        $className = $reflection->getShortName();
        
        return Str::snake(str_replace('Collector', '', $className));
    }

    /**
     * Determine if this collector should be executed.
     */
    public function shouldCollect(): bool
    {
        return Config::get('tapped.collect.' . $this->getName(), true);
    }

    /**
     * Start data collection if this collector has starting logic.
     */
    public function startCollecting(): void
    {
        // Base implementation does nothing, override in child classes
    }

    /**
     * Stop data collection if this collector has stopping logic.
     */
    public function stopCollecting(): void
    {
        // Base implementation does nothing, override in child classes
    }

    /**
     * Safely serialize potentially complex data.
     *
     * @param mixed $data
     * @param int $depth Current recursion depth
     * @return mixed
     */
    protected function safeSerialize(mixed $data, int $depth = 0): mixed
    {
        // Increment stack counter to prevent excessive recursion
        $this->stackCounter++;
        
        // Hard limit on stack depth to prevent stack overflow
        if ($this->stackCounter > $this->maxStackDepth) {
            $this->stackCounter = 0; // Reset counter
            return '[Max stack depth exceeded]';
        }
        
        // Soft limit on recursion depth for better output
        if ($depth > $this->maxRecursionDepth) {
            return $this->truncatedValue($data);
        }
        
        if (is_string($data) || is_numeric($data) || is_bool($data) || is_null($data)) {
            return $data;
        }

        try {
            if (is_object($data)) {
                // Special handling for FluxUI components to prevent stack overflow
                $className = get_class($data);
                if (strpos($className, 'FluxUI\\') === 0 || strpos($className, 'App\\View\\Components\\Flux\\') === 0) {
                    return [
                        '__class' => $className,
                        '__id' => spl_object_id($data),
                        '__description' => '[FluxUI Component]',
                    ];
                }
                
                // For simple objects, convert to array
                if (method_exists($data, 'toArray')) {
                    try {
                        return $data->toArray();
                    } catch (\Throwable $e) {
                        // If toArray() throws, fall back to basic info
                        return [
                            '__class' => get_class($data),
                            '__id' => spl_object_id($data),
                            '__error' => 'Error in toArray() method',
                        ];
                    }
                }

                // For more complex objects, capture basic information
                return [
                    '__class' => get_class($data),
                    '__id' => spl_object_id($data),
                    '__toString' => method_exists($data, '__toString') ? (string) $data : null,
                ];
            }

            if (is_array($data)) {
                // Limit array size to prevent huge payloads
                if (count($data) > 100) {
                    $keys = array_slice(array_keys($data), 0, 100);
                    $result = [];
                    foreach ($keys as $key) {
                        $result[$key] = $this->safeSerialize($data[$key], $depth + 1);
                    }
                    $result['__truncated'] = '[' . (count($data) - 100) . ' more items not shown]';
                    return $result;
                }
                
                $result = [];
                foreach ($data as $key => $value) {
                    $result[$key] = $this->safeSerialize($value, $depth + 1);
                }
                return $result;
            }

            // For other types, use a simple string representation
            return (string) $data;
        } catch (\Throwable $e) {
            return '[Unserializable: ' . (is_object($data) ? get_class($data) : gettype($data)) . ']';
        } finally {
            // Always decrement the stack counter when exiting
            $this->stackCounter--;
        }
    }
    
    /**
     * Create a truncated representation for values that exceed max depth
     *
     * @param mixed $value
     * @return string|array
     */
    protected function truncatedValue(mixed $value): string|array
    {
        if (is_object($value)) {
            return [
                '__class' => get_class($value),
                '__id' => spl_object_id($value),
                '__truncated' => true,
                '__description' => '[Max depth reached]',
            ];
        }
        
        if (is_array($value)) {
            return ['__count' => count($value), '__truncated' => true, '__description' => '[Array: max depth reached]'];
        }
        
        return '[' . gettype($value) . ': max depth reached]';
    }
}
