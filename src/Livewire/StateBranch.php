<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\Livewire;

use Illuminate\Support\Str;

/**
 * Manages alternative branches of component state history for time-travel debugging.
 */
class StateBranch
{
    /**
     * @var string The branch ID
     */
    protected string $id;
    
    /**
     * @var string The branch name
     */
    protected string $name;
    
    /**
     * @var string The component ID this branch belongs to
     */
    protected string $componentId;
    
    /**
     * @var string|null The parent branch ID
     */
    protected ?string $parentBranchId;
    
    /**
     * @var string|null The snapshot ID that created this branch
     */
    protected ?string $sourceSnapshotId;
    
    /**
     * @var array<string, mixed> The component state at this branch
     */
    protected array $state;
    
    /**
     * @var float Creation timestamp
     */
    protected float $createdAt;
    
    /**
     * @var bool Whether this is an active branch
     */
    protected bool $active;
    
    /**
     * @var array<string, array<int, array<string, mixed>>> State history for this branch
     */
    protected array $branchHistory = [];
    
    /**
     * Create a new state branch instance.
     *
     * @param string $componentId The component ID
     * @param string $name The branch name
     * @param array<string, mixed> $state The component state at branch point
     * @param string|null $parentBranchId The parent branch ID
     * @param string|null $sourceSnapshotId The snapshot ID that created this branch
     */
    public function __construct(
        string $componentId,
        string $name,
        array $state,
        ?string $parentBranchId = null,
        ?string $sourceSnapshotId = null
    ) {
        $this->id = (string) Str::uuid();
        $this->componentId = $componentId;
        $this->name = $name;
        $this->state = $state;
        $this->parentBranchId = $parentBranchId;
        $this->sourceSnapshotId = $sourceSnapshotId;
        $this->createdAt = microtime(true);
        $this->active = true;
    }
    
    /**
     * Get the branch ID.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }
    
    /**
     * Get the branch name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * Set the branch name.
     *
     * @param string $name The new name
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
    
    /**
     * Get the component ID.
     *
     * @return string
     */
    public function getComponentId(): string
    {
        return $this->componentId;
    }
    
    /**
     * Get the parent branch ID.
     *
     * @return string|null
     */
    public function getParentBranchId(): ?string
    {
        return $this->parentBranchId;
    }
    
    /**
     * Get the source snapshot ID.
     *
     * @return string|null
     */
    public function getSourceSnapshotId(): ?string
    {
        return $this->sourceSnapshotId;
    }
    
    /**
     * Get the branch state.
     *
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        return $this->state;
    }
    
    /**
     * Get the creation timestamp.
     *
     * @return float
     */
    public function getCreatedAt(): float
    {
        return $this->createdAt;
    }
    
    /**
     * Check if this is an active branch.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }
    
    /**
     * Set the active status.
     *
     * @param bool $active The active status
     * @return self
     */
    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }
    
    /**
     * Add a state change to this branch's history.
     *
     * @param string $property The property name
     * @param mixed $value The new value
     * @param string|null $trigger What triggered the change
     * @return self
     */
    public function addStateChange(string $property, $value, ?string $trigger = null): self
    {
        if (!isset($this->branchHistory[$property])) {
            $this->branchHistory[$property] = [];
        }
        
        $this->branchHistory[$property][] = [
            'value' => $value,
            'timestamp' => microtime(true),
            'trigger' => $trigger,
        ];
        
        // Update the current state
        $this->state[$property] = $value;
        
        return $this;
    }
    
    /**
     * Get the branch history.
     *
     * @param string|null $property Optional property name to filter by
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getHistory(?string $property = null): array
    {
        if ($property !== null) {
            return [$property => $this->branchHistory[$property] ?? []];
        }
        
        return $this->branchHistory;
    }
    
    /**
     * Convert the branch to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'component_id' => $this->componentId,
            'parent_branch_id' => $this->parentBranchId,
            'source_snapshot_id' => $this->sourceSnapshotId,
            'created_at' => $this->createdAt,
            'active' => $this->active,
            'state' => $this->state,
        ];
    }
}
