<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ComponentStateModified
{
    use Dispatchable;

    /**
     * @var string The component ID
     */
    public string $componentId;

    /**
     * @var string The property name (or '*' for bulk updates)
     */
    public string $property;

    /**
     * @var mixed The new value
     */
    public $value;

    /**
     * @var bool Whether this is a snapshot restoration
     */
    public bool $isSnapshot;

    /**
     * Create a new event instance.
     *
     * @param string $componentId The component ID
     * @param string $property The property name
     * @param mixed $value The new value
     * @param bool $isSnapshot Whether this is a snapshot restoration
     */
    public function __construct(string $componentId, string $property, $value, bool $isSnapshot = false)
    {
        $this->componentId = $componentId;
        $this->property = $property;
        $this->value = $value;
        $this->isSnapshot = $isSnapshot;
    }
}
