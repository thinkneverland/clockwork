<?php

namespace ThinkNeverland\Tapped\WebSocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use ThinkNeverland\Tapped\Services\LivewireStateManager;
use ThinkNeverland\Tapped\Services\EventLogger;
use Illuminate\Support\Facades\Config;
use SplObjectStorage;

class McpServer implements MessageComponentInterface
{
    protected SplObjectStorage $clients;
    protected LivewireStateManager $stateManager;
    protected EventLogger $eventLogger;

    public function __construct(LivewireStateManager $stateManager, EventLogger $eventLogger)
    {
        $this->clients = new SplObjectStorage();
        $this->stateManager = $stateManager;
        $this->eventLogger = $eventLogger;
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        $this->log("New connection! ({$conn->resourceId})");
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode($msg, true);

        if (!$data || !isset($data['type'])) {
            return;
        }

        switch ($data['type']) {
            case 'state_request':
                $this->handleStateRequest($from, $data);
                break;
            case 'state_update':
                $this->handleStateUpdate($from, $data);
                break;
            case 'event_log':
                $this->handleEventLog($from, $data);
                break;
            case 'snapshot_request':
                $this->handleSnapshotRequest($from, $data);
                break;
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        $this->log("Connection {$conn->resourceId} has disconnected");
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $this->log("An error has occurred: {$e->getMessage()}");
        $conn->close();
    }

    protected function handleStateRequest(ConnectionInterface $from, array $data): void
    {
        $state = $this->stateManager->getState($data['component_id'] ?? null);
        $from->send(json_encode([
            'type' => 'state_response',
            'state' => $state
        ]));
    }

    protected function handleStateUpdate(ConnectionInterface $from, array $data): void
    {
        $this->stateManager->updateState(
            $data['component_id'] ?? null,
            $data['updates'] ?? []
        );

        // Broadcast state update to all connected clients
        foreach ($this->clients as $client) {
            if ($from !== $client) {
                $client->send(json_encode([
                    'type' => 'state_changed',
                    'component_id' => $data['component_id'] ?? null,
                    'updates' => $data['updates'] ?? []
                ]));
            }
        }
    }

    protected function handleEventLog(ConnectionInterface $from, array $data): void
    {
        $this->eventLogger->log(
            $data['event'] ?? '',
            $data['data'] ?? []
        );

        // Broadcast event to all connected clients
        foreach ($this->clients as $client) {
            if ($from !== $client) {
                $client->send(json_encode([
                    'type' => 'event_logged',
                    'event' => $data['event'] ?? '',
                    'data' => $data['data'] ?? []
                ]));
            }
        }
    }

    protected function handleSnapshotRequest(ConnectionInterface $from, array $data): void
    {
        $snapshot = $this->stateManager->createSnapshot($data['component_id'] ?? null);
        $from->send(json_encode([
            'type' => 'snapshot_response',
            'snapshot' => $snapshot
        ]));
    }

    protected function log(string $message): void
    {
        if (Config::get('tapped.extensive_logging')) {
            error_log("[Tapped MCP] {$message}");
        }
    }
}
