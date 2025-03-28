<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\Livewire;

use Illuminate\Support\Facades\Event;
use Livewire\Component;
use Livewire\Livewire;
use ReflectionClass;
use ReflectionProperty;
use ThinkNeverland\Tapped\Events\ComponentStateModified;
use ThinkNeverland\Tapped\Support\Serializer;

/**
 * Provides functionality to modify Livewire component states in real-time for debugging.
 */
class StateManipulator
{
    /**
     * @var StateManager The state manager instance
     */
    protected StateManager $stateManager;

    /**
     * @var Serializer The serializer instance
     */
    protected Serializer $serializer;

    /**
     * @var array<string, string> Map of component IDs to their class names
     */
    protected array $componentClassMap = [];

    /**
     * @var array<string, array<string, string>> Map of component properties to their types
     */
    protected array $propertyTypeMap = [];

    /**
     * StateManipulator constructor.
     */
    public function __construct(StateManager $stateManager, Serializer $serializer)
    {
        $this->stateManager = $stateManager;
        $this->serializer = $serializer;
        $this->registerHooks();
    }

    /**
     * Register necessary Livewire hooks.
     */
    protected function registerHooks(): void
    {
        // Hydrate hook - called when a component instance is created
        Livewire::hook('component.hydrate', function ($component, $request) {
            $this->componentClassMap[$component->getId()] = get_class($component);
            $this->cachePropertyTypes($component);
        });

        // Dehydrate hook - called when a component is serialized to be sent to the browser
        Livewire::hook('component.dehydrate', function ($component, $response) {
            $this->componentClassMap[$component->getId()] = get_class($component);
        });
    }

    /**
     * Cache property types for a component.
     *
     * @param Component $component The Livewire component
     */
    protected function cachePropertyTypes(Component $component): void
    {
        $id = $component->getId();
        $className = get_class($component);

        if (!isset($this->propertyTypeMap[$className])) {
            $this->propertyTypeMap[$className] = [];
            $reflection = new ReflectionClass($component);

            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                $name = $property->getName();
                
                // Skip if it's a special Livewire property
                if (str_starts_with($name, '_')) {
                    continue;
                }
                
                $type = $property->getType();
                $this->propertyTypeMap[$className][$name] = $type ? $type->getName() : 'mixed';
            }
        }
    }

    /**
     * Update a component property value.
     *
     * @param string $componentId The component ID
     * @param string $property The property name
     * @param mixed $value The new value
     * @return bool Whether the update was successful
     */
    public function updateComponentProperty(string $componentId, string $property, $value): bool
    {
        // Validate component ID format (prevent path traversal and injection)
        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $componentId)) {
            $this->logSecurityWarning('Invalid component ID format during state manipulation', [
                'componentId' => $componentId,
                'property' => $property,
            ]);
            return false;
        }
        
        // Validate property name (prevent arbitrary code execution)
        if (!$this->isValidPropertyName($property)) {
            $this->logSecurityWarning('Invalid property name during state manipulation', [
                'componentId' => $componentId,
                'property' => $property,
            ]);
            return false;
        }
        
        // Find the component instance in memory
        $component = $this->findComponent($componentId);
        
        if (!$component) {
            // Component not found in memory, use direct state management with validation
            $this->logStateModificationAttempt($componentId, $property, $value, 'memory_not_found');
            return $this->updateComponentState($componentId, [$property => $value]);
        }
        
        // Check if the property exists and is accessible
        if (!$this->isAccessibleProperty($component, $property)) {
            $this->logSecurityWarning('Attempt to modify inaccessible property', [
                'componentId' => $componentId,
                'component' => get_class($component),
                'property' => $property,
            ]);
            return false;
        }
        
        // Check property value constraints before applying
        if (!$this->validatePropertyValue($component, $property, $value)) {
            $this->logSecurityWarning('Property value failed validation constraints', [
                'componentId' => $componentId,
                'component' => get_class($component),
                'property' => $property,
            ]);
            return false;
        }
        
        // Component is in memory, update it directly
        try {
            // Get the proper type for the property
            $propertyType = $this->getPropertyType($component, $property);
            
            // Convert value to appropriate type if possible
            $value = $this->convertValueToType($value, $propertyType);
            
            // Log the change attempt before applying it
            $this->logStateModificationAttempt($componentId, $property, $value, 'direct');
            
            // Update the component property
            $component->{$property} = $value;
            
            // Keep our state manager in sync
            $currentState = $this->stateManager->getCurrentState($componentId);
            if ($currentState) {
                $currentState['state'][$property] = $this->serializer->serialize($value);
                $this->stateManager->updateState(
                    $componentId,
                    get_class($component),
                    $currentState['state'],
                    'manual_update'
                );
            }
            
            // Fire an event to notify the system of the change
            event(new ComponentStateModified($componentId, $property, $value));
            
            return true;
        } catch (\Throwable $e) {
            // Log the error and return false
            logger()->error("Failed to update Livewire component property: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Update multiple properties on a component.
     *
     * @param string $componentId The component ID
     * @param array<string, mixed> $propertyValues Array of property names to values
     * @return bool Whether the update was successful
     */
    public function updateComponentState(string $componentId, array $propertyValues): bool
    {
        // Find the component instance in memory
        $component = $this->findComponent($componentId);
        
        if (!$component) {
            // If the component isn't in memory, we can only update our state manager
            // which will be useful for time travel but not immediately affect the UI
            $currentState = $this->stateManager->getCurrentState($componentId);
            if (!$currentState) {
                return false;
            }
            
            $newState = array_merge($currentState['state'], $propertyValues);
            $this->stateManager->updateState(
                $componentId,
                $this->componentClassMap[$componentId] ?? 'Unknown',
                $newState,
                'manual_bulk_update'
            );
            
            // Emit an event to notify of the state change
            foreach ($propertyValues as $property => $value) {
                event(new ComponentStateModified($componentId, $property, $value));
            }
            
            return true;
        }
        
        // Update each property on the in-memory component
        try {
            $className = get_class($component);
            $updated = false;
            
            foreach ($propertyValues as $property => $value) {
                // Skip if property doesn't exist or is protected
                if (!property_exists($component, $property) || 
                    !(new ReflectionProperty($className, $property))->isPublic()) {
                    continue;
                }
                
                // Convert value to appropriate type if possible
                $value = $this->convertValueToType(
                    $value, 
                    $this->getPropertyType($component, $property)
                );
                
                // Update the property
                $component->{$property} = $value;
                $updated = true;
                
                // Emit an event to notify of the property change
                event(new ComponentStateModified($componentId, $property, $value));
            }
            
            // Keep our state manager in sync if any properties were updated
            if ($updated) {
                $currentState = [];
                foreach ((new ReflectionClass($component))->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
                    $name = $prop->getName();
                    if (!str_starts_with($name, '_')) {
                        $currentState[$name] = $this->serializer->serialize($prop->getValue($component));
                    }
                }
                
                $this->stateManager->updateState(
                    $componentId,
                    $className,
                    $currentState,
                    'manual_bulk_update'
                );
            }
            
            return $updated;
        } catch (\Throwable $e) {
            logger()->error("Failed to update Livewire component state: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Apply a snapshot to a component.
     *
     * @param string $componentId The component ID
     * @param string $snapshotId The snapshot ID
     * @return bool Whether the snapshot was applied successfully
     */
    public function applySnapshot(string $componentId, string $snapshotId): bool
    {
        // Get the snapshot from the state manager
        $state = $this->stateManager->applySnapshot($componentId, $snapshotId);
        
        if (!$state) {
            return false;
        }
        
        // Find the component instance in memory
        $component = $this->findComponent($componentId);
        
        if (!$component) {
            // Component not in memory, snapshot is only in our state manager
            // Still consider this a success as it will be used for time travel visualization
            return true;
        }
        
        // Apply the snapshot to the in-memory component
        try {
            $className = get_class($component);
            
            foreach ($state as $property => $value) {
                // Skip if property doesn't exist or is protected
                if (!property_exists($component, $property) || 
                    !(new ReflectionProperty($className, $property))->isPublic()) {
                    continue;
                }
                
                // Convert the value to the appropriate type
                $value = $this->convertValueToType(
                    $value, 
                    $this->getPropertyType($component, $property)
                );
                
                // Update the property
                $component->{$property} = $value;
            }
            
            // Emit an event to notify of the snapshot application
            event(new ComponentStateModified($componentId, '*', $state, true));
            
            return true;
        } catch (\Throwable $e) {
            logger()->error("Failed to apply snapshot to component: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Find a component instance by ID.
     *
     * @param string $componentId The component ID
     * @return Component|null The component instance or null if not found
     */
    protected function findComponent(string $componentId): ?Component
    {
        // This is a complex operation that requires internal Livewire knowledge
        // For now, we need to hook into Livewire's internal state to find components
        try {
            // Try to get the component from Livewire's store
            // This may vary depending on Livewire version
            return app('livewire')->getComponentById($componentId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get the type of a component property.
     *
     * @param Component|string $componentOrClass The component instance or class name
     * @param string $property The property name
     * @return string The property type
     */
    protected function getPropertyType($componentOrClass, string $property): string
    {
        $className = is_string($componentOrClass) ? $componentOrClass : get_class($componentOrClass);
        
        if (isset($this->propertyTypeMap[$className][$property])) {
            return $this->propertyTypeMap[$className][$property];
        }
        
        // Type not cached, try to get it from reflection
        try {
            $reflection = new ReflectionClass($className);
            $prop = $reflection->getProperty($property);
            $type = $prop->getType();
            
            $typeName = $type ? $type->getName() : 'mixed';
            $this->propertyTypeMap[$className][$property] = $typeName;
            
            return $typeName;
        } catch (\Throwable $e) {
            return 'mixed';
        }
    }

    /**
     * Convert a value to a specified type.
     *
     * @param mixed $value The value to convert
     * @param string $type The target type
     * @return mixed The converted value
     */
    protected function convertValueToType($value, string $type)
    {
        if ($value === null) {
            return null;
        }
        
        switch ($type) {
            case 'int':
            case 'integer':
                return (int) $value;
                
            case 'float':
            case 'double':
                return (float) $value;
                
            case 'bool':
            case 'boolean':
                return (bool) $value;
                
            case 'string':
                return (string) $value;
                
            case 'array':
                return is_array($value) ? $value : [$value];
                
            default:
                // For complex types, try to use the value as is
                return $value;
        }
    }

    /**
     * Create a new named snapshot of a component's current state.
     *
     * @param string $componentId The component ID
     * @param string $name The snapshot name
     * @return string|null The snapshot ID or null if failed
     */
    public function createSnapshot(string $componentId, string $name): ?string
    {
        // Get the current component state
        $state = null;
        
        // First try to get it from a live component
        $component = $this->findComponent($componentId);
        if ($component) {
            $state = [];
            $reflection = new ReflectionClass($component);
            
            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                $propName = $property->getName();
                
                // Skip internal Livewire properties
                if (str_starts_with($propName, '_')) {
                    continue;
                }
                
                $state[$propName] = $this->serializer->serialize($property->getValue($component));
            }
        } else {
            // Fall back to the state manager's record
            $currentState = $this->stateManager->getCurrentState($componentId);
            if ($currentState) {
                $state = $currentState['state'];
            }
        }
        
        if (!$state) {
            return null;
        }
        
        // Create the snapshot
        return $this->stateManager->createSnapshot($componentId, $name, $state);
    }
}
