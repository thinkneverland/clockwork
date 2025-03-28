<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\MCP\Handlers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use ThinkNeverland\Tapped\MCP\Connection;
use ThinkNeverland\Tapped\MCP\Contracts\MessageHandler;
use ThinkNeverland\Tapped\MCP\Message;
use ThinkNeverland\Tapped\Support\Tapped;
use Psr\Log\LoggerInterface;

/**
 * Handles time-travel debugging functionality
 * 
 * Allows clients to navigate through component state history
 * by accessing and applying snapshots.
 */
class TimeTravelHandler implements MessageHandler
{
    /**
     * Create a new time travel handler instance.
     *
     * @param Container $app
     * @param Tapped $tapped
     * @param LoggerInterface|null $logger
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
        return 'time_travel';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandle(Message $message): bool
    {
        $type = $message->getType();
        return $type === 'time_travel' || $type === 'list_snapshots' || $type === 'create_snapshot';
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Message $message, Connection $connection): void
    {
        $type = $message->getType();
        $payload = $message->getPayload();
        
        // Validate common parameters
        if (!isset($payload['requestId'])) {
            $connection->send(new Message('error', [
                'error' => 'Missing required parameter: requestId',
                'requestId' => $message->getId(),
            ]));
            return;
        }
        
        $requestId = $payload['requestId'];
        
        // Handle based on message type
        switch ($type) {
            case 'list_snapshots':
                $this->listSnapshots($connection, $message, $requestId, $payload);
                break;
                
            case 'create_snapshot':
                $this->createSnapshot($connection, $message, $requestId, $payload);
                break;
                
            case 'time_travel':
                $this->timeTravel($connection, $message, $requestId, $payload);
                break;
                
            default:
                $connection->send(new Message('error', [
                    'error' => "Unhandled time travel command: {$type}",
                    'requestId' => $message->getId(),
                ]));
        }
    }
    
    /**
     * List all snapshots for a request or component.
     *
     * @param Connection $connection
     * @param Message $message
     * @param string $requestId
     * @param array<string, mixed> $payload
     * 
     * @return void
     */
    protected function listSnapshots(
        Connection $connection,
        Message $message,
        string $requestId,
        array $payload
    ): void {
        $componentId = $payload['componentId'] ?? null;
        
        // Get the request data from storage
        $requestData = $this->tapped->storage()->retrieve($requestId);
        
        if (!$requestData) {
            $connection->send(new Message('error', [
                'error' => "Request not found: {$requestId}",
                'requestId' => $message->getId(),
            ]));
            return;
        }
        
        // Get all snapshots
        $snapshots = $requestData['livewire']['snapshots'] ?? [];
        
        // Filter by component if specified
        if ($componentId) {
            $snapshots = array_filter($snapshots, function ($snapshot) use ($componentId) {
                return ($snapshot['componentId'] ?? '') === $componentId;
            });
        }
        
        // Sort by timestamp (newest first)
        usort($snapshots, function ($a, $b) {
            return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
        });
        
        $connection->send(new Message('snapshots_list', [
            'requestId' => $message->getId(),
            'targetRequestId' => $requestId,
            'componentId' => $componentId,
            'snapshots' => array_values($snapshots),
            'count' => count($snapshots),
        ]));
    }
    
    /**
     * Create a new snapshot of the current state.
     *
     * @param Connection $connection
     * @param Message $message
     * @param string $requestId
     * @param array<string, mixed> $payload
     * 
     * @return void
     */
    protected function createSnapshot(
        Connection $connection,
        Message $message,
        string $requestId,
        array $payload
    ): void {
        // Validate required parameters
        if (!isset($payload['componentId'])) {
            $connection->send(new Message('error', [
                'error' => 'Missing required parameter: componentId',
                'requestId' => $message->getId(),
            ]));
            return;
        }
        
        $componentId = $payload['componentId'];
        $name = $payload['name'] ?? ('Manual snapshot: ' . date('Y-m-d H:i:s'));
        
        // Get the request data from storage
        $requestData = $this->tapped->storage()->retrieve($requestId);
        
        if (!$requestData) {
            $connection->send(new Message('error', [
                'error' => "Request not found: {$requestId}",
                'requestId' => $message->getId(),
            ]));
            return;
        }
        
        // Find the component
        $componentData = null;
        foreach ($requestData['livewire']['components'] ?? [] as $component) {
            if (($component['id'] ?? '') === $componentId) {
                $componentData = $component;
                break;
            }
        }
        
        if (!$componentData) {
            $connection->send(new Message('error', [
                'error' => "Component not found: {$componentId}",
                'requestId' => $message->getId(),
            ]));
            return;
        }
        
        // Create the snapshot
        $snapshotId = uniqid('snapshot_');
        $timestamp = time();
        
        // Get existing snapshots or initialize empty array
        $snapshots = $requestData['livewire']['snapshots'] ?? [];
        
        // Add new snapshot
        $snapshots[$snapshotId] = [
            'id' => $snapshotId,
            'componentId' => $componentId,
            'timestamp' => $timestamp,
            'state' => $componentData['data'] ?? [],
            'name' => $name,
        ];
        
        // Update snapshots in storage
        $requestData['livewire']['snapshots'] = $snapshots;
        $this->tapped->getStorage()->store($requestId, $requestData);
        
        if ($this->logger) {
            $this->logger->info("Created snapshot {$snapshotId} for component {$componentId}");
        }
        
        $connection->send(new Message('snapshot_created', [
            'requestId' => $message->getId(),
            'targetRequestId' => $requestId,
            'componentId' => $componentId,
            'snapshotId' => $snapshotId,
            'timestamp' => $timestamp,
            'name' => $name,
        ]));
    }
    
    /**
     * Apply a snapshot to restore a previous state.
     *
     * @param Connection $connection
     * @param Message $message
     * @param string $requestId
     * @param array<string, mixed> $payload
     * 
     * @return void
     */
    protected function timeTravel(
        Connection $connection,
        Message $message,
        string $requestId,
        array $payload
    ): void {
        // Validate required parameters
        if (!isset($payload['snapshotId'])) {
            $connection->send(new Message('error', [
                'error' => 'Missing required parameter: snapshotId',
                'requestId' => $message->getId(),
            ]));
            return;
        }
        
        $snapshotId = $payload['snapshotId'];
        
        // Get the request data from storage
        $requestData = $this->tapped->storage()->retrieve($requestId);
        
        if (!$requestData) {
            $connection->send(new Message('error', [
                'error' => "Request not found: {$requestId}",
                'requestId' => $message->getId(),
            ]));
            return;
        }
        
        // Find the snapshot
        $snapshots = $requestData['livewire']['snapshots'] ?? [];
        if (!isset($snapshots[$snapshotId])) {
            $connection->send(new Message('error', [
                'error' => "Snapshot not found: {$snapshotId}",
                'requestId' => $message->getId(),
            ]));
            return;
        }
        
        $snapshot = $snapshots[$snapshotId];
        $componentId = $snapshot['componentId'];
        
        // Create a snapshot of the current state before applying the time travel
        $currentSnapshotId = null;
        
        if ($payload['createCurrentSnapshot'] ?? true) {
            // Find the component
            $componentData = null;
            foreach ($requestData['livewire']['components'] ?? [] as $component) {
                if (($component['id'] ?? '') === $componentId) {
                    $componentData = $component;
                    break;
                }
            }
            
            if ($componentData) {
                $currentSnapshotId = uniqid('snapshot_');
                $snapshots[$currentSnapshotId] = [
                    'id' => $currentSnapshotId,
                    'componentId' => $componentId,
                    'timestamp' => time(),
                    'state' => $componentData['data'] ?? [],
                    'name' => 'Auto snapshot before time travel',
                ];
            }
        }
        
        // Apply the snapshot state to the component
        $applied = false;
        
        foreach ($requestData['livewire']['components'] ?? [] as $index => $component) {
            if (($component['id'] ?? '') === $componentId) {
                $requestData['livewire']['components'][$index]['data'] = $snapshot['state'];
                $applied = true;
                break;
            }
        }
        
        if (!$applied) {
            $connection->send(new Message('error', [
                'error' => "Component not found for snapshot: {$componentId}",
                'requestId' => $message->getId(),
            ]));
            return;
        }
        
        // Update snapshots if we created a new one
        if ($currentSnapshotId) {
            $requestData['livewire']['snapshots'] = $snapshots;
        }
        
        // Save the updated request data
        $this->tapped->storage()->store($requestId, $requestData);
        
        if ($this->logger) {
            $this->logger->info("Applied snapshot {$snapshotId} to component {$componentId}");
        }
        
        $connection->send(new Message('time_travel_applied', [
            'requestId' => $message->getId(),
            'targetRequestId' => $requestId,
            'componentId' => $componentId,
            'snapshotId' => $snapshotId,
            'currentSnapshotId' => $currentSnapshotId,
            'timestamp' => time(),
            'snapshotName' => $snapshot['name'] ?? null,
        ]));
    }
}
