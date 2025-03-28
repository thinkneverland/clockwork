<?php

namespace ThinkNeverland\Tapped\Tests\Unit\Services;

use ThinkNeverland\Tapped\Services\DebugStateSerializer;
use ThinkNeverland\Tapped\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class DebugStateSerializerTest extends TestCase
{
    protected DebugStateSerializer $serializer;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up a fake storage disk
        Storage::fake('local');
        
        $this->serializer = new DebugStateSerializer();
    }
    
    public function testSerializeToJson()
    {
        $data = [
            'livewire' => [
                [
                    'id' => 'test-component',
                    'name' => 'TestComponent',
                    'properties' => ['count' => 5],
                ]
            ],
            'queries' => [
                [
                    'query' => 'SELECT * FROM users',
                    'time' => 1.5,
                ]
            ],
        ];
        
        $json = $this->serializer->toJson($data);
        
        $this->assertJson($json);
        $this->assertStringContainsString('test-component', $json);
        $this->assertStringContainsString('TestComponent', $json);
        $this->assertStringContainsString('SELECT * FROM users', $json);
        
        // Verify we can decode it back
        $decoded = json_decode($json, true);
        $this->assertEquals($data, $decoded);
    }
    
    public function testSerializeToBinary()
    {
        $data = [
            'livewire' => [
                [
                    'id' => 'test-component',
                    'name' => 'TestComponent',
                    'properties' => ['count' => 5],
                ]
            ],
        ];
        
        $binary = $this->serializer->toBinary($data);
        
        $this->assertIsString($binary);
        $this->assertGreaterThan(0, strlen($binary));
        
        // The binary format should start with our schema version header
        $this->assertStringStartsWith('TAPPED', $binary);
    }
    
    public function testCreateSnapshot()
    {
        $data = [
            'livewire' => [
                [
                    'id' => 'test-component',
                    'name' => 'TestComponent',
                    'properties' => ['count' => 5],
                ]
            ],
        ];
        
        $label = 'Test Snapshot';
        
        $snapshot = $this->serializer->createSnapshot($data, $label);
        
        $this->assertIsArray($snapshot);
        $this->assertArrayHasKey('id', $snapshot);
        $this->assertArrayHasKey('label', $snapshot);
        $this->assertArrayHasKey('timestamp', $snapshot);
        $this->assertEquals($label, $snapshot['label']);
        
        // Verify the snapshot was stored in the filesystem
        Storage::disk('local')->assertExists('tapped/snapshots/' . $snapshot['id'] . '.json');
    }
    
    public function testGetSnapshot()
    {
        $data = [
            'livewire' => [
                [
                    'id' => 'test-component',
                    'name' => 'TestComponent',
                    'properties' => ['count' => 5],
                ]
            ],
        ];
        
        // Create a snapshot first
        $snapshot = $this->serializer->createSnapshot($data, 'Test Snapshot');
        
        // Retrieve the snapshot
        $retrievedData = $this->serializer->getSnapshot($snapshot['id']);
        
        $this->assertIsArray($retrievedData);
        $this->assertEquals($data, $retrievedData['data']);
        $this->assertEquals('Test Snapshot', $retrievedData['label']);
    }
    
    public function testGetAllSnapshots()
    {
        // Create a few snapshots
        $this->serializer->createSnapshot(['test' => 'data1'], 'Snapshot 1');
        $this->serializer->createSnapshot(['test' => 'data2'], 'Snapshot 2');
        $this->serializer->createSnapshot(['test' => 'data3'], 'Snapshot 3');
        
        $snapshots = $this->serializer->getAllSnapshots();
        
        $this->assertIsArray($snapshots);
        $this->assertCount(3, $snapshots);
        
        // Verify snapshot structure
        $this->assertArrayHasKey('id', $snapshots[0]);
        $this->assertArrayHasKey('label', $snapshots[0]);
        $this->assertArrayHasKey('timestamp', $snapshots[0]);
    }
    
    public function testDeleteSnapshot()
    {
        // Create a snapshot first
        $snapshot = $this->serializer->createSnapshot(['test' => 'data'], 'Test Snapshot');
        
        // Verify it exists
        Storage::disk('local')->assertExists('tapped/snapshots/' . $snapshot['id'] . '.json');
        
        // Delete the snapshot
        $result = $this->serializer->deleteSnapshot($snapshot['id']);
        
        $this->assertTrue($result);
        
        // Verify it was deleted
        Storage::disk('local')->assertMissing('tapped/snapshots/' . $snapshot['id'] . '.json');
    }
}
