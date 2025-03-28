/**
 * WebSocketWrapper tests
 * 
 * Tests the WebSocket wrapper to ensure proper resource management,
 * cross-browser compatibility, and error handling features.
 */

import { WebSocketWrapper } from '../../lib/mcp/WebSocketWrapper';
import EventUtils from '../../lib/utils/EventUtils';

// Mock EventUtils
jest.mock('../../lib/utils/EventUtils', () => ({
  addEventListener: jest.fn(),
  removeEventListener: jest.fn(),
  detectBrowser: jest.fn(() => ({
    name: 'chrome',
    version: '100.0.0',
    isChrome: true,
    isFirefox: false,
    isEdge: false,
    isSafari: false,
    isOpera: false
  }))
}));

describe('WebSocketWrapper', () => {
  let wrapper: WebSocketWrapper;
  const mockUrl = 'ws://localhost:8080';
  
  // Mock WebSocket implementation
  class MockWebSocket {
    url: string;
    readyState: number = 0; // CONNECTING
    onopen: ((event: any) => void) | null = null;
    onmessage: ((event: any) => void) | null = null;
    onclose: ((event: any) => void) | null = null;
    onerror: ((event: any) => void) | null = null;
    
    static CONNECTING = 0;
    static OPEN = 1;
    static CLOSING = 2;
    static CLOSED = 3;
    
    constructor(url: string) {
      this.url = url;
    }
    
    send(data: string): void {
      // Mock implementation
    }
    
    close(): void {
      this.readyState = MockWebSocket.CLOSED;
      if (this.onclose) {
        this.onclose({ code: 1000, reason: 'Normal closure' });
      }
    }
    
    // Helper to simulate connection open
    _simulateOpen(): void {
      this.readyState = MockWebSocket.OPEN;
      if (this.onopen) {
        this.onopen({});
      }
    }
    
    // Helper to simulate message received
    _simulateMessage(data: any): void {
      if (this.onmessage) {
        this.onmessage({ data });
      }
    }
    
    // Helper to simulate connection error
    _simulateError(): void {
      if (this.onerror) {
        this.onerror({});
      }
    }
  }
  
  beforeEach(() => {
    // Clear all mocks
    jest.clearAllMocks();
    
    // Replace global WebSocket with mock
    global.WebSocket = MockWebSocket as any;
    
    // Create wrapper instance
    wrapper = new WebSocketWrapper(mockUrl, {
      debug: true,
      reconnectInterval: 100, // Fast interval for testing
      maxReconnectAttempts: 3
    });
    
    // Mock setTimeout and clearTimeout
    jest.useFakeTimers();
  });
  
  afterEach(() => {
    jest.useRealTimers();
  });
  
  describe('connect', () => {
    test('creates a WebSocket and registers event listeners', async () => {
      const connectPromise = wrapper.connect();
      
      // Simulate successful connection
      const socket = wrapper.getSocket() as MockWebSocket;
      socket._simulateOpen();
      
      await connectPromise;
      
      expect(EventUtils.addEventListener).toHaveBeenCalledTimes(4);
      expect(wrapper.getStatus().connected).toBe(true);
      expect(wrapper.getStatus().connecting).toBe(false);
      expect(wrapper.getStatus().healthStatus).toBe('healthy');
    });
    
    test('handles connection timeout', async () => {
      const connectPromise = wrapper.connect();
      
      // Fast-forward past the connection timeout
      jest.advanceTimersByTime(16000);
      
      await expect(connectPromise).rejects.toThrow('Connection timeout');
      expect(wrapper.getStatus().connected).toBe(false);
      expect(wrapper.getStatus().connecting).toBe(false);
      expect(wrapper.getStatus().healthStatus).toBe('unhealthy');
      expect(wrapper.getStatus().error).toBe('Connection timeout');
    });
    
    test('does not reconnect on deliberate disconnect', async () => {
      await wrapper.connect();
      
      // Mock the socket to simulate a successful connection
      const socket = wrapper.getSocket() as MockWebSocket;
      socket._simulateOpen();
      
      // Disconnect deliberately
      await wrapper.disconnect();
      
      // Fast-forward past the reconnect interval
      jest.advanceTimersByTime(200);
      
      // Should not have attempted to reconnect
      expect(wrapper.getStatus().connecting).toBe(false);
    });
  });
  
  describe('send', () => {
    test('sends data through the WebSocket', async () => {
      await wrapper.connect();
      
      // Mock the socket to simulate a successful connection
      const socket = wrapper.getSocket() as MockWebSocket;
      socket._simulateOpen();
      
      // Mock the send method
      socket.send = jest.fn();
      
      // Send a message
      wrapper.send('test message');
      
      expect(socket.send).toHaveBeenCalledWith('test message');
    });
    
    test('throws an error when not connected', () => {
      expect(() => wrapper.send('test')).toThrow('Not connected');
    });
  });
  
  describe('addEventListener', () => {
    test('registers event listeners', async () => {
      const listener = jest.fn();
      wrapper.addEventListener('message', listener);
      
      await wrapper.connect();
      
      // Mock the socket to simulate a successful connection
      const socket = wrapper.getSocket() as MockWebSocket;
      socket._simulateOpen();
      
      // Simulate a message
      socket._simulateMessage('test data');
      
      expect(listener).toHaveBeenCalled();
    });
  });
  
  describe('removeEventListener', () => {
    test('removes event listeners', async () => {
      const listener = jest.fn();
      wrapper.addEventListener('message', listener);
      wrapper.removeEventListener('message', listener);
      
      await wrapper.connect();
      
      // Mock the socket to simulate a successful connection
      const socket = wrapper.getSocket() as MockWebSocket;
      socket._simulateOpen();
      
      // Simulate a message
      socket._simulateMessage('test data');
      
      expect(listener).not.toHaveBeenCalled();
    });
  });
  
  describe('removeAllEventListeners', () => {
    test('removes all event listeners for an event type', async () => {
      const listener1 = jest.fn();
      const listener2 = jest.fn();
      wrapper.addEventListener('message', listener1);
      wrapper.addEventListener('message', listener2);
      wrapper.removeAllEventListeners('message');
      
      await wrapper.connect();
      
      // Mock the socket to simulate a successful connection
      const socket = wrapper.getSocket() as MockWebSocket;
      socket._simulateOpen();
      
      // Simulate a message
      socket._simulateMessage('test data');
      
      expect(listener1).not.toHaveBeenCalled();
      expect(listener2).not.toHaveBeenCalled();
    });
  });
  
  describe('reconnection', () => {
    test('attempts to reconnect after connection error', async () => {
      const connectPromise = wrapper.connect();
      
      // Mock the socket to simulate a successful connection
      const socket = wrapper.getSocket() as MockWebSocket;
      socket._simulateOpen();
      
      await connectPromise;
      
      // Spy on WebSocket constructor
      const originalWebSocket = global.WebSocket;
      global.WebSocket = jest.fn(() => new MockWebSocket(mockUrl)) as any;
      
      // Simulate connection error and close
      socket._simulateError();
      socket.close();
      
      // Fast-forward past the reconnect interval
      jest.advanceTimersByTime(150);
      
      expect(global.WebSocket).toHaveBeenCalledWith(mockUrl);
      expect(wrapper.getStatus().reconnectAttempts).toBe(1);
      
      // Restore the original WebSocket
      global.WebSocket = originalWebSocket;
    });
    
    test('stops reconnecting after max attempts', async () => {
      const connectPromise = wrapper.connect();
      
      // Mock the socket to simulate a successful connection
      const socket = wrapper.getSocket() as MockWebSocket;
      socket._simulateOpen();
      
      await connectPromise;
      
      // Spy on WebSocket constructor
      const originalWebSocket = global.WebSocket;
      global.WebSocket = jest.fn(() => {
        const socket = new MockWebSocket(mockUrl);
        // Immediately simulate error and close
        setTimeout(() => {
          socket._simulateError();
          socket.close();
        }, 10);
        return socket;
      }) as any;
      
      // Simulate initial connection error and close
      socket._simulateError();
      socket.close();
      
      // Fast-forward through multiple reconnect attempts
      for (let i = 0; i < 4; i++) {
        jest.advanceTimersByTime(150);
      }
      
      expect(global.WebSocket).toHaveBeenCalledTimes(3); // Max attempts
      expect(wrapper.getStatus().reconnectAttempts).toBe(3);
      expect(wrapper.getStatus().connected).toBe(false);
      
      // Restore the original WebSocket
      global.WebSocket = originalWebSocket;
    });
  });
  
  describe('health monitoring', () => {
    test('sends ping messages periodically', async () => {
      await wrapper.connect();
      
      // Mock the socket to simulate a successful connection
      const socket = wrapper.getSocket() as MockWebSocket;
      socket._simulateOpen();
      socket.send = jest.fn();
      
      // Fast-forward past the ping interval
      jest.advanceTimersByTime(30000);
      
      expect(socket.send).toHaveBeenCalledWith('ping');
    });
    
    test('updates health status when receiving pong', async () => {
      await wrapper.connect();
      
      // Mock the socket to simulate a successful connection
      const socket = wrapper.getSocket() as MockWebSocket;
      socket._simulateOpen();
      
      // Simulate sending a ping
      socket.send = jest.fn();
      jest.advanceTimersByTime(30000);
      
      // Simulate receiving a pong
      socket._simulateMessage('pong');
      
      expect(wrapper.getStatus().healthStatus).toBe('healthy');
      expect(wrapper.getStatus().latency).toBeDefined();
    });
  });
});
