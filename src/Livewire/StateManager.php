<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\Livewire;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ThinkNeverland\Tapped\Support\Serializer;
use ThinkNeverland\Tapped\Livewire\StateBranch;

/**
 * Manages Livewire component state history and snapshots for time-travel debugging.
 */
class StateManager
{
    /**
     * @var array<string, array<string, mixed>> The current state of all tracked components
     */
    protected array $currentStates = [];

    /**
     * @var array<string, array<string, array<int, array<string, mixed>>>> Component state history
     * Indexed by component ID, then property name, containing an array of historical values
     */
    protected array $stateHistory = [];

    /**
     * @var array<string, array<int, array<string, mixed>>> Named snapshots for time-travel
     */
    protected array $snapshots = [];
    
    /**
     * @var array<string, array<string, StateBranch>> State branches by component ID and branch ID
     */
    protected array $branches = [];
    
    /**
     * @var array<string, string> Active branch ID by component ID
     */
    protected array $activeBranches = [];

    /**
     * @var Serializer The serializer for handling complex objects
     */
    protected Serializer $serializer;

    /**
     * @var int Maximum number of historical states to keep per component property
     */
    protected int $maxHistoryPerProperty = 20;

    /**
     * @var int Maximum number of snapshots to keep per component
     */
    protected int $maxSnapshots = 50;
    
    /**
     * @var int Maximum number of branches to keep per component
     */
    protected int $maxBranches = 10;

    /**
     * StateManager constructor.
     */
    public function __construct(Serializer $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * Update a component's state and record the change in history.
     *
     * @param string $componentId The component ID
     * @param string $componentName The component class name
     * @param array<string, mixed> $newState The new component state
     * @param string|null $trigger What triggered this state update
     * @return array<string, mixed> The diff of changes
     */
    public function updateState(
        string $componentId,
        string $componentName,
        array $newState,
        ?string $trigger = null
    ): array {
        // Calculate differences between current and new state
        $diff = $this->calculateStateDiff(
            $componentId, 
            $newState
        );
        
        // Only proceed if there are actual changes
        if (!empty($diff)) {
            // Record the current time
            $timestamp = microtime(true);
            
            // Update the current state
            $this->currentStates[$componentId] = array_merge(
                $this->currentStates[$componentId] ?? [],
                [
                    'id' => $componentId,
                    'name' => $componentName,
                    'state' => $newState,
                    'last_updated' => $timestamp,
                ]
            );
            
            // Record changes in history
            foreach ($diff as $property => $value) {
                if (!isset($this->stateHistory[$componentId][$property])) {
                    $this->stateHistory[$componentId][$property] = [];
                }
                
                // Add the change to history
                $this->stateHistory[$componentId][$property][] = [
                    'value' => $value,
                    'timestamp' => $timestamp,
                    'trigger' => $trigger,
                ];
                
                // Trim history if it exceeds the max
                if (count($this->stateHistory[$componentId][$property]) > $this->maxHistoryPerProperty) {
                    array_shift($this->stateHistory[$componentId][$property]);
                }
            }
            
            // If branching is enabled, also update the active branch
            $activeBranch = $this->getActiveBranch($componentId);
            if ($activeBranch !== null) {
                $this->updateBranchState($componentId, $newState, $trigger);
            }
            
            // Automatically create a snapshot for significant state changes
            if (count($diff) >= 3 || isset($diff['id'])) {
                $this->createSnapshot($componentId, $trigger ?: 'auto', $newState);
            }
        }
        
        return $diff;
    }

    /**
     * Create a named snapshot of a component's state.
     *
     * @param string $componentId The component ID
     * @param string $name A name for this snapshot
     * @param array<string, mixed>|null $state Optional state, uses current state if null
     * @return string The snapshot ID
     */
    public function createSnapshot(
        string $componentId, 
        string $name, 
        ?array $state = null
    ): string {
        $state = $state ?? ($this->currentStates[$componentId]['state'] ?? []);
        $snapshotId = (string) Str::uuid();
        
        if (!isset($this->snapshots[$componentId])) {
            $this->snapshots[$componentId] = [];
        }
        
        // Store the snapshot
        $this->snapshots[$componentId][] = [
            'id' => $snapshotId,
            'name' => $name,
            'state' => $state,
            'timestamp' => microtime(true),
        ];
        
        // Trim snapshots if they exceed the max
        if (count($this->snapshots[$componentId]) > $this->maxSnapshots) {
            array_shift($this->snapshots[$componentId]);
        }
        
        return $snapshotId;
    }

    /**
     * Get a specific snapshot by ID.
     *
     * @param string $componentId The component ID
     * @param string $snapshotId The snapshot ID
     * @return array<string, mixed>|null The snapshot or null if not found
     */
    public function getSnapshot(string $componentId, string $snapshotId): ?array
    {
        if (!isset($this->snapshots[$componentId])) {
            return null;
        }
        
        foreach ($this->snapshots[$componentId] as $snapshot) {
            if ($snapshot['id'] === $snapshotId) {
                return $snapshot;
            }
        }
        
        return null;
    }

    /**
     * Get all snapshots for a component.
     *
     * @param string $componentId The component ID
     * @return array<int, array<string, mixed>> The snapshots
     */
    public function getSnapshots(string $componentId): array
    {
        return $this->snapshots[$componentId] ?? [];
    }

    /**
     * Get the current state of a component.
     *
     * @param string $componentId The component ID
     * @return array<string, mixed>|null The component state or null if not tracked
     */
    public function getCurrentState(string $componentId): ?array
    {
        return $this->currentStates[$componentId] ?? null;
    }

    /**
     * Get state history for a component.
     *
     * @param string $componentId The component ID
     * @param string|null $property Optional property name to filter by
     * @return array<string, array<int, array<string, mixed>>> The state history
     */
    public function getStateHistory(string $componentId, ?string $property = null): array
    {
        if (!isset($this->stateHistory[$componentId])) {
            return [];
        }
        
        if ($property !== null) {
            return [$property => $this->stateHistory[$componentId][$property] ?? []];
        }
        
        return $this->stateHistory[$componentId];
    }

    /**
     * Calculate the difference between current and new state.
     *
     * @param string $componentId The component ID
     * @param array<string, mixed> $newState The new state
     * @return array<string, mixed> The differences
     */
    protected function calculateStateDiff(string $componentId, array $newState): array
    {
        $currentState = $this->currentStates[$componentId]['state'] ?? [];
        $diff = [];
        
        // Check for new or modified properties
        foreach ($newState as $key => $value) {
            $serializedValue = $this->serializer->serialize($value);
            
            // If property is new or has changed
            if (!array_key_exists($key, $currentState) || 
                $this->serializer->serialize($currentState[$key]) !== $serializedValue) {
                $diff[$key] = $value;
            }
        }
        
        // Check for removed properties (unlikely in Livewire but included for completeness)
        foreach ($currentState as $key => $value) {
            if (!array_key_exists($key, $newState)) {
                $diff[$key] = null; // Mark as removed
            }
        }
        
        return $diff;
    }

    /**
     * Apply a snapshot to restore a previous state.
     *
     * @param string $componentId The component ID
     * @param string $snapshotId The snapshot ID to apply
     * @return array<string, mixed>|null The restored state or null if snapshot not found
     */
    public function applySnapshot(string $componentId, string $snapshotId): ?array
    {
        $snapshot = $this->getSnapshot($componentId, $snapshotId);
        
        if ($snapshot === null) {
            return null;
        }
        
        // Create a new snapshot of the current state before time-traveling
        if (isset($this->currentStates[$componentId])) {
            $this->createSnapshot(
                $componentId, 
                'Auto before time-travel to ' . $snapshot['name'],
                $this->currentStates[$componentId]['state']
            );
        }
        
        // Update the current state
        if (isset($this->currentStates[$componentId])) {
            $this->currentStates[$componentId]['state'] = $snapshot['state'];
            $this->currentStates[$componentId]['last_updated'] = microtime(true);
            $this->currentStates[$componentId]['applied_snapshot'] = $snapshotId;
        }
        
        return $snapshot['state'];
    }

    /**
     * Set the maximum number of historical states to keep per property.
     *
     * @param int $max The maximum number
     * @return self
     */
    public function setMaxHistoryPerProperty(int $max): self
    {
        $this->maxHistoryPerProperty = $max;
        return $this;
    }

    /**
     * Set the maximum number of snapshots to keep per component.
     *
     * @param int $max The maximum number
     * @return self
     */
    public function setMaxSnapshots(int $max): self
    {
        $this->maxSnapshots = $max;
        return $this;
    }
    
    /**
     * Set the maximum number of branches to keep per component.
     *
     * @param int $max The maximum number
     * @return self
     */
    public function setMaxBranches(int $max): self
    {
        $this->maxBranches = $max;
        return $this;
    }
    
    /**
     * Create a new state branch from the current state.
     *
     * @param string $componentId The component ID
     * @param string $name A name for this branch
     * @param string|null $snapshotId Optional snapshot ID to use as the source
     * @return StateBranch The created branch
     */
    public function createBranch(string $componentId, string $name, ?string $snapshotId = null): StateBranch
    {
        $currentState = $this->getCurrentState($componentId);
        
        if (!$currentState) {
            throw new \InvalidArgumentException("No current state found for component: {$componentId}");
        }
        
        // Get state - either from current state or from a specific snapshot
        $state = $currentState['state'] ?? [];
        if ($snapshotId !== null) {
            $snapshot = $this->getSnapshot($componentId, $snapshotId);
            if ($snapshot) {
                $state = $snapshot['state'] ?? $state;
            }
        }
        
        // Get parent branch ID if there's an active branch
        $parentBranchId = $this->activeBranches[$componentId] ?? null;
        
        // Create the branch
        $branch = new StateBranch(
            $componentId,
            $name,
            $state,
            $parentBranchId,
            $snapshotId
        );
        
        // Store the branch
        if (!isset($this->branches[$componentId])) {
            $this->branches[$componentId] = [];
        }
        
        $this->branches[$componentId][$branch->getId()] = $branch;
        
        // Set as active branch
        $this->activeBranches[$componentId] = $branch->getId();
        
        // Trim branches if they exceed the max
        $this->trimBranches($componentId);
        
        return $branch;
    }
    
    /**
     * Get a specific branch by ID.
     *
     * @param string $componentId The component ID
     * @param string $branchId The branch ID
     * @return StateBranch|null The branch or null if not found
     */
    public function getBranch(string $componentId, string $branchId): ?StateBranch
    {
        return $this->branches[$componentId][$branchId] ?? null;
    }
    
    /**
     * Get all branches for a component.
     *
     * @param string $componentId The component ID
     * @return array<string, StateBranch> The branches
     */
    public function getBranches(string $componentId): array
    {
        return $this->branches[$componentId] ?? [];
    }
    
    /**
     * Get the current active branch for a component.
     *
     * @param string $componentId The component ID
     * @return StateBranch|null The branch or null if not found
     */
    public function getActiveBranch(string $componentId): ?StateBranch
    {
        $activeBranchId = $this->activeBranches[$componentId] ?? null;
        
        if ($activeBranchId === null) {
            return null;
        }
        
        return $this->getBranch($componentId, $activeBranchId);
    }
    
    /**
     * Switch to a different branch.
     *
     * @param string $componentId The component ID
     * @param string $branchId The branch ID to switch to
     * @return StateBranch|null The activated branch or null if not found
     */
    public function switchBranch(string $componentId, string $branchId): ?StateBranch
    {
        $branch = $this->getBranch($componentId, $branchId);
        
        if ($branch === null) {
            return null;
        }
        
        // Create a snapshot of the current state before switching
        if (isset($this->currentStates[$componentId])) {
            $this->createSnapshot(
                $componentId, 
                'Auto before branch switch to ' . $branch->getName(),
                $this->currentStates[$componentId]['state']
            );
        }
        
        // Update the current state
        if (isset($this->currentStates[$componentId])) {
            $this->currentStates[$componentId]['state'] = $branch->getState();
            $this->currentStates[$componentId]['last_updated'] = microtime(true);
            $this->currentStates[$componentId]['active_branch'] = $branchId;
        }
        
        // Set as active branch
        $this->activeBranches[$componentId] = $branchId;
        
        // Mark the branch as active
        $branch->setActive(true);
        
        return $branch;
    }
    
    /**
     * Update a branch with changes from the current state.
     *
     * @param string $componentId The component ID
     * @param array<string, mixed> $newState The new state
     * @param string|null $trigger What triggered this update
     * @return array<string, mixed> The changes made
     */
    public function updateBranchState(string $componentId, array $newState, ?string $trigger = null): array
    {
        $branch = $this->getActiveBranch($componentId);
        
        if ($branch === null) {
            // No active branch, create one
            $branch = $this->createBranch($componentId, 'Auto created', null);
        }
        
        $currentState = $branch->getState();
        $diff = [];
        
        // Calculate differences
        foreach ($newState as $key => $value) {
            $serializedValue = $this->serializer->serialize($value);
            
            // If property is new or has changed
            if (!array_key_exists($key, $currentState) || 
                $this->serializer->serialize($currentState[$key]) !== $serializedValue) {
                $diff[$key] = $value;
                $branch->addStateChange($key, $value, $trigger);
            }
        }
        
        return $diff;
    }
    
    /**
     * Delete a branch.
     *
     * @param string $componentId The component ID
     * @param string $branchId The branch ID to delete
     * @return bool Whether the branch was deleted
     */
    public function deleteBranch(string $componentId, string $branchId): bool
    {
        if (!isset($this->branches[$componentId][$branchId])) {
            return false;
        }
        
        // If this is the active branch, we need to switch to another one
        if (($this->activeBranches[$componentId] ?? null) === $branchId) {
            // Find a different branch to switch to
            foreach ($this->branches[$componentId] as $id => $branch) {
                if ($id !== $branchId) {
                    $this->activeBranches[$componentId] = $id;
                    break;
                }
            }
            
            // If no other branches exist, remove the active branch entry
            if (($this->activeBranches[$componentId] ?? null) === $branchId) {
                unset($this->activeBranches[$componentId]);
            }
        }
        
        // Delete the branch
        unset($this->branches[$componentId][$branchId]);
        
        return true;
    }
    
    /**
     * Trim branches to maintain the maximum number allowed.
     *
     * @param string $componentId The component ID
     */
    protected function trimBranches(string $componentId): void
    {
        if (!isset($this->branches[$componentId])) {
            return;
        }
        
        $branches = $this->branches[$componentId];
        
        if (count($branches) <= $this->maxBranches) {
            return;
        }
        
        // Sort branches by creation time (oldest first)
        uasort($branches, function (StateBranch $a, StateBranch $b) {
            return $a->getCreatedAt() <=> $b->getCreatedAt();
        });
        
        // Keep only the newest branches, but never delete the active branch
        $activeBranchId = $this->activeBranches[$componentId] ?? null;
        $count = 0;
        
        foreach ($branches as $id => $branch) {
            if ($count >= $this->maxBranches - 1 && $id !== $activeBranchId) {
                unset($this->branches[$componentId][$id]);
            }
            $count++;
        }
    }
}
