<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\MCP;

use Exception;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Psr\Log\LoggerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use SplObjectStorage;
use Throwable;
use ThinkNeverland\Tapped\MCP\Contracts\MessageHandler;
use ThinkNeverland\Tapped\MCP\Handlers;

class McpServer implements MessageComponentInterface
{
    /**
     * Active connections.
     *
     * @var \SplObjectStorage<\Ratchet\ConnectionInterface, \ThinkNeverland\Tapped\MCP\Connection>
     */
    protected SplObjectStorage $connections;

    /**
     * Message handlers.
     *
     * @var \Illuminate\Support\Collection<int, \ThinkNeverland\Tapped\MCP\Contracts\MessageHandler>
     */
    protected Collection $handlers;

    /**
     * Whether authentication is required.
     *
     * @var bool
     */
    protected bool $authRequired;

    /**
     * Authentication password.
     *
     * @var string|null
     */
    protected ?string $authPassword;

    /**
     * Create a new MCP server instance.
     *
     * @param \Illuminate\Contracts\Container\Container $app
     * @param \Psr\Log\LoggerInterface|null $logger
     * 
     * @return void
     */
    public function __construct(
        protected Container $app,
        protected ?LoggerInterface $logger = null
    ) {
        $this->connections = new SplObjectStorage();
        $this->handlers = new Collection();
        
        $this->authRequired = (bool) Config::get('tapped.mcp_server.authentication.enabled', false);
        $this->authPassword = Config::get('tapped.mcp_server.authentication.password');
        
        // Register the default message handlers
        $this->registerDefaultHandlers();
    }

    /**
     * Register the default message handlers.
     *
     * @return void
     */
    protected function registerDefaultHandlers(): void
    {
        // Register the handshake handler for initial client connections
        $this->registerHandler($this->app->make(Handlers\HandshakeHandler::class));
        
        // Register handlers for data access
        $this->registerHandler($this->app->make(Handlers\RequestHandler::class));
        
        // Register the update handler for real-time data streaming
        $this->registerHandler($this->app->make(Handlers\UpdateHandler::class));
        
        // Register the state editor handler for Livewire state modifications
        $this->registerHandler($this->app->make(Handlers\StateEditorHandler::class));
        
        // Register the time travel handler for component state history
        $this->registerHandler($this->app->make(Handlers\TimeTravelHandler::class));
    }

    /**
     * Register a message handler.
     *
     * @param \ThinkNeverland\Tapped\MCP\Contracts\MessageHandler $handler
     * 
     * @return self
     */
    public function registerHandler(MessageHandler $handler): self
    {
        $this->handlers->push($handler);
        return $this;
    }

    /**
     * Handle a new connection.
     *
     * @param \Ratchet\ConnectionInterface $conn
     * 
     * @return void
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        $connection = new Connection($conn);
        $this->connections->attach($conn, $connection);
        
        $this->log("New connection: {$connection->getId()}");
        
        // If authentication is not required, mark as authenticated
        if (!$this->authRequired) {
            $connection->setAuthenticated(true);
        }
        
        // Send a welcome message
        $connection->send(new Message('welcome', [
            'server' => 'Tapped MCP Server',
            'version' => '1.0.0',
            'authRequired' => $this->authRequired,
        ]));
    }

    /**
     * Handle a message from a connection.
     *
     * @param \Ratchet\ConnectionInterface $from
     * @param string $msg
     * 
     * @return void
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        try {
            $connection = $this->getConnection($from);
            
            if (!$connection) {
                return;
            }
            
            $connection->updateActivity();
            
            try {
                $message = Message::fromJson($msg);
            } catch (Exception $e) {
                $connection->send(new Message('error', [
                    'error' => 'Invalid message format',
                    'details' => $e->getMessage(),
                ]));
                return;
            }
            
            $this->log("Received message: {$message->getType()} from {$connection->getId()}");
            
            // Handle authentication if required
            if ($this->authRequired && !$connection->isAuthenticated()) {
                if ($message->getType() !== 'auth') {
                    $connection->send(new Message('error', [
                        'error' => 'Authentication required',
                    ]));
                    return;
                }
                
                $password = $message->getPayload()['password'] ?? null;
                
                // Ensure password is not null to avoid warnings
                if ($password === null || $this->authPassword === null) {
                    $connection->send(new Message('error', [
                        'error' => 'Invalid authentication',
                    ]));
                    return;
                }
                
                // Use timing-safe comparison to prevent timing attacks
                if (!hash_equals($this->authPassword, $password)) {
                    // Log potential brute force attempts while keeping the message generic
                    $this->log("Failed authentication attempt from {$connection->getId()}", 'warning');
                    
                    // Add a small delay to further mitigate timing attacks
                    usleep(random_int(50000, 250000)); // 50-250ms delay
                    
                    $connection->send(new Message('error', [
                        'error' => 'Invalid authentication',
                    ]));
                    return;
                }
                
                $connection->setAuthenticated(true);
                $connection->send(new Message('auth_success'));
                return;
            }
            
            // Find a handler for this message type
            $handled = false;
            
            foreach ($this->handlers as $handler) {
                if ($handler->canHandle($message)) {
                    $handler->handle($message, $connection);
                    $handled = true;
                    break;
                }
            }
            
            if (!$handled) {
                $connection->send(new Message('error', [
                    'error' => "Unhandled message type: {$message->getType()}",
                ]));
            }
        } catch (Throwable $e) {
            $this->log("Error handling message: {$e->getMessage()}", 'error');
        }
    }

    /**
     * Handle a connection close.
     *
     * @param \Ratchet\ConnectionInterface $conn
     * 
     * @return void
     */
    public function onClose(ConnectionInterface $conn): void
    {
        $connection = $this->getConnection($conn);
        
        if ($connection) {
            $this->log("Connection closed: {$connection->getId()}");
        }
        
        $this->connections->detach($conn);
    }

    /**
     * Handle an error with a connection.
     *
     * @param \Ratchet\ConnectionInterface $conn
     * @param \Exception $e
     * 
     * @return void
     */
    public function onError(ConnectionInterface $conn, Exception $e): void
    {
        $connection = $this->getConnection($conn);
        $connectionId = $connection ? $connection->getId() : 'unknown';
        
        $this->log("Error on connection {$connectionId}: {$e->getMessage()}", 'error');
        
        $conn->close();
    }

    /**
     * Get a connection by its Ratchet connection interface.
     *
     * @param \Ratchet\ConnectionInterface $conn
     * 
     * @return \ThinkNeverland\Tapped\MCP\Connection|null
     */
    protected function getConnection(ConnectionInterface $conn): ?Connection
    {
        return $this->connections->contains($conn) ? $this->connections[$conn] : null;
    }

    /**
     * Broadcast a message to all authenticated connections.
     *
     * @param \ThinkNeverland\Tapped\MCP\Message $message
     * 
     * @return void
     */
    public function broadcast(Message $message): void
    {
        foreach ($this->connections as $conn) {
            $connection = $this->connections[$conn];
            
            if ($connection->isAuthenticated()) {
                $connection->send($message);
            }
        }
    }

    /**
     * Log a message.
     *
     * @param string $message
     * @param string $level
     * 
     * @return void
     */
    protected function log(string $message, string $level = 'info'): void
    {
        if ($this->logger) {
            $this->logger->{$level}($message);
        }
    }
}
