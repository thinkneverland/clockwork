<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\MCP\Handlers;

use ThinkNeverland\Tapped\MCP\Connection;
use ThinkNeverland\Tapped\MCP\Contracts\MessageHandler;
use ThinkNeverland\Tapped\MCP\Message;

/**
 * Handles initial handshake message from clients
 */
class HandshakeHandler implements MessageHandler
{
    /**
     * The message type this handler can process
     * 
     * @var string
     */
    protected string $messageType = 'handshake';

    /**
     * Handle the handshake message
     *
     * @param Message $message The incoming message
     * @param Connection $connection The client connection
     * @return void
     */
    public function handle(Message $message, Connection $connection): void
    {
        $payload = $message->getPayload();
        $clientInfo = $payload['client'] ?? [];
        
        // Store client information in the connection metadata
        $connection->setMetadata('client', $clientInfo);
        
        // Send handshake response with server capabilities
        $response = new Message('handshake_response', [
            'server' => 'Tapped MCP',
            'version' => '1.0.0',
            'protocolVersion' => '1.0',
            'capabilities' => [
                'livewire' => true,
                'modelOperations' => true,
                'timeTravel' => true,
                'n1Detection' => true,
                'stateEditing' => true,
            ],
            'features' => [
                'auth' => config('tapped.mcp_server.authentication.enabled', false),
                'compression' => false,
                'encryption' => false,
            ],
        ]);
        
        $connection->send($response);
    }

    /**
     * Get the message type this handler can process
     *
     * @return string
     */
    public function getMessageType(): string
    {
        return $this->messageType;
    }

    /**
     * Check if this handler can process the given message
     *
     * @param Message $message The message to check
     * @return bool
     */
    public function canHandle(Message $message): bool
    {
        return $message->getType() === $this->messageType;
    }
}
