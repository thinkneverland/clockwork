<?php

namespace ThinkNeverland\Tapped\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ScreenshotService
{
    /**
     * The storage disk to use for screenshots.
     */
    protected $disk;

    /**
     * The path within the disk where screenshots are stored.
     */
    protected $path;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->disk = config('tapped.storage.disk', 'local');
        $this->path = config('tapped.storage.path', 'tapped/screenshots');
    }

    /**
     * Save a page screenshot.
     *
     * @param string $base64Image Base64 encoded image data
     * @param array $metadata Optional metadata to embed with the screenshot
     * @return array Information about the saved screenshot
     */
    public function savePageScreenshot(string $base64Image, array $metadata = []): array
    {
        $id = Str::uuid()->toString();
        $timestamp = time();
        $filename = "{$id}.png";
        
        // Remove data URL prefix if present
        $base64Image = preg_replace('/^data:image\/\w+;base64,/', '', $base64Image);
        $imageData = base64_decode($base64Image);
        
        if (!$imageData) {
            throw new \InvalidArgumentException('Invalid base64 image data');
        }
        
        // Sanitize and prepare metadata
        $safeMetadata = $this->sanitizeMetadata($metadata);
        
        $metadata = array_merge([
            'id' => $id,
            'type' => 'page',
            'timestamp' => $timestamp,
            'date' => date('Y-m-d H:i:s', $timestamp),
        ], $safeMetadata);
        
        // Save the image
        Storage::disk($this->disk)->put($this->getPath($filename), $imageData);
        
        // Save the metadata
        $metaFilename = "{$id}.meta.json";
        Storage::disk($this->disk)->put(
            $this->getPath($metaFilename), 
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)
        );
        
        return [
            'id' => $id,
            'filename' => $filename,
            'path' => $this->getPath($filename),
            'url' => $this->getUrl($filename),
            'metadata' => $metadata,
        ];
    }

    /**
     * Save an element-specific screenshot.
     *
     * @param string $base64Image Base64 encoded image data
     * @param array $elementInfo Information about the element
     * @param array $metadata Optional metadata to embed with the screenshot
     * @return array Information about the saved screenshot
     */
    public function saveElementScreenshot(string $base64Image, array $elementInfo, array $metadata = []): array
    {
        $elementMetadata = [
            'type' => 'element',
            'element' => $elementInfo,
        ];
        
        return $this->savePageScreenshot(
            $base64Image, 
            array_merge($elementMetadata, $metadata)
        );
    }

    /**
     * Save a recording frame.
     *
     * @param string $recordingId Recording session ID
     * @param string $base64Image Base64 encoded image data
     * @param int $frameNumber Frame sequence number
     * @param array $metadata Optional metadata to embed with the frame
     * @return array Information about the saved frame
     */
    public function saveRecordingFrame(string $recordingId, string $base64Image, int $frameNumber, array $metadata = []): array
    {
        $timestamp = time();
        $filename = "{$recordingId}_frame_{$frameNumber}.png";
        
        // Remove data URL prefix if present
        $base64Image = preg_replace('/^data:image\/\w+;base64,/', '', $base64Image);
        $imageData = base64_decode($base64Image);
        
        if (!$imageData) {
            throw new \InvalidArgumentException('Invalid base64 image data');
        }
        
        // Prepare metadata
        $metadata = array_merge([
            'recording_id' => $recordingId,
            'frame' => $frameNumber,
            'type' => 'recording_frame',
            'timestamp' => $timestamp,
            'date' => date('Y-m-d H:i:s', $timestamp),
        ], $metadata);
        
        // Save the image
        Storage::disk($this->disk)->put($this->getPath($filename), $imageData);
        
        return [
            'recording_id' => $recordingId,
            'frame' => $frameNumber,
            'filename' => $filename,
            'path' => $this->getPath($filename),
            'url' => $this->getUrl($filename),
            'metadata' => $metadata,
        ];
    }

    /**
     * Start a new recording session.
     *
     * @param array $metadata Optional metadata about the recording
     * @return array Recording session information
     */
    public function startRecording(array $metadata = []): array
    {
        $id = Str::uuid()->toString();
        $timestamp = time();
        
        $recording = [
            'id' => $id,
            'status' => 'started',
            'started_at' => $timestamp,
            'frame_count' => 0,
            'metadata' => array_merge([
                'date' => date('Y-m-d H:i:s', $timestamp),
            ], $metadata),
        ];
        
        // Save recording metadata
        $metaFilename = "{$id}.recording.json";
        Storage::disk($this->disk)->put($this->getPath($metaFilename), json_encode($recording, JSON_PRETTY_PRINT));
        
        return $recording;
    }

    /**
     * Complete a recording session.
     *
     * @param string $recordingId Recording session ID
     * @param int $frameCount Total number of frames
     * @param array $metadata Optional additional metadata
     * @return array Updated recording information
     */
    public function completeRecording(string $recordingId, int $frameCount, array $metadata = []): array
    {
        $metaFilename = "{$recordingId}.recording.json";
        $metaPath = $this->getPath($metaFilename);
        
        if (!Storage::disk($this->disk)->exists($metaPath)) {
            throw new \InvalidArgumentException("Recording session not found: {$recordingId}");
        }
        
        $recordingData = json_decode(Storage::disk($this->disk)->get($metaPath), true);
        
        $timestamp = time();
        $recordingData['status'] = 'completed';
        $recordingData['completed_at'] = $timestamp;
        $recordingData['frame_count'] = $frameCount;
        $recordingData['duration'] = $timestamp - $recordingData['started_at'];
        $recordingData['metadata'] = array_merge($recordingData['metadata'], $metadata);
        
        // Update recording metadata
        Storage::disk($this->disk)->put($metaPath, json_encode($recordingData, JSON_PRETTY_PRINT));
        
        return $recordingData;
    }

    /**
     * Get all screenshots.
     *
     * @param string $type Filter by screenshot type (page, element)
     * @return array List of screenshots
     */
    public function getScreenshots(string $type = null): array
    {
        $files = Storage::disk($this->disk)->files($this->path);
        $screenshots = [];
        
        foreach ($files as $file) {
            if (!Str::endsWith($file, '.meta.json')) {
                continue;
            }
            
            $json = Storage::disk($this->disk)->get($file);
            $metadata = json_decode($json, true);
            
            if (!$metadata) {
                continue;
            }
            
            if ($type !== null && ($metadata['type'] ?? '') !== $type) {
                continue;
            }
            
            $id = $metadata['id'] ?? basename($file, '.meta.json');
            $imageFile = Str::replaceLast('.meta.json', '.png', $file);
            
            $screenshots[] = [
                'id' => $id,
                'filename' => basename($imageFile),
                'path' => $imageFile,
                'url' => $this->getUrl(basename($imageFile)),
                'metadata' => $metadata,
            ];
        }
        
        // Sort by timestamp descending (newest first)
        usort($screenshots, function ($a, $b) {
            return ($b['metadata']['timestamp'] ?? 0) <=> ($a['metadata']['timestamp'] ?? 0);
        });
        
        return $screenshots;
    }

    /**
     * Get recordings.
     *
     * @return array List of recordings
     */
    public function getRecordings(): array
    {
        $files = Storage::disk($this->disk)->files($this->path);
        $recordings = [];
        
        foreach ($files as $file) {
            if (!Str::endsWith($file, '.recording.json')) {
                continue;
            }
            
            $json = Storage::disk($this->disk)->get($file);
            $recording = json_decode($json, true);
            
            if (!$recording) {
                continue;
            }
            
            $id = $recording['id'] ?? basename($file, '.recording.json');
            
            // Count actual frames
            $framePattern = $id . '_frame_*.png';
            $frameFiles = Storage::disk($this->disk)->files($this->path, $framePattern);
            
            $recording['actual_frame_count'] = count($frameFiles);
            $recording['frames'] = array_map(function ($frameFile) {
                return [
                    'filename' => basename($frameFile),
                    'path' => $frameFile,
                    'url' => $this->getUrl(basename($frameFile)),
                ];
            }, $frameFiles);
            
            $recordings[] = $recording;
        }
        
        // Sort by started_at descending (newest first)
        usort($recordings, function ($a, $b) {
            return ($b['started_at'] ?? 0) <=> ($a['started_at'] ?? 0);
        });
        
        return $recordings;
    }

    /**
     * Get the full path for a screenshot or recording file.
     *
     * @param string $filename
     * @return string
     */
    protected function getPath(string $filename): string
    {
        return $this->path . '/' . $filename;
    }

    /**
     * Get the URL for a screenshot or recording file.
     *
     * @param string $filename
     * @return string|null
     */
    protected function getUrl(string $filename): ?string
    {
        if (config('tapped.storage.disk') === 's3') {
            return Storage::disk($this->disk)->url($this->getPath($filename));
        }
        
        return null;
    }

    /**
     * Delete a screenshot.
     *
     * @param string $id Screenshot ID
     * @return bool
     */
    public function deleteScreenshot(string $id): bool
    {
        $imageFilename = "{$id}.png";
        $metaFilename = "{$id}.meta.json";
        
        $deleted = true;
        
        if (Storage::disk($this->disk)->exists($this->getPath($imageFilename))) {
            $deleted = Storage::disk($this->disk)->delete($this->getPath($imageFilename)) && $deleted;
        }
        
        if (Storage::disk($this->disk)->exists($this->getPath($metaFilename))) {
            $deleted = Storage::disk($this->disk)->delete($this->getPath($metaFilename)) && $deleted;
        }
        
        return $deleted;
    }

    /**
     * Delete a recording.
     *
     * @param string $id Recording ID
     * @return bool
     */
    public function deleteRecording(string $id): bool
    {
        $metaFilename = "{$id}.recording.json";
        
        $deleted = true;
        
        if (Storage::disk($this->disk)->exists($this->getPath($metaFilename))) {
            $deleted = Storage::disk($this->disk)->delete($this->getPath($metaFilename)) && $deleted;
        }
        
        // Delete all frames
        $framePattern = $id . '_frame_*.png';
        $frameFiles = Storage::disk($this->disk)->files($this->path, $framePattern);
        
        foreach ($frameFiles as $frameFile) {
            $deleted = Storage::disk($this->disk)->delete($frameFile) && $deleted;
        }
        
        return $deleted;
    }
}
