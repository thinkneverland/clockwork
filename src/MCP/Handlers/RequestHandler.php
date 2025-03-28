<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\MCP\Handlers;

use Illuminate\Contracts\Container\Container;
use ThinkNeverland\Tapped\MCP\Connection;
use ThinkNeverland\Tapped\MCP\Contracts\MessageHandler;
use ThinkNeverland\Tapped\MCP\Message;
use ThinkNeverland\Tapped\Support\Tapped;

class RequestHandler implements MessageHandler
{
    /**
     * Create a new request handler instance.
     *
     * @param \Illuminate\Contracts\Container\Container $app
     * @param \ThinkNeverland\Tapped\Support\Tapped $tapped
     * 
     * @return void
     */
    public function __construct(
        protected Container $app,
        protected Tapped $tapped
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getMessageType(): string
    {
        return 'request';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandle(Message $message): bool
    {
        return $message->getType() === $this->getMessageType();
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Message $message, Connection $connection): void
    {
        $command = $message->getPayload()['command'] ?? null;
        $params = $message->getPayload()['params'] ?? [];

        if (!$command) {
            $connection->send(new Message('error', [
                'error' => 'Missing command parameter',
                'requestId' => $message->getId(),
            ]));
            return;
        }

        $response = $this->processCommand($command, $params);

        $connection->send(new Message('response', [
            'requestId' => $message->getId(),
            'command' => $command,
            'data' => $response,
        ]));
    }

    /**
     * Process a command.
     *
     * @param string $command
     * @param array<string, mixed> $params
     * 
     * @return array<string, mixed>
     */
    protected function processCommand(string $command, array $params): array
    {
        return match ($command) {
            'get_requests' => $this->getRequests($params),
            'get_request' => $this->getRequest($params),
            'get_components' => $this->getComponents($params),
            'get_component' => $this->getComponent($params),
            default => [
                'error' => "Unknown command: {$command}",
            ],
        };
    }

    /**
     * Get a list of requests.
     *
     * @param array<string, mixed> $params
     * 
     * @return array<string, mixed>
     */
    protected function getRequests(array $params): array
    {
        $limit = $params['limit'] ?? 20;
        $offset = $params['offset'] ?? 0;
        $filter = $params['filter'] ?? null;

        // This is a placeholder - would be implemented with actual storage
        return [
            'requests' => [],
            'total' => 0,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Get details for a specific request.
     *
     * @param array<string, mixed> $params
     * 
     * @return array<string, mixed>
     */
    protected function getRequest(array $params): array
    {
        $id = $params['id'] ?? null;

        if (!$id) {
            return ['error' => 'Missing request ID'];
        }

        // This is a placeholder - would be implemented with actual storage
        return [
            'request' => null,
            'notFound' => true,
        ];
    }

    /**
     * Get a list of components.
     *
     * @param array<string, mixed> $params
     * 
     * @return array<string, mixed>
     */
    protected function getComponents(array $params): array
    {
        $requestId = $params['requestId'] ?? null;

        if (!$requestId) {
            return ['error' => 'Missing request ID'];
        }

        // This is a placeholder - would be implemented with Livewire collector
        return [
            'components' => [],
            'total' => 0,
        ];
    }

    /**
     * Get details for a specific component.
     *
     * @param array<string, mixed> $params
     * 
     * @return array<string, mixed>
     */
    protected function getComponent(array $params): array
    {
        $requestId = $params['requestId'] ?? null;
        $componentId = $params['componentId'] ?? null;

        if (!$requestId) {
            return ['error' => 'Missing request ID'];
        }

        if (!$componentId) {
            return ['error' => 'Missing component ID'];
        }

        // This is a placeholder - would be implemented with Livewire collector
        return [
            'component' => null,
            'notFound' => true,
        ];
    }
}
