<?php

namespace ThinkNeverland\Tapped\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ThinkNeverland\Tapped\Server\Connection;
use Ratchet\ConnectionInterface;
use Mockery;

class ConnectionTest extends TestCase
{
    protected $rawConnection;
    protected $connection;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a mock Ratchet connection
        $this->rawConnection = Mockery::mock(ConnectionInterface::class);
        $this->rawConnection->resourceId = 123;
        
        // Create the connection wrapper
        $this->connection = new Connection($this->rawConnection);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    public function testGetId()
    {
        $this->assertEquals(123, $this->connection->getId());
    }
    
    public function testSend()
    {
        // Test data to send
        $data = ['type' => 'test', 'data' => 'hello'];
        
        // Expect the raw connection to receive JSON string
        $this->rawConnection->shouldReceive('send')
            ->once()
            ->with(json_encode($data));
        
        // Call the method
        $this->connection->send($data);
    }
    
    public function testSendWithJsonEncodeError()
    {
        // Create test data with circular reference to cause JSON encode error
        $data = ['type' => 'test'];
        $data['circular'] = &$data;
        
        // Expect an error to be thrown
        $this->expectException(\RuntimeException::class);
        
        // Call the method
        $this->connection->send($data);
    }
    
    public function testAuthentication()
    {
        // Default should be unauthenticated
        $this->assertFalse($this->connection->isAuthenticated());
        
        // Set as authenticated
        $this->connection->setAuthenticated(true);
        $this->assertTrue($this->connection->isAuthenticated());
        
        // Set back to unauthenticated
        $this->connection->setAuthenticated(false);
        $this->assertFalse($this->connection->isAuthenticated());
    }
    
    public function testClientType()
    {
        // Default should be null
        $this->assertNull($this->connection->getClientType());
        
        // Set client type
        $this->connection->setClientType('browser-extension');
        $this->assertEquals('browser-extension', $this->connection->getClientType());
    }
    
    public function testClientVersion()
    {
        // Default should be null
        $this->assertNull($this->connection->getClientVersion());
        
        // Set client version
        $this->connection->setClientVersion('1.0.0');
        $this->assertEquals('1.0.0', $this->connection->getClientVersion());
    }
    
    public function testPing()
    {
        // Expect the raw connection to receive a ping message
        $this->rawConnection->shouldReceive('send')
            ->once()
            ->with(json_encode(['type' => 'ping', 'timestamp' => Mockery::type('int')]));
        
        // Call the method
        $this->connection->sendPing();
    }
    
    public function testGetLastActivity()
    {
        // New connection should have recent activity
        $now = time();
        $this->assertLessThanOrEqual(1, $now - $this->connection->getLastActivity());
        
        // Update last activity
        $this->connection->updateLastActivity();
        $this->assertLessThanOrEqual(1, $now - $this->connection->getLastActivity());
    }
    
    public function testGetSessionData()
    {
        // Default should be empty array
        $this->assertEquals([], $this->connection->getSessionData());
        
        // Set some session data
        $this->connection->setSessionData('user_id', 123);
        $this->assertEquals(['user_id' => 123], $this->connection->getSessionData());
        
        // Get specific session data
        $this->assertEquals(123, $this->connection->getSessionData('user_id'));
        $this->assertNull($this->connection->getSessionData('nonexistent'));
        
        // Set default return value
        $this->assertEquals('default', $this->connection->getSessionData('nonexistent', 'default'));
    }
}
