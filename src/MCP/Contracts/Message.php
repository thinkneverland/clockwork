<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\MCP\Contracts;

interface Message
{
    /**
     * Get the message ID.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Get the message type.
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Get the message timestamp.
     *
     * @return int
     */
    public function getTimestamp(): int;

    /**
     * Get the message payload.
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array;

    /**
     * Convert the message to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Convert the message to JSON.
     *
     * @return string
     */
    public function toJson(): string;
}
