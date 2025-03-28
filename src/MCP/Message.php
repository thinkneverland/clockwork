<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\MCP;

use ThinkNeverland\Tapped\MCP\Contracts\Message as MessageContract;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

class Message implements MessageContract
{
    /**
     * @var string
     */
    protected string $id;

    /**
     * @var string
     */
    protected string $type;

    /**
     * @var int
     */
    protected int $timestamp;

    /**
     * @var array<string, mixed>
     */
    protected array $payload;

    /**
     * Create a new message instance.
     *
     * @param string $type
     * @param array<string, mixed> $payload
     * @param string|null $id
     * @param int|null $timestamp
     * 
     * @return void
     */
    public function __construct(string $type, array $payload = [], ?string $id = null, ?int $timestamp = null)
    {
        $this->id = $id ?? Uuid::uuid4()->toString();
        $this->type = $type;
        $this->timestamp = $timestamp ?? time();
        $this->payload = $payload;
    }

    /**
     * Create a new message from an array.
     *
     * @param array<string, mixed> $data
     * 
     * @return static
     * 
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['id'], $data['type'], $data['timestamp'])) {
            throw new InvalidArgumentException('Invalid message data. Missing required fields: id, type, or timestamp.');
        }

        return new static(
            $data['type'],
            $data['payload'] ?? [],
            $data['id'],
            $data['timestamp']
        );
    }

    /**
     * Create a new message from a JSON string.
     *
     * @param string $json
     * 
     * @return static
     * 
     * @throws InvalidArgumentException
     */
    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        return static::fromArray($data);
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * {@inheritdoc}
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'timestamp' => $this->timestamp,
            'payload' => $this->payload,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
