<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\Storage;

use Illuminate\Filesystem\Filesystem;
use ThinkNeverland\Tapped\Contracts\StorageManager;

class FileStorage implements StorageManager
{
    /**
     * @var string The directory where data will be stored
     */
    protected string $storagePath;

    /**
     * @var Filesystem The filesystem instance
     */
    protected Filesystem $files;

    /**
     * FileStorage constructor.
     */
    public function __construct(string $storagePath)
    {
        $this->storagePath = $storagePath;
        $this->files = new Filesystem();
        
        // Ensure the storage directory exists
        if (!$this->files->isDirectory($this->storagePath)) {
            $this->files->makeDirectory($this->storagePath, 0755, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function store(string $requestId, array $data): bool
    {
        $path = $this->getFilePath($requestId);
        
        // Store the data as JSON
        $bytesWritten = $this->files->put($path, json_encode($data, JSON_PRETTY_PRINT));
        
        // Convert to boolean (true if any bytes were written)
        return $bytesWritten > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function retrieve(string $requestId): ?array
    {
        $path = $this->getFilePath($requestId);
        
        if (!$this->files->exists($path)) {
            return null;
        }
        
        $content = $this->files->get($path);
        $data = json_decode($content, true);
        
        return is_array($data) ? $data : null;
    }

    /**
     * {@inheritdoc}
     */
    public function list(?int $limit = null, int $offset = 0): array
    {
        // Get all JSON files in the storage directory
        $pattern = $this->storagePath . '/*.json';
        $files = $this->files->glob($pattern);
        
        // Sort files by modification time (newest first)
        usort($files, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });
        
        // Extract request IDs from file paths
        $requestIds = array_map(function ($path) {
            return pathinfo($path, PATHINFO_FILENAME);
        }, $files);
        
        // Apply offset and limit
        $requestIds = array_slice($requestIds, $offset, $limit);
        
        return $requestIds;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $requestId): bool
    {
        $path = $this->getFilePath($requestId);
        
        if (!$this->files->exists($path)) {
            return false;
        }
        
        return $this->files->delete($path);
    }

    /**
     * {@inheritdoc}
     */
    public function cleanup(int $lifetime): int
    {
        if ($lifetime <= 0) {
            return 0;
        }
        
        $pattern = $this->storagePath . '/*.json';
        $files = $this->files->glob($pattern);
        $count = 0;
        
        $cutoff = time() - ($lifetime * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                $this->files->delete($file);
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Get the file path for a request ID.
     */
    protected function getFilePath(string $requestId): string
    {
        return $this->storagePath . '/' . $requestId . '.json';
    }
}
