<?php

namespace ThinkNeverland\Tapped\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DebugStateSerializer
{
    /**
     * The storage disk to use for snapshots.
     */
    protected $disk;

    /**
     * The path within the disk where snapshots are stored.
     */
    protected $path;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->disk = config('tapped.storage.disk', 'local');
        $this->path = config('tapped.storage.path', 'tapped/snapshots');
    }

    /**
     * Serialize debug data to JSON format.
     *
     * @param array $data
     * @param bool $pretty
     * @return string
     */
    public function serializeToJson(array $data, bool $pretty = false): string
    {
        $options = $pretty ? JSON_PRETTY_PRINT : 0;
        return json_encode($data, $options | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Serialize debug data to binary format for efficiency.
     *
     * @param array $data
     * @return string
     */
    public function serializeToBinary(array $data): string
    {
        // Add schema version header
        $schemaVersion = 1;
        $timestamp = time();
        
        $header = [
            'schema_version' => $schemaVersion,
            'timestamp' => $timestamp,
            'format' => 'tapped-binary',
        ];
        
        // Combine header and data
        $combined = [
            'header' => $header,
            'data' => $data,
        ];
        
        // Use PHP serialization for binary format
        // In a production system, this could be replaced with a more efficient
        // binary serialization library like MessagePack
        return serialize($combined);
    }

    /**
     * Deserialize data from binary format.
     *
     * @param string $binary
     * @return array|null
     */
    public function deserializeFromBinary(string $binary): ?array
    {
        try {
            $data = unserialize($binary);
            
            // Validate schema version
            if (!isset($data['header']) || !isset($data['header']['schema_version'])) {
                return null;
            }
            
            return $data['data'];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new debug state snapshot.
     *
     * @param array $data
     * @return array
     */
    public function createSnapshot(array $data): array
    {
        $id = Str::uuid()->toString();
        $label = $data['label'] ?? 'Snapshot ' . date('Y-m-d H:i:s');
        $timestamp = $data['timestamp'] ?? time();
        
        $snapshot = [
            'id' => $id,
            'label' => $label,
            'timestamp' => $timestamp,
            'data' => $data,
        ];
        
        $json = $this->serializeToJson($snapshot);
        $filename = "{$id}.json";
        
        Storage::disk($this->disk)->put($this->getPath($filename), $json);
        
        return [
            'id' => $id,
            'label' => $label,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Get a snapshot by ID.
     *
     * @param string $id
     * @return array|null
     */
    public function getSnapshot(string $id): ?array
    {
        $filename = "{$id}.json";
        
        if (!Storage::disk($this->disk)->exists($this->getPath($filename))) {
            return null;
        }
        
        $json = Storage::disk($this->disk)->get($this->getPath($filename));
        return json_decode($json, true);
    }

    /**
     * List all available snapshots.
     *
     * @return array
     */
    public function listSnapshots(): array
    {
        $files = Storage::disk($this->disk)->files($this->path);
        $snapshots = [];
        
        foreach ($files as $file) {
            if (!Str::endsWith($file, '.json')) {
                continue;
            }
            
            $json = Storage::disk($this->disk)->get($file);
            $data = json_decode($json, true);
            
            if (!$data) {
                continue;
            }
            
            $snapshots[] = [
                'id' => $data['id'],
                'label' => $data['label'],
                'timestamp' => $data['timestamp'],
                'filename' => basename($file),
            ];
        }
        
        // Sort by timestamp descending (newest first)
        usort($snapshots, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });
        
        return $snapshots;
    }

    /**
     * Delete a snapshot.
     *
     * @param string $id
     * @return bool
     */
    public function deleteSnapshot(string $id): bool
    {
        $filename = "{$id}.json";
        
        if (!Storage::disk($this->disk)->exists($this->getPath($filename))) {
            return false;
        }
        
        return Storage::disk($this->disk)->delete($this->getPath($filename));
    }

    /**
     * Get the full path for a snapshot file.
     *
     * @param string $filename
     * @return string
     */
    protected function getPath(string $filename): string
    {
        return $this->path . '/' . $filename;
    }

    /**
     * Export debug data in the requested format.
     *
     * @param array $data
     * @param string $format
     * @return string
     */
    public function exportData(array $data, string $format = 'json'): string
    {
        if ($format === 'binary') {
            return $this->serializeToBinary($data);
        }
        
        return $this->serializeToJson($data, true);
    }

    /**
     * Import debug data from the provided content.
     *
     * @param string $content
     * @param string $format
     * @return array|null
     */
    public function importData(string $content, string $format = 'json'): ?array
    {
        if ($format === 'binary') {
            return $this->deserializeFromBinary($content);
        }
        
        return json_decode($content, true);
    }
}
