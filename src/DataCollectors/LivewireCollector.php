<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\DataCollectors;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Livewire\Component;
use Livewire\Livewire;
use ReflectionClass;

class LivewireCollector extends AbstractDataCollector
{
    /**
     * @var array<string, array<string, mixed>> Component states
     */
    protected array $componentStates = [];

    /**
     * @var array<int, array<string, mixed>> Component lifecycle events
     */
    protected array $events = [];

    /**
     * @var array<int, array<string, mixed>> Component updates
     */
    protected array $updates = [];

    /**
     * @var array<string, array<string, mixed>> Component snapshots for time travel
     */
    protected array $snapshots = [];

    /**
     * Start collecting Livewire data.
     */
    public function startCollecting(): void
    {
        // Only collect Livewire data if Livewire is installed
        if (!class_exists(Livewire::class)) {
            return;
        }

        // Listen for component initialization
        Event::listen('livewire:mounted', function ($component, $id) {
            $this->recordComponentMount($component, $id);
        });

        // Listen for component updates
        Event::listen('livewire:updating', function ($component, $updates) {
            $this->recordComponentUpdate($component, $updates);
        });

        // Listen for component calls
        Event::listen('livewire:calling', function ($component, $method, $params) {
            $this->recordComponentMethodCall($component, $method, $params);
        });

        // Listen for component events
        Event::listen('livewire:event', function ($component, $event, $params) {
            $this->recordComponentEvent($component, $event, $params);
        });

        // Listen for component rendering
        Event::listen('livewire:rendered', function ($component) {
            $this->recordComponentRender($component);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        return [
            'components' => $this->componentStates,
            'events' => $this->events,
            'updates' => $this->updates,
            'snapshots' => $this->snapshots,
            'count' => count($this->componentStates),
        ];
    }

    /**
     * Record component mount.
     *
     * @param Component $component
     * @param string $id
     */
    protected function recordComponentMount(Component $component, string $id): void
    {
        $name = $component->getName();
        $state = $this->getComponentState($component);

        $this->componentStates[$id] = [
            'id' => $id,
            'name' => $name,
            'state' => $state,
            'rendered_at' => microtime(true),
            'file' => $this->getComponentFile($component),
            'listeners' => $this->getComponentListeners($component),
            'computed' => $this->getComponentComputed($component),
        ];

        $this->events[] = [
            'component_id' => $id,
            'component_name' => $name,
            'type' => 'mount',
            'time' => microtime(true),
        ];

        // Create a snapshot for time travel
        $this->createSnapshot($id, $state);
    }

    /**
     * Record component update.
     *
     * @param Component $component
     * @param array<string, mixed> $updates
     */
    protected function recordComponentUpdate(Component $component, array $updates): void
    {
        $id = $component->getId();
        
        if (!isset($this->componentStates[$id])) {
            return;
        }

        // Update the component state
        $oldState = $this->componentStates[$id]['state'] ?? [];
        $newState = array_merge($oldState, $updates);
        $this->componentStates[$id]['state'] = $newState;
        $this->componentStates[$id]['updated_at'] = microtime(true);

        // Record the update
        $this->updates[] = [
            'component_id' => $id,
            'component_name' => $component->getName(),
            'changes' => $updates,
            'time' => microtime(true),
        ];

        // Create a snapshot for time travel
        $this->createSnapshot($id, $newState);
    }

    /**
     * Record component method call.
     *
     * @param Component $component
     * @param string $method
     * @param array<int, mixed> $params
     */
    protected function recordComponentMethodCall(Component $component, string $method, array $params): void
    {
        $id = $component->getId();
        
        $this->events[] = [
            'component_id' => $id,
            'component_name' => $component->getName(),
            'type' => 'method',
            'method' => $method,
            'params' => $this->safeSerialize($params),
            'time' => microtime(true),
        ];
    }

    /**
     * Record component event.
     *
     * @param Component $component
     * @param string $event
     * @param array<int, mixed> $params
     */
    protected function recordComponentEvent(Component $component, string $event, array $params): void
    {
        $id = $component->getId();
        
        $this->events[] = [
            'component_id' => $id,
            'component_name' => $component->getName(),
            'type' => 'event',
            'event' => $event,
            'params' => $this->safeSerialize($params),
            'time' => microtime(true),
        ];
    }

    /**
     * Record component render.
     *
     * @param Component $component
     */
    protected function recordComponentRender(Component $component): void
    {
        $id = $component->getId();
        
        if (!isset($this->componentStates[$id])) {
            return;
        }

        $this->componentStates[$id]['rendered_at'] = microtime(true);

        $this->events[] = [
            'component_id' => $id,
            'component_name' => $component->getName(),
            'type' => 'render',
            'time' => microtime(true),
        ];
    }

    /**
     * Get component state.
     *
     * @param Component $component
     * @return array<string, mixed>
     */
    protected function getComponentState(Component $component): array
    {
        $state = [];
        $publicProperties = (new ReflectionClass($component))->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($publicProperties as $property) {
            $name = $property->getName();
            
            // Skip internal Livewire properties
            if (str_starts_with($name, '_')) {
                continue;
            }
            
            // Get the property value
            $value = $property->getValue($component);
            
            // Safe serialize the value
            $state[$name] = $this->safeSerialize($value);
        }

        return $state;
    }

    /**
     * Get component file path.
     *
     * @param Component $component
     * @return string|null
     */
    protected function getComponentFile(Component $component): ?string
    {
        $reflection = new ReflectionClass($component);
        return $reflection->getFileName();
    }

    /**
     * Get component listeners.
     *
     * @param Component $component
     * @return array<string, string>
     */
    protected function getComponentListeners(Component $component): array
    {
        // Try to access the protected $listeners property
        try {
            $reflection = new ReflectionClass($component);
            $property = $reflection->getProperty('listeners');
            $property->setAccessible(true);
            
            return $property->getValue($component);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get component computed properties.
     *
     * @param Component $component
     * @return array<string, mixed>
     */
    protected function getComponentComputed(Component $component): array
    {
        // Try to access the protected $computedPropertyCache property
        try {
            $reflection = new ReflectionClass($component);
            
            if ($reflection->hasProperty('computedPropertyCache')) {
                $property = $reflection->getProperty('computedPropertyCache');
                $property->setAccessible(true);
                
                return $this->safeSerialize($property->getValue($component) ?? []);
            }
        } catch (\Throwable $e) {
            // Silently fail
        }

        return [];
    }

    /**
     * Create a component snapshot for time travel.
     *
     * @param string $componentId
     * @param array<string, mixed> $state
     */
    protected function createSnapshot(string $componentId, array $state): void
    {
        if (!isset($this->snapshots[$componentId])) {
            $this->snapshots[$componentId] = [];
        }

        // Only keep the most recent 10 snapshots per component
        if (count($this->snapshots[$componentId]) >= 10) {
            array_shift($this->snapshots[$componentId]);
        }

        $this->snapshots[$componentId][] = [
            'state' => $state,
            'time' => microtime(true),
        ];
    }
}
