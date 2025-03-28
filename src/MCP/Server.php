<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\MCP;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;
use Psr\Log\LoggerInterface;
use ThinkNeverland\Tapped\Contracts\StorageManager;
use SplObjectStorage;

/**
 * MCP Protocol WebSocket Server
 * 
 * Handles WebSocket connections for Tapped's MCP protocol communication
 * between browser extensions and the server.
 */
class Server implements MessageComponentInterface
{
    /**
     * @var SplObjectStorage<ConnectionInterface, array<string, mixed>> Collection of connected clients
     */
    protected SplObjectStorage $clients;

    /**
     * @var StorageManager The storage manager for retrieving debugging data
     */
    protected StorageManager $storage;

    /**
     * @var LoggerInterface The logger for the server
     */
    protected LoggerInterface $logger;

    /**
     * @var LoopInterface The event loop
     */
    protected LoopInterface $loop;

    /**
     * @var array<string, callable> Message handlers registered by command
     */
    protected array $handlers = [];

    /**
     * Constructor
     *
     * @param StorageManager $storage The storage manager for retrieving debugging data
     * @param LoggerInterface $logger The logger for the server
     * @param LoopInterface $loop The event loop
     */
    public function __construct(
        StorageManager $storage,
        LoggerInterface $logger,
        LoopInterface $loop
    ) {
        $this->clients = new SplObjectStorage();
        $this->storage = $storage;
        $this->logger = $logger;
        $this->loop = $loop;
        
        $this->registerDefaultHandlers();
        
        // Set up a timer to send keep-alive messages to clients
        $this->loop->addPeriodicTimer(30, function () {
            $this->sendKeepAlive();
        });
    }

    /**
     * Handle a new WebSocket connection
     *
     * @param ConnectionInterface $conn The connection
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        // Store the new connection
        $this->clients->attach($conn, [
            'id' => uniqid('client_'),
            'authenticated' => false,
            'metadata' => [],
            'subscriptions' => [],
        ]);

        $this->logger->info("New connection! ({$this->clients->count()} total)");
    }

    /**
     * Handle a WebSocket message
     *
     * @param ConnectionInterface $from The connection sending the message
     * @param string $msg The message
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['command'])) {
            $this->sendError($from, 'Invalid message format');
            return;
        }

        $command = $data['command'];
        
        // Basic authentication check
        if ($command !== 'auth' && !$this->isAuthenticated($from)) {
            $this->sendError($from, 'Not authenticated');
            return;
        }

        // Handle the command if we have a registered handler
        if (isset($this->handlers[$command])) {
            try {
                call_user_func($this->handlers[$command], $from, $data);
            } catch (\Throwable $e) {
                $this->logger->error("Error handling command '{$command}': " . $e->getMessage());
                $this->sendError($from, "Error processing command: {$e->getMessage()}");
            }
        } else {
            $this->sendError($from, "Unknown command: {$command}");
        }
    }

    /**
     * Handle a WebSocket connection closing
     *
     * @param ConnectionInterface $conn The connection
     */
    public function onClose(ConnectionInterface $conn): void
    {
        // Remove the connection
        $this->clients->detach($conn);

        $this->logger->info("Connection closed! ({$this->clients->count()} total)");
    }

    /**
     * Handle a WebSocket error
     *
     * @param ConnectionInterface $conn The connection
     * @param \Exception $e The exception
     */
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $this->logger->error("Error: {$e->getMessage()}");

        // Close the connection
        $conn->close();
    }
    
    /**
     * Register a command handler
     *
     * @param string $command The command to handle
     * @param callable $handler The handler function (fn($conn, $data) => void)
     */
    public function registerHandler(string $command, callable $handler): void
    {
        $this->handlers[$command] = $handler;
    }
    
    /**
     * Register default command handlers
     */
    protected function registerDefaultHandlers(): void
    {
        // Authentication handler
        $this->registerHandler('auth', function (ConnectionInterface $conn, array $data): void {
            // For development, we use a simple token-based authentication
            // In production, this should be more secure
            if (isset($data['token']) && $data['token'] === $this->getAuthToken()) {
                $clientData = $this->clients[$conn];
                $clientData['authenticated'] = true;
                $this->clients[$conn] = $clientData;
                
                $this->sendResponse($conn, 'auth', [
                    'status' => 'success',
                    'message' => 'Authentication successful',
                ]);
                
                $this->logger->info("Client authenticated: {$clientData['id']}");
            } else {
                $this->sendError($conn, 'Invalid authentication token');
            }
        });
        
        // Subscribe to debug data
        $this->registerHandler('subscribe', function (ConnectionInterface $conn, array $data): void {
            if (!isset($data['requestId'])) {
                $this->sendError($conn, 'Missing requestId in subscription');
                return;
            }
            
            $clientData = $this->clients[$conn];
            $clientData['subscriptions'][] = $data['requestId'];
            $this->clients[$conn] = $clientData;
            
            // Send initial data for this request
            $requestData = $this->storage->retrieve($data['requestId']);
            if ($requestData) {
                $this->sendResponse($conn, 'data', [
                    'requestId' => $data['requestId'],
                    'data' => $requestData,
                ]);
            }
            
            $this->logger->info("Client {$clientData['id']} subscribed to request {$data['requestId']}");
        });
        
        // Unsubscribe from debug data
        $this->registerHandler('unsubscribe', function (ConnectionInterface $conn, array $data): void {
            if (!isset($data['requestId'])) {
                $this->sendError($conn, 'Missing requestId in unsubscription');
                return;
            }
            
            $clientData = $this->clients[$conn];
            $clientData['subscriptions'] = array_filter(
                $clientData['subscriptions'], 
                fn($id) => $id !== $data['requestId']
            );
            $this->clients[$conn] = $clientData;
            
            $this->logger->info("Client {$clientData['id']} unsubscribed from request {$data['requestId']}");
        });
        
        // Get list of available requests
        $this->registerHandler('get_requests', function (ConnectionInterface $conn, array $data): void {
            $limit = $data['limit'] ?? 10;
            $offset = $data['offset'] ?? 0;
            
            $requests = $this->storage->list($limit, $offset);
            
            $this->sendResponse($conn, 'requests', [
                'requests' => $requests,
                'total' => $this->storage->count(),
                'limit' => $limit,
                'offset' => $offset,
            ]);
        });
        
        // Get metadata about this server
        $this->registerHandler('get_metadata', function (ConnectionInterface $conn): void {
            $this->sendResponse($conn, 'metadata', [
                'version' => '1.0.0',
                'protocolVersion' => '1.0',
                'capabilities' => [
                    'livewire' => true,
                    'timeTravel' => true,
                    'n1Detection' => true,
                    'stateEditing' => true,
                ],
            ]);
        });
    }
    
    /**
     * Check if a connection is authenticated
     *
     * @param ConnectionInterface $conn The connection
     * @return bool
     */
    protected function isAuthenticated(ConnectionInterface $conn): bool
    {
        return $this->clients[$conn]['authenticated'] ?? false;
    }
    
    /**
     * Send a response to a client
     *
     * @param ConnectionInterface $conn The connection
     * @param string $type The response type
     * @param array<string, mixed> $data The response data
     */
    protected function sendResponse(ConnectionInterface $conn, string $type, array $data): void
    {
        $response = json_encode([
            'type' => $type,
            'timestamp' => microtime(true),
            'data' => $data,
        ]);
        
        $conn->send($response);
    }
    
    /**
     * Send an error message to a client
     *
     * @param ConnectionInterface $conn The connection
     * @param string $message The error message
     * @param int $code The error code
     */
    protected function sendError(ConnectionInterface $conn, string $message, int $code = 400): void
    {
        $response = json_encode([
            'type' => 'error',
            'timestamp' => microtime(true),
            'data' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
        
        $conn->send($response);
    }
    
    /**
     * Send a keep-alive message to all clients
     */
    protected function sendKeepAlive(): void
    {
        foreach ($this->clients as $client) {
            $this->sendResponse($client, 'ping', [
                'time' => microtime(true),
            ]);
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
    
    /**
     * Broadcast an update to all subscribers of a request
     *
     * @param string $requestId The request ID
     * @param array<string, mixed> $data The updated data
     */
    public function broadcastUpdate(string $requestId, array $data): void
    {
        foreach ($this->clients as $client) {
            $clientData = $this->clients[$client];
            
            if (in_array($requestId, $clientData['subscriptions'], true)) {
                $this->sendResponse($client, 'update', [
                    'requestId' => $requestId,
                    'data' => $data,
                ]);
            }
        }
    }
}
