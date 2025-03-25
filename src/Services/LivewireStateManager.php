<?php

namespace ThinkNeverland\Tapped\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Livewire\Component;

class LivewireStateManager
{
    protected const CACHE_PREFIX = 'tapped_state_';
    protected const SNAPSHOT_PREFIX = 'tapped_snapshot_';

    public function getState(?string $componentId): array
    {
        if (!$componentId) {
            return $this->getAllComponentStates();
        }

        return Cache::get(self::CACHE_PREFIX . $componentId, []);
    }

    public function updateState(?string $componentId, array $updates): void
    {
        if (!$componentId) {
            return;
        }

        $currentState = $this->getState($componentId);
        $newState = array_merge($currentState, $updates);

        Cache::put(
            self::CACHE_PREFIX . $componentId,
            $newState,
            Carbon::now()->addMinutes(60)
        );
    }

    public function createSnapshot(?string $componentId): array
    {
        if (!$componentId) {
            return [];
        }

        $state = $this->getState($componentId);
        $snapshotId = uniqid('snapshot_');

        Cache::put(
            self::SNAPSHOT_PREFIX . $componentId . '_' . $snapshotId,
            [
                'state' => $state,
                'timestamp' => Carbon::now()->toIso8601String(),
            ],
            Carbon::now()->addDay()
        );

        return [
            'id' => $snapshotId,
            'state' => $state,
            'timestamp' => Carbon::now()->toIso8601String(),
        ];
    }

    public function getSnapshot(string $componentId, string $snapshotId): ?array
    {
        return Cache::get(self::SNAPSHOT_PREFIX . $componentId . '_' . $snapshotId);
    }

    protected function getAllComponentStates(): array
    {
        $states = [];
        $keys = Cache::get('tapped_component_ids', []);

        foreach ($keys as $componentId) {
            $states[$componentId] = $this->getState($componentId);
        }

        return $states;
    }

    public function registerComponent(Component $component): void
    {
        // Get component ID - in Livewire v3 this might be accessed differently
        try {
            $reflectionProperty = new \ReflectionProperty($component, 'id');
            $reflectionProperty->setAccessible(true);
            $componentId = $reflectionProperty->getValue($component);
        } catch (\Exception $e) {
            // Fallback method - try to get id using method if it exists
            $componentId = method_exists($component, 'getId') ? $component->getId() : null;
        }

        if (!$componentId) {
            return; // Can't register without an ID
        }

        $keys = Cache::get('tapped_component_ids', []);

        if (!in_array($componentId, $keys)) {
            $keys[] = $componentId;
            Cache::put('tapped_component_ids', $keys, Carbon::now()->addDay());
        }

        // Store initial state
        if (!$this->getState($componentId)) {
            // In Livewire v3, public properties might be accessed differently
            try {
                // Try to get public properties using reflection
                $publicProperties = [];
                $reflection = new \ReflectionClass($component);
                $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

                foreach ($properties as $property) {
                    if (!$property->isStatic()) {
                        $name = $property->getName();
                        $publicProperties[$name] = $property->getValue($component);
                    }
                }

                $this->updateState($componentId, $publicProperties);
            } catch (\Exception $e) {
                // Fallback to original method if available
                if (method_exists($component, 'getPublicPropertiesDefinedBySubClass')) {
                    $this->updateState($componentId, $component->getPublicPropertiesDefinedBySubClass());
                }
            }
        }
    }
}
