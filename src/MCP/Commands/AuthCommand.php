<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\MCP\Commands;

use Ratchet\ConnectionInterface;
use Psr\Log\LoggerInterface;
use ThinkNeverland\Tapped\Contracts\StorageManager;

/**
 * Authentication command for MCP protocol
 */
class AuthCommand extends Command
{
    /**
     * @var string The command name
     */
    protected string $command = 'auth';
    
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
     * Handle the authentication command
     *
     * @param ConnectionInterface $connection
     * @param array<string, mixed> $data
     */
    public function handle(ConnectionInterface $connection, array $data): void
    {
        // For development, we use a simple token-based authentication
        if (!isset($data['token'])) {
            $this->sendError($connection, 'Missing authentication token');
            return;
        }
        
        if ($data['token'] === $this->getAuthToken()) {
            // Store authenticated state in the connection data
            $connection->tappedData = $connection->tappedData ?? [];
            $connection->tappedData['authenticated'] = true;
            $connection->tappedData['clientId'] = $data['clientId'] ?? uniqid('client_');
            
            $this->sendResponse($connection, 'auth', [
                'status' => 'success',
                'message' => 'Authentication successful',
                'clientId' => $connection->tappedData['clientId'],
            ]);
            
            $this->logger->info("Client authenticated: {$connection->tappedData['clientId']}");
        } else {
            $this->sendError($connection, 'Invalid authentication token');
        }
    }
    
    /**
     * Get the authentication token
     * 
     * In a real application, this would be more secure
     *
     * @return string
     */
    protected function getAuthToken(): string
    {
        return env('TAPPED_AUTH_TOKEN', 'tapped_default_token');
    }
}
