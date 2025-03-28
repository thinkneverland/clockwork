<?php

namespace ThinkNeverland\Tapped\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ThinkNeverland\Tapped\Server\WebSocketServer;
use ThinkNeverland\Tapped\Server\Connection;
use ThinkNeverland\Tapped\Server\MessageHandler;
use Ratchet\RFC6455\Messaging\MessageInterface;
use Ratchet\ConnectionInterface;
use Mockery;

class WebSocketServerTest extends TestCase
{
    protected $webSocketServer;
    protected $messageHandler;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a mock message handler
        $this->messageHandler = Mockery::mock(MessageHandler::class);
        
        // Create the websocket server with the mock handler
        $this->webSocketServer = new WebSocketServer($this->messageHandler);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    public function testOnOpen()
    {
        // Create a mock connection
        $conn = Mockery::mock(ConnectionInterface::class);
        $conn->resourceId = 123;
        
        // Set expectations on the message handler
        $this->messageHandler->shouldReceive('handleConnect')
            ->once()
            ->with(Mockery::type(Connection::class));
        
        // Call the method
        $this->webSocketServer->onOpen($conn);
        
        // Verify connections are tracked
        $this->assertArrayHasKey(123, $this->webSocketServer->getConnections());
    }
    
    public function testOnMessage()
    {
        // Create a mock connection
        $conn = Mockery::mock(ConnectionInterface::class);
        $conn->resourceId = 123;
        
        // Create a mock message
        $msg = Mockery::mock(MessageInterface::class);
        $msg->shouldReceive('getPayload')
            ->andReturn('{"type":"test","data":"hello"}');
        
        // Set expectations on the message handler
        $this->messageHandler->shouldReceive('handleMessage')
            ->once()
            ->with(
                Mockery::type(Connection::class),
                Mockery::on(function ($arg) {
                    return is_array($arg) && $arg['type'] === 'test' && $arg['data'] === 'hello';
                })
            );
        
        // First open the connection
        $this->messageHandler->shouldReceive('handleConnect')->once();
        $this->webSocketServer->onOpen($conn);
        
        // Call the method
        $this->webSocketServer->onMessage($conn, $msg);
    }
    
    public function testOnClose()
    {
        // Create a mock connection
        $conn = Mockery::mock(ConnectionInterface::class);
        $conn->resourceId = 123;
        
        // Set expectations on the message handler
        $this->messageHandler->shouldReceive('handleConnect')->once();
        $this->messageHandler->shouldReceive('handleDisconnect')
            ->once()
            ->with(Mockery::type(Connection::class));
        
        // First open the connection
        $this->webSocketServer->onOpen($conn);
        
        // Call the method
        $this->webSocketServer->onClose($conn);
        
        // Verify connections are removed
        $this->assertArrayNotHasKey(123, $this->webSocketServer->getConnections());
    }
    
    public function testOnError()
    {
        // Create a mock connection
        $conn = Mockery::mock(ConnectionInterface::class);
        $conn->resourceId = 123;
        
        // Create a test exception
        $exception = new \Exception('Test error');
        
        // Set expectations on the message handler
        $this->messageHandler->shouldReceive('handleError')
            ->once()
            ->with(
                Mockery::type(Connection::class),
                Mockery::type(\Exception::class)
            );
        
        // Call the method
        $this->webSocketServer->onError($conn, $exception);
    }
    
    public function testBroadcast()
    {
        // Create multiple mock connections
        $conn1 = Mockery::mock(ConnectionInterface::class);
        $conn1->resourceId = 1;
        $conn1->shouldReceive('send')->once()->with('{"type":"broadcast","data":"test"}');
        
        $conn2 = Mockery::mock(ConnectionInterface::class);
        $conn2->resourceId = 2;
        $conn2->shouldReceive('send')->once()->with('{"type":"broadcast","data":"test"}');
        
        // Set expectations on the message handler
        $this->messageHandler->shouldReceive('handleConnect')->twice();
        
        // Open the connections
        $this->webSocketServer->onOpen($conn1);
        $this->webSocketServer->onOpen($conn2);
        
        // Test broadcasting
        $this->webSocketServer->broadcast(['type' => 'broadcast', 'data' => 'test']);
    }
    
    public function testSendToConnection()
    {
        // Create a mock connection
        $conn = Mockery::mock(ConnectionInterface::class);
        $conn->resourceId = 123;
        $conn->shouldReceive('send')->once()->with('{"type":"direct","data":"test"}');
        
        // Set expectations on the message handler
        $this->messageHandler->shouldReceive('handleConnect')->once();
        
        // First open the connection
        $this->webSocketServer->onOpen($conn);
        
        // Send a message to this specific connection
        $connection = $this->webSocketServer->getConnections()[123];
        $connection->send(['type' => 'direct', 'data' => 'test']);
    }
    
    public function testOnMessageWithInvalidJson()
    {
        // Create a mock connection
        $conn = Mockery::mock(ConnectionInterface::class);
        $conn->resourceId = 123;
        
        // Create a mock message with invalid JSON
        $msg = Mockery::mock(MessageInterface::class);
        $msg->shouldReceive('getPayload')
            ->andReturn('{invalid json}');
        
        // Set expectations on the message handler
        $this->messageHandler->shouldReceive('handleConnect')->once();
        $this->messageHandler->shouldReceive('handleError')
            ->once()
            ->with(
                Mockery::type(Connection::class),
                Mockery::type(\Exception::class)
            );
        
        // First open the connection
        $this->webSocketServer->onOpen($conn);
        
        // Call the method
        $this->webSocketServer->onMessage($conn, $msg);
    }
    
    public function testConnectionAuthentication()
    {
        // Create a mock connection
        $conn = Mockery::mock(ConnectionInterface::class);
        $conn->resourceId = 123;
        
        // Create a mock message for authentication
        $msg = Mockery::mock(MessageInterface::class);
        $msg->shouldReceive('getPayload')
            ->andReturn('{"command":"authenticate","client":"browser-extension","version":"1.0"}');
        
        // Set expectations on the message handler
        $this->messageHandler->shouldReceive('handleConnect')->once();
        $this->messageHandler->shouldReceive('handleAuthentication')
            ->once()
            ->with(
                Mockery::type(Connection::class),
                Mockery::on(function ($arg) {
                    return is_array($arg) && 
                           $arg['command'] === 'authenticate' && 
                           $arg['client'] === 'browser-extension';
                })
            );
        
        // First open the connection
        $this->webSocketServer->onOpen($conn);
        
        // Call the method
        $this->webSocketServer->onMessage($conn, $msg);
    }
}
