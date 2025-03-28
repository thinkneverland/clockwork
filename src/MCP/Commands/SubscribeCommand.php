<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\MCP\Commands;

use Ratchet\ConnectionInterface;
use Psr\Log\LoggerInterface;
use ThinkNeverland\Tapped\Contracts\StorageManager;

/**
 * Command to subscribe to debugging data updates for a specific request
 */
class SubscribeCommand extends Command
{
    /**
     * @var string The command name
     */
    protected string $command = 'subscribe';
    
    /**
     * @var LoggerInterface The logger
     */
    protected LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param StorageManager $storage
     * @param LoggerInterface $logger
     */
    public function __construct(StorageManager $storage, LoggerInterface $logger)
    {
        parent::__construct($storage);
        $this->logger = $logger;
    }

    /**
     * Handle the subscribe command
     *
     * @param ConnectionInterface $connection
     * @param array<string, mixed> $data
     */
    public function handle(ConnectionInterface $connection, array $data): void
    {
        if (!isset($data['requestId'])) {
            $this->sendError($connection, 'Missing requestId in subscription');
            return;
        }
        
        $requestId = $data['requestId'];
        
        // Initialize or get the connection's tapped data
        $connection->tappedData = $connection->tappedData ?? [];
        $connection->tappedData['subscriptions'] = $connection->tappedData['subscriptions'] ?? [];
        
        // Add this request to the connection's subscriptions if not already subscribed
        if (!in_array($requestId, $connection->tappedData['subscriptions'], true)) {
            $connection->tappedData['subscriptions'][] = $requestId;
        }
        
        // Send initial data for this request
        $requestData = $this->storage->retrieve($requestId);
        if ($requestData) {
            $this->sendResponse($connection, 'data', [
                'requestId' => $requestId,
                'data' => $requestData,
                'initial' => true,
            ]);
        } else {
            $this->sendError($connection, "No data found for request: {$requestId}", 404);
            return;
        }
        
        $clientId = $connection->tappedData['clientId'] ?? 'unknown';
        $this->logger->info("Client {$clientId} subscribed to request {$requestId}");
        
        // Send subscription confirmation
        $this->sendResponse($connection, 'subscribed', [
            'requestId' => $requestId,
            'success' => true,
        ]);
    }
}
