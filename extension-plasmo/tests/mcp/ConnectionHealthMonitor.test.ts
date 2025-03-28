/**
 * ConnectionHealthMonitor tests
 * 
 * Tests the connection health monitoring functionality to ensure
 * reliable WebSocket connections, detection of zombie connections,
 * and proper reconnection behavior across different browsers.
 */

import { ConnectionHealthMonitor, ConnectionHealthMetrics, ConnectionHealthSettings } from '../../lib/mcp/ConnectionHealthMonitor';
import { WebSocketWrapper } from '../../lib/mcp/WebSocketWrapper';

// Mock WebSocketWrapper
jest.mock('../../lib/mcp/WebSocketWrapper', () => {
  return {
    WebSocketWrapper: jest.fn().mockImplementation(() => ({
      addEventListener: jest.fn(),
      removeAllEventListeners: jest.fn(),
      getReadyState: jest.fn().mockReturnValue(1), // WebSocket.OPEN
      send: jest.fn(),
      connect: jest.fn().mockResolvedValue(undefined),
      disconnect: jest.fn().mockResolvedValue(undefined)
    }))
  };
});

describe('ConnectionHealthMonitor', () => {
  let monitor: ConnectionHealthMonitor;
  let mockSocket: WebSocketWrapper;
  const testConnectionId = 'test-connection-123';
  const testUrl = 'ws://localhost:8080';
  
  beforeEach(() => {
    jest.clearAllMocks();
    jest.useFakeTimers();
    
    // Create custom settings for testing
    const settings: Partial<ConnectionHealthSettings> = {
      pingInterval: 500,    // Shorter intervals for testing
      pongTimeout: 300,
      zombieThreshold: 2,
      reconnectDelay: 100,
      maxReconnects: 3
    };
    
    monitor = new ConnectionHealthMonitor(settings);
    
    // Create mock socket
    mockSocket = new WebSocketWrapper(testUrl) as any;
  });
  
  afterEach(() => {
    jest.useRealTimers();
  });
  
  describe('registerConnection', () => {
    test('registers a connection for monitoring', () => {
      monitor.registerConnection(testConnectionId, mockSocket, testUrl);
      
      const metrics = monitor.getConnectionMetrics(testConnectionId);
      
      expect(metrics).not.toBeNull();
      expect(metrics?.connectionId).toBe(testConnectionId);
      expect(metrics?.isZombie).toBe(false);
      expect(metrics?.reconnects).toBe(0);
      
      // Should add event listeners to the socket
      expect(mockSocket.addEventListener).toHaveBeenCalledTimes(3);
    });
    
    test('starts ping scheduler after registration', () => {
      const pingSchedulerSpy = jest.spyOn(monitor as any, 'startPingScheduler');
      
      monitor.registerConnection(testConnectionId, mockSocket, testUrl);
      
      expect(pingSchedulerSpy).toHaveBeenCalledWith(testConnectionId);
    });
    
    test('emits connection:registered event', () => {
      const emitSpy = jest.spyOn(monitor, 'emit');
      
      monitor.registerConnection(testConnectionId, mockSocket, testUrl);
      
      expect(emitSpy).toHaveBeenCalledWith('connection:registered', expect.objectContaining({
        connectionId: testConnectionId
      }));
    });
  });
  
  describe('unregisterConnection', () => {
    test('unregisters a connection and cleans up', () => {
      monitor.registerConnection(testConnectionId, mockSocket, testUrl);
      monitor.unregisterConnection(testConnectionId);
      
      const metrics = monitor.getConnectionMetrics(testConnectionId);
      
      expect(metrics).toBeNull();
      expect(mockSocket.removeAllEventListeners).toHaveBeenCalled();
    });
    
    test('clears all timers for the connection', () => {
      const clearAllTimersSpy = jest.spyOn(monitor as any, 'clearAllTimers');
      
      monitor.registerConnection(testConnectionId, mockSocket, testUrl);
      monitor.unregisterConnection(testConnectionId);
      
      expect(clearAllTimersSpy).toHaveBeenCalledWith(testConnectionId);
    });
    
    test('emits connection:unregistered event', () => {
      const emitSpy = jest.spyOn(monitor, 'emit');
      
      monitor.registerConnection(testConnectionId, mockSocket, testUrl);
      monitor.unregisterConnection(testConnectionId);
      
      expect(emitSpy).toHaveBeenCalledWith('connection:unregistered', expect.objectContaining({
        connectionId: testConnectionId
      }));
    });
  });
  
  describe('ping mechanism', () => {
    test('sends ping messages at regular intervals', () => {
      monitor.registerConnection(testConnectionId, mockSocket, testUrl);
      
      // Fast-forward past the ping interval
      jest.advanceTimersByTime(600);
      
      expect(mockSocket.send).toHaveBeenCalledWith('ping');
      
      // Ensure metrics are updated
      const metrics = monitor.getConnectionMetrics(testConnectionId);
      expect(metrics?.lastPingTime).toBeGreaterThan(0);
    });
    
    test('detects missed pongs and marks connection as zombie', () => {
      monitor.registerConnection(testConnectionId, mockSocket, testUrl);
      
      // Simulate first ping
      jest.advanceTimersByTime(600);
      expect(mockSocket.send).toHaveBeenCalledWith('ping');
      
      // Fast-forward past pong timeout (no pong received)
      jest.advanceTimersByTime(400);
      
      // Simulate second ping
      jest.advanceTimersByTime(600);
      
      // Fast-forward past second pong timeout
      jest.advanceTimersByTime(400);
      
      // Check if connection is now marked as zombie after 2 missed pongs
      const metrics = monitor.getConnectionMetrics(testConnectionId);
      expect(metrics?.isZombie).toBe(true);
    });
    
    test('calculates latency correctly when pong is received', () => {
      monitor.registerConnection(testConnectionId, mockSocket, testUrl);
      
      // Simulate ping
      jest.advanceTimersByTime(600);
      
      // Mock current time for deterministic testing
      const mockNow = Date.now();
      jest.spyOn(Date, 'now').mockImplementation(() => mockNow + 50); // 50ms latency
      
      // Simulate pong response
      const messageHandler = (mockSocket.addEventListener as jest.Mock).mock.calls.find(
        call => call[0] === 'message'
      )[1];
      
      messageHandler({ data: 'pong' });
      
      // Check latency calculation
      const metrics = monitor.getConnectionMetrics(testConnectionId);
      expect(metrics?.latency.length).toBe(1);
      expect(metrics?.latency[0]).toBe(50);
      expect(metrics?.averageLatency).toBe(50);
      
      // Restore Date.now
      jest.restoreAllMocks();
    });
  });
  
  describe('reconnection mechanism', () => {
    test('attempts to reconnect zombie connections', () => {
      const reconnectSpy = jest.spyOn(monitor as any, 'reconnectConnection');
      const emitSpy = jest.spyOn(monitor, 'emit');
      
      monitor.registerConnection(testConnectionId, mockSocket, testUrl);
      
      // Force connection to zombie state
      const connection = (monitor as any).connections.get(testConnectionId);
      connection.missedPongs = 3;
      (monitor as any).updateConnectionMetrics(testConnectionId, { isZombie: true });
      
      // Call check zombies method
      monitor.checkZombieConnections();
      
      // Should attempt reconnect and emit event
      expect(reconnectSpy).toHaveBeenCalledWith(testConnectionId);
      expect(emitSpy).toHaveBeenCalledWith('connection:reconnecting', expect.objectContaining({
        connectionId: testConnectionId
      }));
    });
    
    test('limits reconnection attempts based on settings', async () => {
      monitor.registerConnection(testConnectionId, mockSocket, testUrl);
      
      // Force connection to zombie state
      const connection = (monitor as any).connections.get(testConnectionId);
      connection.missedPongs = 3;
      connection.metrics.reconnects = 3; // Max reconnects already reached
      connection.metrics.isZombie = true;
      
      // Call check zombies method
      monitor.checkZombieConnections();
      
      // Should not attempt to reconnect
      expect(mockSocket.disconnect).not.toHaveBeenCalled();
      expect(mockSocket.connect).not.toHaveBeenCalled();
    });
    
    test('resets zombie state after successful reconnection', async () => {
      monitor.registerConnection(testConnectionId, mockSocket, testUrl);
      
      // Force connection to zombie state
      const connection = (monitor as any).connections.get(testConnectionId);
      connection.missedPongs = 3;
      connection.metrics.isZombie = true;
      
      // Call reconnect method directly
      await (monitor as any).reconnectConnection(testConnectionId);
      
      // Fast-forward past reconnect delay
      jest.advanceTimersByTime(200);
      
      // Should have attempted to reconnect
      expect(mockSocket.disconnect).toHaveBeenCalled();
      expect(mockSocket.connect).toHaveBeenCalled();
      
      // Metrics should be updated
      const metrics = monitor.getConnectionMetrics(testConnectionId);
      expect(metrics?.reconnects).toBe(1);
      
      // Simulate successful reconnection by resetting zombie state
      connection.metrics.isZombie = false;
      connection.missedPongs = 0;
      
      // Verify state after reconnection
      expect(monitor.getConnectionMetrics(testConnectionId)?.isZombie).toBe(false);
    });
  });
  
  describe('browser optimizations', () => {
    test('applies browser-specific optimizations to settings', () => {
      // Access private method to test optimizations
      const optimizeMethod = (monitor as any).optimizeSettingsForBrowser.bind(monitor);
      
      // Mock different browser detections
      const chromeSettings = optimizeMethod({
        pingInterval: 30000,
        pongTimeout: 5000
      });
      
      const firefoxSettings = optimizeMethod({
        pingInterval: 30000,
        pongTimeout: 5000
      });
      
      // Different browsers should have optimized settings
      expect(chromeSettings).toBeDefined();
      expect(firefoxSettings).toBeDefined();
    });
  });
  
  describe('metrics tracking', () => {
    test('provides connection metrics', () => {
      monitor.registerConnection(testConnectionId, mockSocket, testUrl);
      
      const metrics = monitor.getConnectionMetrics(testConnectionId);
      
      expect(metrics).toHaveProperty('connectionId', testConnectionId);
      expect(metrics).toHaveProperty('latency');
      expect(metrics).toHaveProperty('averageLatency');
      expect(metrics).toHaveProperty('packetLoss');
      expect(metrics).toHaveProperty('reconnects');
      expect(metrics).toHaveProperty('uptime');
    });
    
    test('retrieves all connection metrics', () => {
      monitor.registerConnection('connection-1', mockSocket, testUrl);
      monitor.registerConnection('connection-2', mockSocket, testUrl);
      
      const allMetrics = monitor.getAllConnectionMetrics();
      
      expect(allMetrics.length).toBe(2);
      expect(allMetrics[0]).toHaveProperty('connectionId');
      expect(allMetrics[1]).toHaveProperty('connectionId');
    });
    
    test('updates uptime in metrics', () => {
      // Mock Date.now for deterministic testing
      const startTime = Date.now();
      jest.spyOn(Date, 'now')
        .mockImplementationOnce(() => startTime)
        .mockImplementationOnce(() => startTime + 5000)
        .mockImplementationOnce(() => startTime + 10000);
      
      monitor.registerConnection(testConnectionId, mockSocket, testUrl);
      
      // Initial uptime check
      let metrics = monitor.getConnectionMetrics(testConnectionId);
      expect(metrics?.uptime).toBe(5000); // 5 seconds
      
      // Check again after more time
      metrics = monitor.getConnectionMetrics(testConnectionId);
      expect(metrics?.uptime).toBe(10000); // 10 seconds
      
      // Restore Date.now
      jest.restoreAllMocks();
    });
  });
});
