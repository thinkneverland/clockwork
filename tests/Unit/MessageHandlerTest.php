<?php

namespace ThinkNeverland\Tapped\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ThinkNeverland\Tapped\Server\MessageHandler;
use ThinkNeverland\Tapped\Server\Connection;
use ThinkNeverland\Tapped\Services\LivewireService;
use Mockery;

class MessageHandlerTest extends TestCase
{
    protected $messageHandler;
    protected $livewireService;
    protected $logger;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mock dependencies
        $this->livewireService = Mockery::mock(LivewireService::class);
        $this->logger = Mockery::mock('Psr\Log\LoggerInterface');
        
        // Create the message handler with mocks
        $this->messageHandler = new MessageHandler(
            $this->livewireService,
            $this->logger
        );
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    public function testHandleConnect()
    {
        // Create a mock connection
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getId')->andReturn(123);
        
        // Set expectations for the logger
        $this->logger->shouldReceive('info')
            ->once()
            ->with('New connection opened: 123');
        
        // Call the method
        $this->messageHandler->handleConnect($connection);
    }
    
    public function testHandleDisconnect()
    {
        // Create a mock connection
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getId')->andReturn(123);
        
        // Set expectations for the logger
        $this->logger->shouldReceive('info')
            ->once()
            ->with('Connection closed: 123');
        
        // Call the method
        $this->messageHandler->handleDisconnect($connection);
    }
    
    public function testHandleError()
    {
        // Create a mock connection
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getId')->andReturn(123);
        
        // Create a test exception
        $exception = new \Exception('Test error');
        
        // Set expectations for the logger
        $this->logger->shouldReceive('error')
            ->once()
            ->with('Error on connection 123: Test error', Mockery::type('array'));
        
        // Call the method
        $this->messageHandler->handleError($connection, $exception);
    }
    
    public function testHandleAuthentication()
    {
        // Create a mock connection
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getId')->andReturn(123);
        $connection->shouldReceive('setAuthenticated')->once()->with(true);
        $connection->shouldReceive('setClientType')->once()->with('browser-extension');
        $connection->shouldReceive('setClientVersion')->once()->with('1.0');
        $connection->shouldReceive('send')->once()->with(Mockery::on(function($arg) {
            return is_array($arg) && 
                   $arg['type'] === 'authentication' && 
                   $arg['status'] === 'success';
        }));
        
        // Set expectations for the logger
        $this->logger->shouldReceive('info')
            ->once()
            ->with('Client authenticated: browser-extension v1.0 on connection 123');
        
        // Message data for authentication
        $data = [
            'command' => 'authenticate',
            'client' => 'browser-extension',
            'version' => '1.0'
        ];
        
        // Call the method
        $this->messageHandler->handleAuthentication($connection, $data);
    }
    
    public function testHandleAuthenticationFailure()
    {
        // Create a mock connection
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getId')->andReturn(123);
        $connection->shouldReceive('setAuthenticated')->never();
        $connection->shouldReceive('send')->once()->with(Mockery::on(function($arg) {
            return is_array($arg) && 
                   $arg['type'] === 'authentication' && 
                   $arg['status'] === 'error';
        }));
        
        // Set expectations for the logger
        $this->logger->shouldReceive('warning')
            ->once()
            ->with('Failed authentication attempt on connection 123: Missing client type');
        
        // Message data for authentication (missing version)
        $data = [
            'command' => 'authenticate',
            'client' => 'browser-extension'
            // Missing version
        ];
        
        // Call the method
        $this->messageHandler->handleAuthentication($connection, $data);
    }
    
    public function testHandleMessage()
    {
        // Create a mock connection
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getId')->andReturn(123);
        $connection->shouldReceive('isAuthenticated')->andReturn(true);
        
        // Set expectations for the livewire service
        $this->livewireService->shouldReceive('processComponentUpdate')
            ->once()
            ->with(['component' => 'test-component', 'data' => ['value' => 42]], $connection)
            ->andReturn(['status' => 'success']);
        
        // Message data
        $data = [
            'type' => 'component-update',
            'component' => 'test-component',
            'data' => ['value' => 42]
        ];
        
        // Call the method
        $this->messageHandler->handleMessage($connection, $data);
    }
    
    public function testHandleMessageWithUnauthenticatedConnection()
    {
        // Create a mock connection
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getId')->andReturn(123);
        $connection->shouldReceive('isAuthenticated')->andReturn(false);
        $connection->shouldReceive('send')->once()->with(Mockery::on(function($arg) {
            return is_array($arg) && 
                   $arg['type'] === 'error' && 
                   $arg['message'] === 'Not authenticated';
        }));
        
        // Set expectations for the logger
        $this->logger->shouldReceive('warning')
            ->once()
            ->with('Unauthenticated message received on connection 123');
        
        // Message data
        $data = [
            'type' => 'component-update',
            'component' => 'test-component',
            'data' => ['value' => 42]
        ];
        
        // Call the method
        $this->messageHandler->handleMessage($connection, $data);
    }
    
    public function testHandleUnknownMessageType()
    {
        // Create a mock connection
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getId')->andReturn(123);
        $connection->shouldReceive('isAuthenticated')->andReturn(true);
        $connection->shouldReceive('send')->once()->with(Mockery::on(function($arg) {
            return is_array($arg) && 
                   $arg['type'] === 'error' && 
                   $arg['message'] === 'Unknown message type';
        }));
        
        // Set expectations for the logger
        $this->logger->shouldReceive('warning')
            ->once()
            ->with('Unknown message type received: unknown-type on connection 123');
        
        // Message data with unknown type
        $data = [
            'type' => 'unknown-type',
            'data' => 'test'
        ];
        
        // Call the method
        $this->messageHandler->handleMessage($connection, $data);
    }
}
