<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\MCP;

use Ratchet\ConnectionInterface;
use ThinkNeverland\Tapped\MCP\Contracts\Message as MessageContract;

class Connection
{
    /**
     * The unique identifier for this connection.
     *
     * @var string
     */
    protected string $id;

    /**
     * Whether the connection is authenticated.
     *
     * @var bool
     */
    protected bool $authenticated = false;

    /**
     * The time when the connection was established.
     *
     * @var int
     */
    protected int $connectedAt;

    /**
     * The time of the last activity.
     *
     * @var int
     */
    protected int $lastActivity;

    /**
     * Connection metadata.
     *
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    /**
     * Create a new connection.
     *
     * @param \Ratchet\ConnectionInterface $connection
     * @param string $id
     * 
     * @return void
     */
    public function __construct(
        protected ConnectionInterface $connection,
        ?string $id = null
    ) {
        $this->id = $id ?? uniqid('conn_');
        $this->connectedAt = time();
        $this->lastActivity = $this->connectedAt;
    }

    /**
     * Get the connection identifier.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Send a message to the connection.
     *
     * @param \ThinkNeverland\Tapped\MCP\Contracts\Message $message
     * 
     * @return void
     */
    public function send(MessageContract $message): void
    {
        $this->connection->send($message->toJson());
        $this->updateActivity();
    }

    /**
     * Close the connection.
     *
     * @return void
     */
    public function close(): void
    {
        $this->connection->close();
    }

    /**
     * Mark the connection as authenticated.
     *
     * @param bool $authenticated
     * 
     * @return self
     */
    public function setAuthenticated(bool $authenticated = true): self
    {
        $this->authenticated = $authenticated;
        return $this;
    }

    /**
     * Check if the connection is authenticated.
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    /**
     * Update the last activity timestamp.
     *
     * @return void
     */
    public function updateActivity(): void
    {
        $this->lastActivity = time();
    }

    /**
     * Get the time since the last activity.
     *
     * @return int
     */
    public function getIdleTime(): int
    {
        return time() - $this->lastActivity;
    }

    /**
     * Check if the connection has been idle for a certain period.
     *
     * @param int $seconds
     * 
     * @return bool
     */
    public function isIdleFor(int $seconds): bool
    {
        return $this->getIdleTime() >= $seconds;
    }

    /**
     * Get the underlying Ratchet connection.
     *
     * @return \Ratchet\ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Set a metadata value.
     *
     * @param string $key
     * @param mixed $value
     * 
     * @return self
     */
    public function setMetadata(string $key, $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Get a metadata value.
     *
     * @param string $key
     * @param mixed $default
     * 
     * @return mixed
     */
    public function getMetadata(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if a metadata key exists.
     *
     * @param string $key
     * 
     * @return bool
     */
    public function hasMetadata(string $key): bool
    {
        return isset($this->metadata[$key]);
    }

    /**
     * Get all metadata.
     *
     * @return array<string, mixed>
     */
    public function getAllMetadata(): array
    {
        return $this->metadata;
    }
}
