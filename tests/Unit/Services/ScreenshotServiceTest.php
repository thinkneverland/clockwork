<?php

namespace ThinkNeverland\Tapped\Tests\Unit\Services;

use ThinkNeverland\Tapped\Services\ScreenshotService;
use ThinkNeverland\Tapped\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class ScreenshotServiceTest extends TestCase
{
    protected ScreenshotService $screenshotService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up a fake storage disk
        Storage::fake('local');
        
        $this->screenshotService = new ScreenshotService();
    }
    
    public function testSaveScreenshot()
    {
        // Create a fake image
        $image = UploadedFile::fake()->image('screenshot.png', 100, 100);
        $imageContents = file_get_contents($image->path());
        
        $metadata = [
            'type' => 'page',
            'url' => 'https://example.com',
            'width' => 1024,
            'height' => 768,
        ];
        
        $result = $this->screenshotService->saveScreenshot($imageContents, $metadata);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('metadata', $result);
        
        // Verify the screenshot was stored
        Storage::disk('local')->assertExists($result['path']);
        
        // Verify metadata
        $this->assertEquals('page', $result['metadata']['type']);
        $this->assertEquals('https://example.com', $result['metadata']['url']);
        $this->assertEquals(1024, $result['metadata']['width']);
        $this->assertEquals(768, $result['metadata']['height']);
    }
    
    public function testGetScreenshots()
    {
        // Create a few screenshots first
        $image = UploadedFile::fake()->image('screenshot.png', 100, 100);
        $imageContents = file_get_contents($image->path());
        
        $this->screenshotService->saveScreenshot($imageContents, ['type' => 'page']);
        $this->screenshotService->saveScreenshot($imageContents, ['type' => 'element']);
        $this->screenshotService->saveScreenshot($imageContents, ['type' => 'page']);
        
        // Get all screenshots
        $screenshots = $this->screenshotService->getScreenshots();
        
        $this->assertIsArray($screenshots);
        $this->assertCount(3, $screenshots);
        
        // Filter by type
        $pageScreenshots = $this->screenshotService->getScreenshots('page');
        $this->assertCount(2, $pageScreenshots);
        
        $elementScreenshots = $this->screenshotService->getScreenshots('element');
        $this->assertCount(1, $elementScreenshots);
    }
    
    public function testStartRecording()
    {
        $metadata = [
            'description' => 'Test recording',
            'browser' => 'Chrome',
        ];
        
        $recording = $this->screenshotService->startRecording($metadata);
        
        $this->assertIsArray($recording);
        $this->assertArrayHasKey('id', $recording);
        $this->assertArrayHasKey('status', $recording);
        $this->assertArrayHasKey('started_at', $recording);
        $this->assertArrayHasKey('metadata', $recording);
        
        $this->assertEquals('started', $recording['status']);
        $this->assertEquals('Test recording', $recording['metadata']['description']);
        $this->assertEquals('Chrome', $recording['metadata']['browser']);
        
        // Verify the recording info was stored
        Storage::disk('local')->assertExists('tapped/recordings/' . $recording['id'] . '.json');
    }
    
    public function testAddFrameToRecording()
    {
        // Start a recording
        $recording = $this->screenshotService->startRecording(['description' => 'Test recording']);
        
        // Create a fake image
        $image = UploadedFile::fake()->image('frame.png', 100, 100);
        $imageContents = file_get_contents($image->path());
        
        // Add a frame
        $frame = $this->screenshotService->addFrameToRecording($recording['id'], $imageContents);
        
        $this->assertIsArray($frame);
        $this->assertArrayHasKey('filename', $frame);
        $this->assertArrayHasKey('path', $frame);
        
        // Verify the frame was stored
        Storage::disk('local')->assertExists($frame['path']);
        
        // Get the updated recording
        $updatedRecording = $this->screenshotService->getRecording($recording['id']);
        $this->assertEquals(1, $updatedRecording['frame_count']);
        $this->assertCount(1, $updatedRecording['frames']);
    }
    
    public function testCompleteRecording()
    {
        // Start a recording
        $recording = $this->screenshotService->startRecording(['description' => 'Test recording']);
        
        // Create a fake image and add frames
        $image = UploadedFile::fake()->image('frame.png', 100, 100);
        $imageContents = file_get_contents($image->path());
        
        $this->screenshotService->addFrameToRecording($recording['id'], $imageContents);
        $this->screenshotService->addFrameToRecording($recording['id'], $imageContents);
        
        // Complete the recording
        $completedRecording = $this->screenshotService->completeRecording($recording['id'], ['notes' => 'Completed successfully']);
        
        $this->assertIsArray($completedRecording);
        $this->assertEquals('completed', $completedRecording['status']);
        $this->assertArrayHasKey('completed_at', $completedRecording);
        $this->assertEquals(2, $completedRecording['frame_count']);
        $this->assertEquals('Completed successfully', $completedRecording['metadata']['notes']);
    }
    
    public function testGetRecordings()
    {
        // Create a few recordings
        $this->screenshotService->startRecording(['description' => 'Recording 1']);
        $this->screenshotService->startRecording(['description' => 'Recording 2']);
        $recording3 = $this->screenshotService->startRecording(['description' => 'Recording 3']);
        
        // Complete one recording
        $this->screenshotService->completeRecording($recording3['id']);
        
        // Get all recordings
        $recordings = $this->screenshotService->getRecordings();
        
        $this->assertIsArray($recordings);
        $this->assertCount(3, $recordings);
        
        // Check statuses
        $this->assertEquals('started', $recordings[0]['status']);
        $this->assertEquals('started', $recordings[1]['status']);
        $this->assertEquals('completed', $recordings[2]['status']);
    }
}
