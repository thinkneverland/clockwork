<?php

namespace ThinkNeverland\Tapped\Tests\Feature\Http\Controllers\Api;

use ThinkNeverland\Tapped\Tests\TestCase;
use ThinkNeverland\Tapped\Services\DebugStateSerializer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

class DebugDataControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up a fake storage disk
        Storage::fake('local');
        
        // Disable API authentication for testing
        Config::set('tapped.api.auth.enabled', false);
        
        // Create some test data
        $serializer = new DebugStateSerializer();
        $testData = [
            'livewire' => [
                [
                    'id' => 'test-component',
                    'name' => 'Counter',
                    'class' => 'App\\Http\\Livewire\\Counter',
                    'properties' => ['count' => 5],
                ]
            ],
            'queries' => [
                [
                    'id' => 'query1',
                    'query' => 'SELECT * FROM users',
                    'time' => 1.5,
                ]
            ],
            'events' => [
                [
                    'id' => 'event1',
                    'type' => 'lifecycle',
                    'name' => 'mount',
                    'component' => 'Counter',
                ]
            ],
            'requests' => [
                [
                    'id' => 'req1',
                    'method' => 'GET',
                    'uri' => '/test',
                    'responseStatus' => 200,
                ]
            ],
        ];
        
        // Store a snapshot for testing
        $serializer->createSnapshot($testData, 'Test Snapshot');
    }
    
    public function testGetDebugData()
    {
        $response = $this->getJson('/tapped/api/debug-data');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'livewire',
                    'queries',
                    'events',
                    'requests',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }
    
    public function testGetLivewireData()
    {
        $response = $this->getJson('/tapped/api/debug-data/livewire');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'class',
                        'properties',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }
    
    public function testGetLivewireDataWithComponentId()
    {
        $response = $this->getJson('/tapped/api/debug-data/livewire?component_id=test-component');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'class',
                    'properties',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => 'test-component',
                    'name' => 'Counter',
                ],
            ]);
    }
    
    public function testGetQueryData()
    {
        $response = $this->getJson('/tapped/api/debug-data/queries');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'queries' => [
                        '*' => [
                            'id',
                            'query',
                            'time',
                        ],
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }
    
    public function testGetEventData()
    {
        $response = $this->getJson('/tapped/api/debug-data/events');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'name',
                        'component',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }
    
    public function testGetEventDataWithFilter()
    {
        $response = $this->getJson('/tapped/api/debug-data/events?type=lifecycle');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'type' => 'lifecycle',
                    ],
                ],
            ]);
    }
    
    public function testGetRequestData()
    {
        $response = $this->getJson('/tapped/api/debug-data/requests');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'method',
                        'uri',
                        'responseStatus',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }
    
    public function testGetSnapshots()
    {
        $response = $this->getJson('/tapped/api/debug-data/snapshots');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'label',
                        'timestamp',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }
    
    public function testCreateSnapshot()
    {
        $response = $this->postJson('/tapped/api/debug-data/snapshots', [
            'label' => 'API Created Snapshot',
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'snapshot_id',
                    'label',
                    'timestamp',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Snapshot created successfully',
                'data' => [
                    'label' => 'API Created Snapshot',
                ],
            ]);
            
        // Verify the snapshot was created
        $snapshotId = $response->json('data.snapshot_id');
        Storage::disk('local')->assertExists('tapped/snapshots/' . $snapshotId . '.json');
    }
    
    public function testGetSnapshot()
    {
        // First get the list of snapshots
        $response = $this->getJson('/tapped/api/debug-data/snapshots');
        $snapshotId = $response->json('data.0.id');
        
        // Then get a specific snapshot
        $response = $this->getJson('/tapped/api/debug-data/snapshots/' . $snapshotId);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'label',
                    'timestamp',
                    'data' => [
                        'livewire',
                        'queries',
                        'events',
                        'requests',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $snapshotId,
                    'label' => 'Test Snapshot',
                ],
            ]);
    }
    
    public function testDeleteSnapshot()
    {
        // First get the list of snapshots
        $response = $this->getJson('/tapped/api/debug-data/snapshots');
        $snapshotId = $response->json('data.0.id');
        
        // Then delete a specific snapshot
        $response = $this->deleteJson('/tapped/api/debug-data/snapshots/' . $snapshotId);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Snapshot deleted successfully',
            ]);
            
        // Verify the snapshot was deleted
        Storage::disk('local')->assertMissing('tapped/snapshots/' . $snapshotId . '.json');
    }
}
