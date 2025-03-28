<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\MCP\Contracts;

use ThinkNeverland\Tapped\MCP\Connection;
use ThinkNeverland\Tapped\MCP\Message;

interface MessageHandler
{
    /**
     * Handle an incoming message.
     *
     * @param \ThinkNeverland\Tapped\MCP\Message $message
     * @param \ThinkNeverland\Tapped\MCP\Connection $connection
     * 
     * @return void
     */
    public function handle(Message $message, Connection $connection): void;

    /**
     * Get the message type that this handler can process.
     *
     * @return string
     */
    public function getMessageType(): string;

    /**
     * Check if this handler can process the given message.
     *
     * @param \ThinkNeverland\Tapped\MCP\Message $message
     * 
     * @return bool
     */
    public function canHandle(Message $message): bool;
}
