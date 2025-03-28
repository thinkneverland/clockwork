<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\Support;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;
use stdClass;
use Stringable;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Throwable;

/**
 * Handles serialization of complex PHP objects in a way that's safe for debugging.
 */
class Serializer
{
    /**
     * @var int Maximum depth for nested object serialization
     */
    protected int $maxDepth = 3;
    
    /**
     * @var int Stack size counter to prevent PHP stack overflows
     */
    protected int $stackCounter = 0;
    
    /**
     * @var int Maximum stack size to prevent stack overflow
     */
    protected int $maxStackSize = 500;

    /**
     * @var int Maximum items to serialize from collections/arrays
     */
    protected int $maxItems = 100;

    /**
     * @var bool Whether to use specialized handling for Eloquent models
     */
    protected bool $handleEloquentModels = true;
    
    /**
     * @var array<int, bool> Tracks object IDs to detect recursive references
     */
    protected array $objectsInProgress = [];
    
    /**
     * @var int Maximum memory limit in bytes before simpler serialization is used
     */
    protected int $memoryLimit = 67108864; // 64MB
    
    /**
     * @var bool Whether to use specialized handling for DateTime objects
     */
    protected bool $handleDateTimes = true;

    /**
     * @var array<string, Closure> Custom serializers for specific types
     */
    protected array $customSerializers = [];

    /**
     * Serializes a value into a debug-friendly format.
     *
     * @param mixed $value The value to serialize
     * @param int $depth The current depth (used for recursion control)
     * @param bool $resetObjectTracking Whether to reset object tracking (true for initial call)
     * @return mixed The serialized value
     */
    public function serialize($value, int $depth = 0, bool $resetObjectTracking = true)
    {
        // Check memory usage to prevent excessive memory consumption
        $memoryUsage = memory_get_usage(true);
        if ($memoryUsage > $this->memoryLimit) {
            return $this->truncated($value, 'Memory limit exceeded: ' . $this->formatBytes($memoryUsage));
        }
        
        // Reset object tracking on the initial call
        if ($resetObjectTracking) {
            $this->objectsInProgress = [];
        }
        // Increment stack counter
        $this->stackCounter++;
        
        // Prevent stack overflow
        if ($this->stackCounter > $this->maxStackSize) {
            // Reset counter and return truncated value
            $this->stackCounter = 0;
            return $this->truncated($value);
        }
        
        // Prevent excessive recursion
        if ($depth > $this->maxDepth) {
            return $this->truncated($value);
        }

        // Handle simple scalar types directly
        if (is_null($value) || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        // Handle strings
        if (is_string($value)) {
            // Truncate very long strings
            if (strlen($value) > 1000) {
                return substr($value, 0, 1000) . '... [truncated, total length: ' . strlen($value) . ']';
            }
            return $value;
        }

        // Handle arrays - recursively process each element
        if (is_array($value)) {
            return $this->serializeArray($value, $depth);
        }

        // Handle objects with circular reference detection
        if (is_object($value)) {
            // Get object ID for tracking
            $objectId = spl_object_id($value);
            
            // Check if this object is already being processed (circular reference)
            if (isset($this->objectsInProgress[$objectId])) {
                return ['__type' => 'circular_reference', 'class' => get_class($value)];
            }
            
            // Mark this object as being processed
            $this->objectsInProgress[$objectId] = true;
            
            // Serialize the object
            $result = $this->serializeObject($value, $depth);
            
            // Remove the object from the tracking list once processed
            unset($this->objectsInProgress[$objectId]);
            
            return $result;
        }

        // Handle resources
        if (is_resource($value)) {
            return '[Resource: ' . get_resource_type($value) . ']';
        }

        // Handle closures
        if ($value instanceof Closure) {
            return '[Closure]';
        }

        // Fallback
        return '[Unserializable data]';
    }

    /**
     * Format bytes to human-readable string.
     *
     * @param int $bytes The number of bytes
     * @param int $precision The number of decimal places
     * @return string The formatted string
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Serialize an array.
     *
     * @param array<mixed> $array The array to serialize
     * @param int $depth The current depth
     * @return array<mixed> The serialized array
     */
    protected function serializeArray(array $array, int $depth): array
    {
        $result = [];
        $count = 0;
        
        foreach ($array as $key => $value) {
            // Limit number of items processed
            if ($count >= $this->maxItems) {
                $result['[truncated]'] = 'Array contains ' . count($array) . ' items, showing first ' . $this->maxItems;
                break;
            }
            
            $result[$key] = $this->serialize($value, $depth + 1);
            $count++;
        }
        
        return $result;
    }

    /**
     * Serialize an object.
     *
     * @param object $object The object to serialize
     * @param int $depth The current depth
     * @return mixed The serialized object
     */
    protected function serializeObject(object $object, int $depth)
    {
        // Check for custom serializers first
        $className = get_class($object);
        
        // Special case for FluxUI components
        if (strpos($className, 'FluxUI\\') === 0 || strpos($className, 'App\\View\\Components\\Flux\\') === 0) {
            return $this->serializeFluxComponent($object, $depth);
        }
        
        if (isset($this->customSerializers[$className])) {
            return ($this->customSerializers[$className])($object, $depth, $this);
        }

        // Handle Eloquent models specially (if enabled)
        if ($this->handleEloquentModels && 
            class_exists('Illuminate\Database\Eloquent\Model') && 
            $object instanceof \Illuminate\Database\Eloquent\Model) {
            return $this->serializeEloquentModel($object, $depth);
        }

        // Handle DateTime objects specially (if enabled)
        if ($this->handleDateTimes && $object instanceof DateTimeInterface) {
            return [
                '__type' => 'datetime',
                'datetime' => $object->format(DateTimeInterface::RFC3339_EXTENDED),
                'timestamp' => $object->getTimestamp(),
            ];
        }

        // Handle collections
        if (class_exists('Illuminate\Support\Collection') && $object instanceof \Illuminate\Support\Collection) {
            return [
                '__type' => 'collection',
                'class' => get_class($object),
                'count' => $object->count(),
                'items' => $this->serialize($object->take($this->maxItems)->all(), $depth + 1, false),
            ];
        }

        // Handle objects that can be converted to arrays
        if (interface_exists('Illuminate\Contracts\Support\Arrayable') && 
            $object instanceof \Illuminate\Contracts\Support\Arrayable) {
            return [
                '__type' => 'arrayable',
                'class' => get_class($object),
                'data' => $this->serialize($object->toArray(), $depth + 1, false),
            ];
        }

        // Handle objects that can be converted to JSON
        if (interface_exists('Illuminate\Contracts\Support\Jsonable') && 
            $object instanceof \Illuminate\Contracts\Support\Jsonable) {
            return [
                '__type' => 'jsonable',
                'class' => get_class($object),
                'json' => $object->toJson(),
            ];
        }

        // Handle objects that implement JsonSerializable
        if ($object instanceof JsonSerializable) {
            return [
                '__type' => 'jsonSerializable',
                'class' => get_class($object),
                'data' => $this->serialize($object->jsonSerialize(), $depth + 1),
            ];
        }

        // Handle objects that can be converted to strings
        if ($object instanceof Stringable || method_exists($object, '__toString')) {
            $string = (string) $object;
            return [
                '__type' => 'stringable',
                'class' => get_class($object),
                'string' => strlen($string) > 1000 
                    ? substr($string, 0, 1000) . '... [truncated]' 
                    : $string,
            ];
        }

        // Handle standard objects with default property extraction
        return $this->serializeStandardObject($object, $depth);
    }

    /**
     * Serialize a standard object by extracting its properties.
     *
     * @param object $object The object to serialize
     * @param int $depth The current depth
     * @return array<string, mixed> The serialized object
     */
    protected function serializeStandardObject(object $object, int $depth): array
    {
        $className = get_class($object);
        $result = [
            '__type' => 'object',
            'class' => $className,
            'id' => spl_object_id($object)
        ];
        
        // Extract public properties
        if ($object instanceof stdClass) {
            // Handle stdClass directly
            $vars = (array) $object;
            $result['properties'] = $this->serialize($vars, $depth + 1, false);
        } else {
            // Safety check - if we're getting too deep with reflection, return a simplified version
            if ($depth > 2) {
                return [
                    '__type' => 'object',
                    'class' => $className,
                    'id' => spl_object_id($object),
                    'properties' => '[Object properties omitted for stack safety]'
                ];
            }
            
            // Check memory usage frequently during reflection to avoid memory issues
            if (memory_get_usage(true) > ($this->memoryLimit * 0.8)) {
                return $this->truncated($object, 'Memory usage critical during reflection');
            }
            
            // Extract properties using reflection
            try {
                $reflection = new ReflectionClass($object);
                $properties = [];
                
                // Get all properties (public, protected, private)
                $allProperties = $reflection->getProperties(
                    ReflectionProperty::IS_PUBLIC | 
                    ReflectionProperty::IS_PROTECTED | 
                    ReflectionProperty::IS_PRIVATE
                );
                
                // Limit the number of properties we process to avoid excessive serialization
                $propertyCount = count($allProperties);
                if ($propertyCount > $this->maxItems) {
                    $allProperties = array_slice($allProperties, 0, $this->maxItems);
                    $properties['__note'] = "Object has {$propertyCount} properties, showing first {$this->maxItems}";
                }
                
                foreach ($allProperties as $property) {
                    if ($property->isStatic()) {
                        continue;
                    }
                    
                    $property->setAccessible(true);
                    
                    try {
                        if ($property->isInitialized($object)) {
                            // Check if we can safely serialize this property
                            $propertyValue = $property->getValue($object);
                            
                            // Use object tracking for circular reference detection
                            $propertyName = $property->getName();
                            if (is_object($propertyValue)) {
                                $propObjectId = spl_object_id($propertyValue);
                                if (isset($this->objectsInProgress[$propObjectId])) {
                                    $properties[$propertyName] = [
                                        '__type' => 'circular_reference',
                                        'class' => get_class($propertyValue),
                                        'id' => $propObjectId
                                    ];
                                } else {
                                    $properties[$propertyName] = $this->serialize($propertyValue, $depth + 1, false);
                                }
                            } else {
                                $properties[$propertyName] = $this->serialize($propertyValue, $depth + 1, false);
                            }
                        } else {
                            $properties[$property->getName()] = '[Uninitialized]';
                        }
                    } catch (\Throwable $e) {
                        $properties[$property->getName()] = '[Error accessing property: ' . $e->getMessage() . ']';
                    }
                }
                
                $result['properties'] = $properties;
            } catch (\Throwable $e) {
                $result['properties'] = '[Error during reflection: ' . $e->getMessage() . ']';
                $result['error'] = true;
            }
        }
        
        return $result;
    }

    /**
     * Serialize an Eloquent model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model The model to serialize
     * @param int $depth The current depth
     * @return array<string, mixed> The serialized model
     */
    protected function serializeEloquentModel(\Illuminate\Database\Eloquent\Model $model, int $depth): array
    {
        return [
            '__type' => 'model',
            'class' => get_class($model),
            'id' => $model->getKey(),
            'attributes' => $this->serialize($model->getAttributes(), $depth + 1),
            'relations' => $this->serialize($model->getRelations(), $depth + 1),
            'exists' => $model->exists,
        ];
    }

    /**
     * Create a truncated representation of a value.
     *
     * @param mixed $value The value to truncate
     * @return string A string representation of the truncated value
     */
    /**
     * Special serialization for FluxUI components to prevent stack overflow
     * 
     * @param object $component The FluxUI component
     * @param int $depth The current depth
     * @return array The serialized representation
     */
    protected function serializeFluxComponent(object $component, int $depth): array
    {
        $className = get_class($component);
        
        // Basic component info that won't cause recursion issues
        $result = [
            '__type' => 'flux_component',
            'class' => $className,
        ];
        
        // Only attempt to get name and id if methods exist and we're not too deep
        if (method_exists($component, 'getName') && $depth < 2) {
            try {
                $result['name'] = $component->getName();
            } catch (\Throwable $e) {
                $result['name'] = '[Error getting component name]';
            }
        }
        
        if (method_exists($component, 'getId') && $depth < 2) {
            try {
                $result['id'] = $component->getId();
            } catch (\Throwable $e) {
                $result['id'] = '[Error getting component ID]';
            }
        }
        
        // Safely get attributes if available
        if (property_exists($component, 'attributes') && $depth < 2) {
            try {
                $attributes = $component->attributes;
                if (is_object($attributes) && method_exists($attributes, 'toArray')) {
                    $result['attributes'] = '[FluxUI attributes available]';
                } else {
                    $result['attributes'] = '[FluxUI attributes format unknown]';
                }
            } catch (\Throwable $e) {
                $result['attributes'] = '[Error accessing component attributes]';
            }
        }
        
        return $result;
    }
    
    /**
     * Check if an object would create a circular reference
     * 
     * @param object $object The object to check
     * @param object $parent The parent object
     * @return bool True if circular reference detected
     */
    protected function isCircularReference(object $object, object $parent): bool
    {
        // Simple check - if they're the same object
        if ($object === $parent) {
            return true;
        }
        
        // More complex checks could be added here if needed
        
        return false;
    }
    
    /**
     * Create a truncated representation of a value.
     *
     * @param mixed $value The value to truncate
     * @param string|null $reason Optional reason for truncation
     * @return string|array A string or array representation of the truncated value
     */
    protected function truncated($value, ?string $reason = null): string|array
    {
        $defaultReason = 'depth limit reached';
        $reason = $reason ?: $defaultReason;
        
        if (is_object($value)) {
            return [
                '__type' => 'truncated_object',
                'class' => get_class($value),
                'reason' => $reason,
                'id' => spl_object_id($value)
            ];
        }
        
        if (is_array($value)) {
            return [
                '__type' => 'truncated_array',
                'count' => count($value),
                'reason' => $reason
            ];
        }
        
        return '[Value of type ' . gettype($value) . ' (' . $reason . ')]';
    }

    /**
     * Register a custom serializer for a specific class.
     *
     * @param string $class The fully qualified class name
     * @param Closure $serializer Function that takes (object $value, int $depth, Serializer $serializer)
     * @return self
     */
    public function registerCustomSerializer(string $class, Closure $serializer): self
    {
        $this->customSerializers[$class] = $serializer;
        return $this;
    }

    /**
     * Set the maximum recursion depth.
     *
     * @param int $depth The maximum depth
     * @return self
     */
    public function setMaxDepth(int $depth): self
    {
        $this->maxDepth = $depth;
        return $this;
    }

    /**
     * Set the maximum number of items to serialize from collections/arrays.
     *
     * @param int $items The maximum number of items
     * @return self
     */
    public function setMaxItems(int $items): self
    {
        $this->maxItems = $items;
        return $this;
    }

    /**
     * Enable or disable special handling for Eloquent models.
     *
     * @param bool $enabled Whether to enable special handling
     * @return self
     */
    public function handleEloquentModels(bool $enabled): self
    {
        $this->handleEloquentModels = $enabled;
        return $this;
    }

    /**
     * Enable or disable special handling for DateTime objects.
     *
     * @param bool $enabled Whether to enable special handling
     * @return self
     */
    public function handleDateTimes(bool $enabled): self
    {
        $this->handleDateTimes = $enabled;
        return $this;
    }
}
