<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\Contracts;

interface StorageManager
{
    /**
     * Store data for a request.
     *
     * @param string $requestId
     * @param array<string, mixed> $data
     * @return bool
     */
    public function store(string $requestId, array $data): bool;

    /**
     * Retrieve data for a specific request.
     *
     * @param string $requestId
     * @return array<string, mixed>|null
     */
    public function retrieve(string $requestId): ?array;

    /**
     * List all available request IDs.
     *
     * @param int|null $limit
     * @param int $offset
     * @return array<string>
     */
    public function list(?int $limit = null, int $offset = 0): array;

    /**
     * Delete a specific request.
     *
     * @param string $requestId
     * @return bool
     */
    public function delete(string $requestId): bool;

    /**
     * Clean up old requests based on storage lifetime.
     *
     * @param int $lifetime Lifetime in minutes
     * @return int Number of requests cleaned up
     */
    public function cleanup(int $lifetime): int;
}
