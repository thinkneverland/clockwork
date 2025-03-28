<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\DataCollectors;

use Illuminate\Support\Facades\Event;
use Livewire\Component;
use Livewire\Livewire;
use ReflectionClass;
use ReflectionProperty;

class LivewireComponentCollector extends AbstractDataCollector
{
    /**
     * @var array<string, array<string, mixed>> Track component state changes
     */
    protected array $components = [];

    /**
     * @var array<int, array<string, mixed>> Track component lifecycle events
     */
    protected array $events = [];

    /**
     * @var array<string, array<int, array<string, mixed>>> Component snapshots for time-travel debugging
     */
    protected array $snapshots = [];

    /**
     * Start collecting Livewire component data.
     */
    public function startCollecting(): void
    {
        $this->registerLivewireHooks();
        $this->registerLivewireEvents();
    }

    /**
     * {@inheritdoc}
     */
    public function shouldCollect(): bool
    {
        return config('tapped.collect.livewire_components', true) && class_exists(Component::class);
    }

    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        return [
            'components' => $this->components,
            'events' => $this->events,
            'snapshots' => $this->snapshots,
            'total_components' => count($this->components),
            'total_events' => count($this->events),
        ];
    }

    /**
     * Register Livewire hooks to track component lifecycle.
     */
    protected function registerLivewireHooks(): void
    {
        // Hydrate hook - called when a component instance is created
        Livewire::hook('component.hydrate', function ($component, $request) {
            $this->captureComponentState($component, 'hydrate');
        });

        // Dehydrate hook - called when a component is serialized to be sent to the browser
        Livewire::hook('component.dehydrate', function ($component, $response) {
            $this->captureComponentState($component, 'dehydrate');
        });

        // Method calling hook - called before a method is called on a component
        Livewire::hook('component.calling', function ($component, $method, $params) {
            $componentId = $this->getComponentId($component);
            
            $this->events[] = [
                'component_id' => $componentId,
                'component_name' => get_class($component),
                'type' => 'method_call',
                'method' => $method,
                'params' => $this->safeSerialize($params),
                'timestamp' => microtime(true),
            ];
            
            // Capture a snapshot before the method changes state
            $this->captureSnapshot($component, "before-{$method}");
        });

        // Method called hook - called after a method is called on a component
        Livewire::hook('component.called', function ($component, $method, $params, $result) {
            // Capture a snapshot after the method changes state
            $this->captureSnapshot($component, "after-{$method}");
        });

        // Property updated hook - called when a property is updated
        Livewire::hook('property.updated', function ($component, $name, $value, $old) {
            $componentId = $this->getComponentId($component);
            
            $this->events[] = [
                'component_id' => $componentId,
                'component_name' => get_class($component),
                'type' => 'property_updated',
                'property' => $name,
                'old_value' => $this->safeSerialize($old),
                'new_value' => $this->safeSerialize($value),
                'timestamp' => microtime(true),
            ];
        });
    }

    /**
     * Register Livewire event listeners.
     */
    protected function registerLivewireEvents(): void
    {
        // Listen for Livewire events
        Event::listen('Livewire\Event\*', function ($eventName, $data) {
            $this->events[] = [
                'type' => 'livewire_event',
                'event' => $eventName,
                'data' => $this->safeSerialize($data),
                'timestamp' => microtime(true),
            ];
        });
    }

    /**
     * Capture a component's state at a specific lifecycle point.
     */
    protected function captureComponentState(Component $component, string $lifecycle): void
    {
        $componentId = $this->getComponentId($component);
        $componentName = get_class($component);
        
        // Get public properties
        $publicProperties = $this->getComponentProperties($component);
        
        // Get computed properties if we're in dehydrate lifecycle
        $computedProperties = ($lifecycle === 'dehydrate') ? $this->getComputedProperties($component) : [];
        
        // Store component information
        $this->components[$componentId] = [
            'id' => $componentId,
            'name' => $componentName,
            'properties' => $publicProperties,
            'computed' => $computedProperties,
            'lifecycle' => $lifecycle,
            'last_updated' => microtime(true),
        ];
        
        // Record a lifecycle event
        $this->events[] = [
            'component_id' => $componentId,
            'component_name' => $componentName,
            'type' => 'lifecycle',
            'lifecycle' => $lifecycle,
            'timestamp' => microtime(true),
        ];
    }

    /**
     * Capture a snapshot of the component state for time-travel debugging.
     */
    protected function captureSnapshot(Component $component, string $trigger): void
    {
        $componentId = $this->getComponentId($component);
        
        if (!isset($this->snapshots[$componentId])) {
            $this->snapshots[$componentId] = [];
        }
        
        $this->snapshots[$componentId][] = [
            'properties' => $this->getComponentProperties($component),
            'trigger' => $trigger,
            'timestamp' => microtime(true),
        ];
    }

    /**
     * Get a component's ID.
     */
    protected function getComponentId(Component $component): string
    {
        return $component->id;
    }

    /**
     * Get a component's public properties.
     *
     * @return array<string, mixed>
     */
    protected function getComponentProperties(Component $component): array
    {
        $properties = [];
        $reflection = new ReflectionClass($component);
        
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            
            // Skip if it's a special Livewire property
            if (in_array($name, ['id', 'listeners', 'rules', 'validationAttributes', 'dispatchBrowserEvents'])) {
                continue;
            }
            
            // Skip if it has a protected getter
            if (method_exists($component, 'isPropertyCached') && !$component->isPropertyCached($name)) {
                continue;
            }
            
            $properties[$name] = $this->safeSerialize($component->{$name});
        }
        
        return $properties;
    }

    /**
     * Get a component's computed properties.
     *
     * @return array<string, mixed>
     */
    protected function getComputedProperties(Component $component): array
    {
        $computed = [];
        
        // Check if component has the getComputedProperties method (not all versions of Livewire expose this)
        if (method_exists($component, 'getComputedProperties')) {
            foreach ($component->getComputedProperties() as $name => $value) {
                $computed[$name] = $this->safeSerialize($value);
            }
        }
        
        return $computed;
    }
}
