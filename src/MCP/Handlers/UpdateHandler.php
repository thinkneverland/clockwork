<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\MCP\Handlers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Config;
use ThinkNeverland\Tapped\MCP\Connection;
use ThinkNeverland\Tapped\MCP\Contracts\MessageHandler;
use ThinkNeverland\Tapped\MCP\Message;
use ThinkNeverland\Tapped\Support\Tapped;
use Psr\Log\LoggerInterface;

/**
 * Handles real-time updates to clients
 * 
 * This handler is responsible for managing subscriptions to specific debug
 * requests and broadcasting real-time updates to clients.
 */
class UpdateHandler implements MessageHandler
{
    /**
     * Create a new update handler instance.
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
        return 'subscribe';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandle(Message $message): bool
    {
        $type = $message->getType();
        return $type === 'subscribe' || $type === 'unsubscribe';
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Message $message, Connection $connection): void
    {
        $type = $message->getType();
        $payload = $message->getPayload();
        $requestId = $payload['requestId'] ?? null;

        if (!$requestId) {
            $connection->send(new Message('error', [
                'error' => 'Missing requestId parameter',
                'requestId' => $message->getId(),
            ]));
            return;
        }

        // Handle subscription management
        if ($type === 'subscribe') {
            $this->subscribe($connection, $requestId);
        } else {
            $this->unsubscribe($connection, $requestId);
        }

        // Send confirmation
        $connection->send(new Message($type . '_response', [
            'requestId' => $message->getId(),
            'success' => true,
            'target' => $requestId,
        ]));

        // If subscribing, immediately send current data
        if ($type === 'subscribe') {
            $this->sendInitialData($connection, $requestId);
        }
    }

    /**
     * Subscribe a connection to updates for a specific request.
     *
     * @param Connection $connection
     * @param string $requestId
     * 
     * @return void
     */
    protected function subscribe(Connection $connection, string $requestId): void
    {
        // Get or initialize subscriptions
        $subscriptions = $connection->getMetadata('subscriptions', []);
        
        // Add request to subscriptions if not already present
        if (!in_array($requestId, $subscriptions, true)) {
            $subscriptions[] = $requestId;
            $connection->setMetadata('subscriptions', $subscriptions);
            
            if ($this->logger) {
                $this->logger->info("Client {$connection->getId()} subscribed to request {$requestId}");
            }
        }
    }

    /**
     * Unsubscribe a connection from updates for a specific request.
     *
     * @param Connection $connection
     * @param string $requestId
     * 
     * @return void
     */
    protected function unsubscribe(Connection $connection, string $requestId): void
    {
        // Get subscriptions
        $subscriptions = $connection->getMetadata('subscriptions', []);
        
        // Remove request from subscriptions
        $subscriptions = array_filter($subscriptions, fn($id) => $id !== $requestId);
        $connection->setMetadata('subscriptions', $subscriptions);
        
        if ($this->logger) {
            $this->logger->info("Client {$connection->getId()} unsubscribed from request {$requestId}");
        }
    }

    /**
     * Send initial data for a subscription.
     *
     * @param Connection $connection
     * @param string $requestId
     * 
     * @return void
     */
    protected function sendInitialData(Connection $connection, string $requestId): void
    {
        // Get the request data from storage
        $data = $this->tapped->storage()->retrieve($requestId);
        
        if (!$data) {
            $connection->send(new Message('error', [
                'error' => "Request not found: {$requestId}",
                'code' => 404,
            ]));
            return;
        }
        
        // Send the data to the client
        $connection->send(new Message('update', [
            'requestId' => $requestId,
            'data' => $data,
            'initial' => true,
        ]));
    }
}
