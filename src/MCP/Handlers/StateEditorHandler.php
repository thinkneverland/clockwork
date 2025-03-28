<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\MCP\Handlers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use ThinkNeverland\Tapped\Events\ComponentStateModified;
use ThinkNeverland\Tapped\Livewire\StateManager;
use ThinkNeverland\Tapped\Livewire\StateManipulator;
use ThinkNeverland\Tapped\MCP\Connection;
use ThinkNeverland\Tapped\MCP\Contracts\MessageHandler;
use ThinkNeverland\Tapped\MCP\Message;
use ThinkNeverland\Tapped\Support\Tapped;
use ThinkNeverland\Tapped\Support\Serializer;
use Psr\Log\LoggerInterface;

/**
 * Handles Livewire component state editing
 * 
 * Allows clients to modify Livewire component properties in real-time
 * for debugging and development purposes.
 */
class StateEditorHandler implements MessageHandler
{
    /**
     * @var StateManipulator|null Cache for state manipulator instance
     */
    protected ?StateManipulator $stateManipulator = null;
    
    /**
     * @var StateManager|null Cache for state manager instance
     */
    protected ?StateManager $stateManager = null;
    
    /**
     * Create a new state editor handler instance.
     *
     * @param Container $app The application container
     * @param Tapped $tapped The tapped service instance
     * @param LoggerInterface|null $logger The logger instance
     * 
     * @return void
     */
    public function __construct(
        protected Container $app,
        protected Tapped $tapped,
        protected ?LoggerInterface $logger = null
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getMessageType(): string
    {
        return 'edit_state';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandle(Message $message): bool
    {
        return $message->getType() === $this->getMessageType();
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Message $message, Connection $connection): void
    {
        $payload = $message->getPayload();
        
        // Validate required parameters
        if (!isset($payload['componentId'])) {
            $connection->send(new Message('error', [
                'error' => 'Missing required parameter: componentId',
                'requestId' => $message->getId(),
            ]));
            return;
        }
        
        $componentId = $payload['componentId'];
        $createSnapshot = $payload['snapshot'] ?? true;
        
        // Determine what kind of state edit we're doing
        if (isset($payload['property'], $payload['value'])) {
            // Single property update
            $property = $payload['property'];
            $value = $payload['value'];
            
            // Take a snapshot first if requested
            $snapshotId = null;
            if ($createSnapshot) {
                $snapshotId = $this->getStateManipulator()->createSnapshot(
                    $componentId, 
                    "Before property update: {$property}"
                );
            }
            
            // Update the component property
            $success = $this->getStateManipulator()->updateComponentProperty(
                $componentId, 
                $property, 
                $value
            );
            
            if (!$success) {
                $connection->send(new Message('error', [
                    'error' => "Failed to update property {$property} on component {$componentId}",
                    'requestId' => $message->getId(),
                ]));
                return;
            }
            
            // Send confirmation and updated state
            $connection->send(new Message('state_updated', [
                'requestId' => $message->getId(),
                'success' => true,
                'componentId' => $componentId,
                'property' => $property,
                'newValue' => $value,
                'timestamp' => time(),
                'snapshotId' => $snapshotId,
            ]));
        } else if (isset($payload['properties']) && is_array($payload['properties'])) {
            // Bulk property update
            $properties = $payload['properties'];
            
            // Take a snapshot first if requested
            $snapshotId = null;
            if ($createSnapshot) {
                $snapshotId = $this->getStateManipulator()->createSnapshot(
                    $componentId, 
                    "Before bulk property update"
                );
            }
            
            // Update the component properties
            $success = $this->getStateManipulator()->updateComponentState(
                $componentId, 
                $properties
            );
            
            if (!$success) {
                $connection->send(new Message('error', [
                    'error' => "Failed to update properties on component {$componentId}",
                    'requestId' => $message->getId(),
                ]));
                return;
            }
            
            // Send confirmation and updated state
            $connection->send(new Message('state_updated', [
                'requestId' => $message->getId(),
                'success' => true,
                'componentId' => $componentId,
                'bulkUpdate' => true,
                'propertyCount' => count($properties),
                'timestamp' => time(),
                'snapshotId' => $snapshotId,
            ]));
        } else {
            $connection->send(new Message('error', [
                'error' => 'Invalid state edit request: must provide either property+value or properties array',
                'requestId' => $message->getId(),
            ]));
            return;
        }
    }
    
    /**
     * Get the state manipulator instance.
     *
     * @return StateManipulator
     */
    protected function getStateManipulator(): StateManipulator
    {
        if ($this->stateManipulator === null) {
            $this->stateManipulator = $this->app->make(StateManipulator::class);
        }
        
        return $this->stateManipulator;
    }
    
    /**
     * Get the state manager instance.
     *
     * @return StateManager
     */
    protected function getStateManager(): StateManager
    {
        if ($this->stateManager === null) {
            $this->stateManager = $this->app->make(StateManager::class);
        }
        
        return $this->stateManager;
    }
}
