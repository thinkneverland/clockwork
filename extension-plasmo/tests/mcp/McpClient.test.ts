/**
 * McpClient tests
 * 
 * Tests for the MCP (Master Control Protocol) client that handles
 * communication between the extension and the Tapped server.
 */

import { McpClient } from '../../lib/mcp/McpClient';
import { WebSocketWrapper } from '../../lib/mcp/WebSocketWrapper';
import { ConnectionHealthMonitor } from '../../lib/mcp/ConnectionHealthMonitor';

// Mock dependencies
jest.mock('../../lib/mcp/WebSocketWrapper');
jest.mock('../../lib/mcp/ConnectionHealthMonitor');

describe('McpClient', () => {
  let client: McpClient;
  let mockSocket: jest.Mocked<WebSocketWrapper>;
  let mockHealthMonitor: jest.Mocked<ConnectionHealthMonitor>;
  
  beforeEach(() => {
    jest.clearAllMocks();
    
    // Setup mock socket
    mockSocket = new WebSocketWrapper('') as jest.Mocked<WebSocketWrapper>;
    mockSocket.connect.mockResolvedValue();
    mockSocket.disconnect.mockResolvedValue();
    mockSocket.send.mockImplementation(() => {});
    mockSocket.getStatus.mockReturnValue({
      connected: true,
      connecting: false,
      reconnectAttempts: 0,
      lastError: null
    });
    
    // Setup mock health monitor
    mockHealthMonitor = new ConnectionHealthMonitor() as jest.Mocked<ConnectionHealthMonitor>;
    mockHealthMonitor.registerConnection.mockImplementation(() => {});
    mockHealthMonitor.unregisterConnection.mockImplementation(() => {});
    mockHealthMonitor.getConnectionMetrics.mockReturnValue({
      connectionId: 'test-connection',
      url: 'ws://localhost:8080',
      connected: true,
      connectionTime: Date.now(),
      lastPingTime: Date.now(),
      lastPongTime: Date.now(),
      pingsSent: 0,
      pongsReceived: 0,
      latency: 0,
      dataReceived: 0,
      dataSent: 0,
      errors: 0,
      isZombie: false
    });
    
    // Setup mocks for constructor injection
    (WebSocketWrapper as jest.Mock).mockImplementation(() => mockSocket);
    (ConnectionHealthMonitor as jest.Mock).mockImplementation(() => mockHealthMonitor);
    
    // Create client
    client = new McpClient();
  });
  
  describe('connection handling', () => {
    test('connect establishes connection to server', async () => {
      const url = 'ws://localhost:8080';
      await client.connect(url);
      
      expect(WebSocketWrapper).toHaveBeenCalledWith(url, expect.any(Object));
      expect(mockSocket.connect).toHaveBeenCalled();
      expect(mockHealthMonitor.registerConnection).toHaveBeenCalledWith(
        expect.any(String), // Connection ID
        mockSocket,
        url
      );
    });
    
    test('disconnect closes connection', async () => {
      // First connect
      await client.connect('ws://localhost:8080');
      
      // Then disconnect
      await client.disconnect();
      
      expect(mockSocket.disconnect).toHaveBeenCalled();
      expect(mockHealthMonitor.unregisterConnection).toHaveBeenCalled();
    });
    
    test('isConnected returns connection status', async () => {
      // Initially not connected
      expect(client.isConnected()).toBe(false);
      
      // Connect
      await client.connect('ws://localhost:8080');
      
      // Mock socket is connected
      expect(client.isConnected()).toBe(true);
      
      // Mock socket disconnected
      mockSocket.getStatus.mockReturnValue({
        connected: false,
        connecting: false,
        reconnectAttempts: 0,
        lastError: null
      });
      
      expect(client.isConnected()).toBe(false);
    });
    
    test('handles connection errors', async () => {
      mockSocket.connect.mockRejectedValueOnce(new Error('Connection error'));
      
      // Should not throw but handle the error internally
      await expect(client.connect('ws://localhost:8080')).resolves.not.toThrow();
      
      // Socket should be in error state
      expect(client.getConnectionStatus().hasError).toBe(true);
    });
  });
  
  describe('message handling', () => {
    const testMessageHandler = jest.fn();
    
    beforeEach(async () => {
      // Setup connection and message handler
      await client.connect('ws://localhost:8080');
      client.addMessageHandler('test', testMessageHandler);
    });
    
    test('addMessageHandler registers handler for message type', () => {
      // Get access to internal map
      const handlers = (client as any).messageHandlers;
      
      expect(handlers.get('test')).toBe(testMessageHandler);
    });
    
    test('removeMessageHandler unregisters handler', () => {
      client.removeMessageHandler('test');
      
      const handlers = (client as any).messageHandlers;
      
      expect(handlers.has('test')).toBe(false);
    });
    
    test('sends messages to server', () => {
      const message = {
        type: 'test',
        data: { foo: 'bar' }
      };
      
      client.send(message);
      
      expect(mockSocket.send).toHaveBeenCalledWith(JSON.stringify(message));
    });
    
    test('handles incoming messages', () => {
      // Simulate receiving a message from the server
      const message = {
        type: 'test',
        data: { foo: 'bar' }
      };
      
      // Get the message handler
      const socketMessageHandler = (client as any).handleMessage;
      
      // Call the handler with the message
      socketMessageHandler(JSON.stringify(message));
      
      // Our test handler should have been called with the message data
      expect(testMessageHandler).toHaveBeenCalledWith(message.data);
    });
    
    test('handles malformed incoming messages', () => {
      // Get the message handler
      const socketMessageHandler = (client as any).handleMessage;
      
      // Call with invalid JSON
      socketMessageHandler('invalid json');
      
      // No handler should be called, and no error should be thrown
      expect(testMessageHandler).not.toHaveBeenCalled();
    });
    
    test('handles messages with unknown type', () => {
      // Simulate receiving a message with unknown type
      const message = {
        type: 'unknown',
        data: { foo: 'bar' }
      };
      
      // Get the message handler
      const socketMessageHandler = (client as any).handleMessage;
      
      // Call the handler with the message
      socketMessageHandler(JSON.stringify(message));
      
      // Our test handler should not have been called
      expect(testMessageHandler).not.toHaveBeenCalled();
    });
  });
  
  describe('connection health monitoring', () => {
    beforeEach(async () => {
      await client.connect('ws://localhost:8080');
    });
    
    test('getConnectionHealth returns health metrics', () => {
      const health = client.getConnectionHealth();
      
      expect(health).toEqual(mockHealthMonitor.getConnectionMetrics.mock.results[0].value);
    });
    
    test('handles zombie connections', () => {
      // Mock a zombie connection
      mockHealthMonitor.getConnectionMetrics.mockReturnValue({
        connectionId: 'test-connection',
        url: 'ws://localhost:8080',
        connected: true,
        connectionTime: Date.now(),
        lastPingTime: Date.now() - 60000, // Last ping 1 minute ago
        lastPongTime: Date.now() - 60000, // Last pong 1 minute ago
        pingsSent: 5,
        pongsReceived: 0, // No pongs received
        latency: 0,
        dataReceived: 0,
        dataSent: 500,
        errors: 0,
        isZombie: true // Marked as zombie
      });
      
      // Trigger zombie check
      client.checkConnection();
      
      // Should have tried to disconnect and reconnect
      expect(mockSocket.disconnect).toHaveBeenCalled();
    });
  });
  
  describe('cross-browser compatibility', () => {
    test('works with different WebSocket implementations', async () => {
      // Different browsers might have slightly different WebSocket implementations
      // This test ensures our client works with various implementations
      
      // Mock a Firefox-style WebSocket
      const originalWebSocket = global.WebSocket;
      
      // Create a custom WebSocket mock
      const CustomWebSocket = jest.fn().mockImplementation((url) => {
        const socket = {
          url,
          readyState: 0, // CONNECTING
          CONNECTING: 0,
          OPEN: 1,
          CLOSING: 2,
          CLOSED: 3,
          send: jest.fn(),
          close: jest.fn(),
          addEventListener: jest.fn(),
          removeEventListener: jest.fn()
        };
        
        // Auto open after a delay
        setTimeout(() => {
          socket.readyState = 1; // OPEN
          const event = { target: socket };
          const openListeners = socket.addEventListener.mock.calls
            .filter(call => call[0] === 'open')
            .map(call => call[1]);
          
          openListeners.forEach(listener => listener(event));
        }, 0);
        
        return socket;
      });
      
      global.WebSocket = CustomWebSocket as any;
      
      // Create a new client with our custom WebSocket
      const customClient = new McpClient();
      
      // Connect should work with the custom WebSocket
      await customClient.connect('ws://localhost:8080');
      
      // Restore original WebSocket
      global.WebSocket = originalWebSocket;
      
      // Client should have connected successfully
      expect(customClient.isConnected()).toBe(true);
    });
  });
  
  describe('authentication', () => {
    test('authenticates with server', async () => {
      await client.connect('ws://localhost:8080');
      
      const authToken = 'test-auth-token';
      const userId = 'user-123';
      
      // Attempt authentication
      client.authenticate(authToken, userId);
      
      // Should have sent authentication message
      expect(mockSocket.send).toHaveBeenCalledWith(
        expect.stringContaining('authenticate')
      );
      expect(mockSocket.send).toHaveBeenCalledWith(
        expect.stringContaining(authToken)
      );
      expect(mockSocket.send).toHaveBeenCalledWith(
        expect.stringContaining(userId)
      );
    });
  });
  
  describe('resource cleanup', () => {
    test('cleans up resources on disconnect', async () => {
      // Setup connection
      await client.connect('ws://localhost:8080');
      
      // Register handlers
      client.addMessageHandler('test1', jest.fn());
      client.addMessageHandler('test2', jest.fn());
      
      // Disconnect
      await client.disconnect();
      
      // Message handlers should be cleared
      expect((client as any).messageHandlers.size).toBe(0);
      
      // Socket should be disconnected
      expect(mockSocket.disconnect).toHaveBeenCalled();
      
      // Health monitor should unregister connection
      expect(mockHealthMonitor.unregisterConnection).toHaveBeenCalled();
    });
  });
});
