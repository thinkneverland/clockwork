<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\MCP\Commands;

use Ratchet\ConnectionInterface;
use ThinkNeverland\Tapped\Contracts\StorageManager;

/**
 * Base Command class for MCP protocol
 * 
 * All MCP commands extend this class to provide a standardized interface
 * for message handling in the WebSocket server.
 */
abstract class Command
{
    /**
     * The command name that this handler responds to
     *
     * @var string
     */
    protected string $command;

    /**
     * The storage manager instance
     *
     * @var StorageManager
     */
    protected StorageManager $storage;

    /**
     * Constructor
     *
     * @param StorageManager $storage
     */
    public function __construct(StorageManager $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Get the command name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->command;
    }

    /**
     * Handle the command
     *
     * @param ConnectionInterface $connection The connection that sent the message
     * @param array<string, mixed> $data The command data
     * @return void
     */
    abstract public function handle(ConnectionInterface $connection, array $data): void;

    /**
     * Send a response back to the client
     *
     * @param ConnectionInterface $connection The connection to send to
     * @param string $type The response type
     * @param array<string, mixed> $data The response data
     * @return void
     */
    protected function sendResponse(ConnectionInterface $connection, string $type, array $data): void
    {
        $response = json_encode([
            'type' => $type,
            'timestamp' => microtime(true),
            'data' => $data,
        ]);
        
        $connection->send($response);
    }

    /**
     * Send an error message to the client
     *
     * @param ConnectionInterface $connection The connection to send to
     * @param string $message The error message
     * @param int $code The error code
     * @return void
     */
    protected function sendError(ConnectionInterface $connection, string $message, int $code = 400): void
    {
        $this->sendResponse($connection, 'error', [
            'code' => $code,
            'message' => $message,
        ]);
    }
}
