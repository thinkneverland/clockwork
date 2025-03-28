/**
 * ConnectionHealthMonitor
 * 
 * Responsible for:
 * - WebSocket health monitoring with ping/pong mechanisms
 * - Zombie connection detection
 * - Automatic reconnection for unhealthy connections
 * - Cross-browser optimized settings
 * - Detailed connection metrics and diagnostics
 */

import { WebSocketWrapper } from './WebSocketWrapper';
import { EventEmitter } from '../utils/EventUtils';

export interface ConnectionHealthMetrics {
  connectionId: string;
  latency: number[];
  averageLatency: number;
  packetLoss: number;
  lastPingTime: number;
  lastPongTime: number;
  isZombie: boolean;
  reconnects: number;
  connectionStart: number;
  uptime: number;
  browser: string;
}

export interface ConnectionHealthSettings {
  pingInterval: number;       // Milliseconds between ping messages
  pongTimeout: number;        // Milliseconds to wait for pong response
  zombieThreshold: number;    // Consecutive failed pings to consider connection zombie
  reconnectDelay: number;     // Milliseconds to wait before reconnection attempt
  maxReconnects: number;      // Maximum number of reconnection attempts
  maxLatency: number;         // Maximum acceptable latency in milliseconds
  metricsWindow: number;      // Number of latency measurements to keep
}

export class ConnectionHealthMonitor extends EventEmitter {
  private connections: Map<string, {
    socket: WebSocketWrapper;
    metrics: ConnectionHealthMetrics;
    timers: {
      ping: number | null;
      pong: number | null;
      reconnect: number | null;
    };
    missedPongs: number;
    url: string;
    isReconnecting: boolean;
  }> = new Map();

  private settings: ConnectionHealthSettings;
  private browser: string;

  /**
   * Create a new connection health monitor
   */
  constructor(settings?: Partial<ConnectionHealthSettings>) {
    super();
    
    // Detect browser
    this.browser = this.detectBrowser();
    
    // Default settings
    const defaultSettings: ConnectionHealthSettings = {
      pingInterval: 30000,  // 30 seconds between pings
      pongTimeout: 5000,    // 5 seconds to wait for pong
      zombieThreshold: 3,   // 3 missed pongs = zombie
      reconnectDelay: 1000, // 1 second before reconnect
      maxReconnects: 5,     // 5 max reconnect attempts
      maxLatency: 2000,     // 2 seconds max latency
      metricsWindow: 10     // Keep last 10 latency measurements
    };
    
    // Optimize settings based on browser
    const browserOptimizedSettings = this.optimizeSettingsForBrowser(defaultSettings);
    
    // Apply user settings over browser-optimized defaults
    this.settings = {
      ...browserOptimizedSettings,
      ...settings
    };
  }
  
  /**
   * Register a WebSocket for health monitoring
   */
  public registerConnection(
    connectionId: string, 
    socket: WebSocketWrapper, 
    url: string
  ): void {
    // Initialize metrics
    const metrics: ConnectionHealthMetrics = {
      connectionId,
      latency: [],
      averageLatency: 0,
      packetLoss: 0,
      lastPingTime: 0,
      lastPongTime: 0,
      isZombie: false,
      reconnects: 0,
      connectionStart: Date.now(),
      uptime: 0,
      browser: this.browser
    };
    
    // Store connection data
    this.connections.set(connectionId, {
      socket,
      metrics,
      timers: {
        ping: null,
        pong: null,
        reconnect: null
      },
      missedPongs: 0,
      url,
      isReconnecting: false
    });
    
    // Set up message handlers
    socket.addEventListener('message', (event) => {
      this.handleMessage(connectionId, event);
    });
    
    socket.addEventListener('close', () => {
      this.handleClose(connectionId);
    });
    
    socket.addEventListener('error', () => {
      this.handleError(connectionId);
    });
    
    // Start monitoring
    this.startPingScheduler(connectionId);
    
    // Emit event
    this.emit('connection:registered', { connectionId, metrics });
  }
  
  /**
   * Unregister a connection from health monitoring
   */
  public unregisterConnection(connectionId: string): void {
    const connection = this.connections.get(connectionId);
    if (!connection) {
      return;
    }
    
    // Clear all timers
    this.clearAllTimers(connectionId);
    
    // Remove event listeners
    connection.socket.removeAllEventListeners();
    
    // Delete from connections map
    this.connections.delete(connectionId);
    
    // Emit event
    this.emit('connection:unregistered', { connectionId });
  }
  
  /**
   * Get health metrics for a specific connection
   */
  public getConnectionMetrics(connectionId: string): ConnectionHealthMetrics | null {
    const connection = this.connections.get(connectionId);
    if (!connection) {
      return null;
    }
    
    // Update uptime
    connection.metrics.uptime = Date.now() - connection.metrics.connectionStart;
    
    return { ...connection.metrics };
  }
  
  /**
   * Get health metrics for all connections
   */
  public getAllConnectionMetrics(): ConnectionHealthMetrics[] {
    return Array.from(this.connections.values()).map(connection => {
      // Update uptime
      connection.metrics.uptime = Date.now() - connection.metrics.connectionStart;
      return { ...connection.metrics };
    });
  }
  
  /**
   * Force check for zombie connections
   */
  public checkZombieConnections(): void {
    const now = Date.now();
    
    for (const [connectionId, connection] of this.connections.entries()) {
      if (connection.socket.getReadyState() !== WebSocket.OPEN) {
        continue;
      }
      
      // If no pong received for a while, consider it a zombie
      if (connection.metrics.lastPongTime > 0 && 
          now - connection.metrics.lastPongTime > this.settings.pingInterval * this.settings.zombieThreshold) {
        connection.metrics.isZombie = true;
        this.emit('connection:zombie', { connectionId, metrics: connection.metrics });
        
        // Attempt reconnection
        this.reconnectIfNeeded(connectionId);
      }
    }
  }
  
  /**
   * Start the ping scheduler for a connection
   */
  private startPingScheduler(connectionId: string): void {
    const connection = this.connections.get(connectionId);
    if (!connection) {
      return;
    }
    
    // Clear any existing ping timer
    if (connection.timers.ping !== null) {
      clearInterval(connection.timers.ping);
    }
    
    // Set up ping timer
    connection.timers.ping = window.setInterval(() => {
      this.sendPing(connectionId);
    }, this.settings.pingInterval);
  }
  
  /**
   * Send a ping message
   */
  private sendPing(connectionId: string): void {
    const connection = this.connections.get(connectionId);
    if (!connection || connection.socket.getReadyState() !== WebSocket.OPEN) {
      return;
    }
    
    // Create ping payload with timestamp
    const pingTime = Date.now();
    const pingPayload = JSON.stringify({
      type: 'ping',
      time: pingTime
    });
    
    // Send ping
    try {
      connection.socket.send(pingPayload);
      connection.metrics.lastPingTime = pingTime;
      
      // Set pong timeout
      this.setPongTimeout(connectionId);
      
      // Emit event
      this.emit('connection:ping', { connectionId, time: pingTime });
    } catch (error) {
      console.error(`Error sending ping to ${connectionId}:`, error);
      this.handleError(connectionId);
    }
  }
  
  /**
   * Set timeout for pong response
   */
  private setPongTimeout(connectionId: string): void {
    const connection = this.connections.get(connectionId);
    if (!connection) {
      return;
    }
    
    // Clear any existing pong timer
    if (connection.timers.pong !== null) {
      clearTimeout(connection.timers.pong);
    }
    
    // Set pong timeout
    connection.timers.pong = window.setTimeout(() => {
      this.handlePongTimeout(connectionId);
    }, this.settings.pongTimeout);
  }
  
  /**
   * Handle pong timeout
   */
  private handlePongTimeout(connectionId: string): void {
    const connection = this.connections.get(connectionId);
    if (!connection) {
      return;
    }
    
    // Increment missed pongs counter
    connection.missedPongs++;
    
    // Update packet loss metric
    const totalExpectedPongs = connection.metrics.latency.length + connection.missedPongs;
    connection.metrics.packetLoss = connection.missedPongs / Math.max(1, totalExpectedPongs);
    
    // Check if connection is zombie
    if (connection.missedPongs >= this.settings.zombieThreshold) {
      connection.metrics.isZombie = true;
      
      // Emit zombie event
      this.emit('connection:zombie', { 
        connectionId, 
        metrics: connection.metrics 
      });
      
      // Attempt to reconnect
      this.reconnectIfNeeded(connectionId);
    } else {
      // Emit missed pong event
      this.emit('connection:missed_pong', { 
        connectionId, 
        missedPongs: connection.missedPongs,
        metrics: connection.metrics
      });
    }
  }
  
  /**
   * Handle incoming WebSocket message
   */
  private handleMessage(
    connectionId: string, 
    event: MessageEvent
  ): void {
    const connection = this.connections.get(connectionId);
    if (!connection) {
      return;
    }
    
    try {
      // Parse message
      const message = JSON.parse(event.data);
      
      // Check if it's a pong response
      if (message.type === 'pong' && typeof message.time === 'number') {
        this.handlePong(connectionId, message.time);
      }
    } catch (error) {
      // Not JSON or not our protocol message, ignore
    }
  }
  
  /**
   * Handle pong response
   */
  private handlePong(connectionId: string, pingTime: number): void {
    const connection = this.connections.get(connectionId);
    if (!connection) {
      return;
    }
    
    // Clear pong timeout
    if (connection.timers.pong !== null) {
      clearTimeout(connection.timers.pong);
      connection.timers.pong = null;
    }
    
    // Calculate latency
    const pongTime = Date.now();
    connection.metrics.lastPongTime = pongTime;
    const latency = pongTime - pingTime;
    
    // Store latency in the window
    connection.metrics.latency.push(latency);
    if (connection.metrics.latency.length > this.settings.metricsWindow) {
      connection.metrics.latency.shift();
    }
    
    // Calculate average latency
    const totalLatency = connection.metrics.latency.reduce((sum, latency) => sum + latency, 0);
    connection.metrics.averageLatency = totalLatency / connection.metrics.latency.length;
    
    // Reset missed pongs counter
    connection.missedPongs = 0;
    
    // Reset zombie status if it was marked as zombie
    if (connection.metrics.isZombie) {
      connection.metrics.isZombie = false;
      this.emit('connection:recovered', { connectionId, metrics: connection.metrics });
    }
    
    // Emit pong event
    this.emit('connection:pong', {
      connectionId,
      latency,
      metrics: connection.metrics
    });
    
    // Check if latency is too high
    if (latency > this.settings.maxLatency) {
      this.emit('connection:high_latency', {
        connectionId,
        latency,
        threshold: this.settings.maxLatency,
        metrics: connection.metrics
      });
    }
  }
  
  /**
   * Handle WebSocket close event
   */
  private handleClose(connectionId: string): void {
    this.clearAllTimers(connectionId);
    
    const connection = this.connections.get(connectionId);
    if (!connection) {
      return;
    }
    
    // Emit event
    this.emit('connection:closed', { connectionId, metrics: connection.metrics });
    
    // Attempt to reconnect
    this.reconnectIfNeeded(connectionId);
  }
  
  /**
   * Handle WebSocket error event
   */
  private handleError(connectionId: string): void {
    const connection = this.connections.get(connectionId);
    if (!connection) {
      return;
    }
    
    // Emit event
    this.emit('connection:error', { connectionId, metrics: connection.metrics });
    
    // Attempt to reconnect
    this.reconnectIfNeeded(connectionId);
  }
  
  /**
   * Reconnect a connection if needed
   */
  private reconnectIfNeeded(connectionId: string): void {
    const connection = this.connections.get(connectionId);
    if (!connection || connection.isReconnecting) {
      return;
    }
    
    // Check if we've exceeded max reconnects
    if (connection.metrics.reconnects >= this.settings.maxReconnects) {
      this.emit('connection:max_reconnects', {
        connectionId,
        reconnects: connection.metrics.reconnects,
        maxReconnects: this.settings.maxReconnects
      });
      return;
    }
    
    // Set reconnecting flag
    connection.isReconnecting = true;
    
    // Clear any existing reconnect timer
    if (connection.timers.reconnect !== null) {
      clearTimeout(connection.timers.reconnect);
    }
    
    // Set reconnect timer
    connection.timers.reconnect = window.setTimeout(() => {
      this.performReconnect(connectionId);
    }, this.settings.reconnectDelay);
    
    // Emit event
    this.emit('connection:reconnect_scheduled', {
      connectionId,
      delay: this.settings.reconnectDelay,
      attempt: connection.metrics.reconnects + 1
    });
  }
  
  /**
   * Perform the reconnection
   */
  private performReconnect(connectionId: string): void {
    const connection = this.connections.get(connectionId);
    if (!connection) {
      return;
    }
    
    // Clear the reconnect timer
    if (connection.timers.reconnect !== null) {
      clearTimeout(connection.timers.reconnect);
      connection.timers.reconnect = null;
    }
    
    // Increment reconnect counter
    connection.metrics.reconnects++;
    
    // Create new WebSocket
    try {
      // Close the old socket if it's still open
      if (connection.socket.getReadyState() === WebSocket.OPEN || 
          connection.socket.getReadyState() === WebSocket.CONNECTING) {
        connection.socket.close();
      }
      
      // Create new socket
      const newSocket = new WebSocketWrapper(connection.url);
      
      // Set up event handlers
      newSocket.addEventListener('open', () => {
        // Update connection data
        connection.socket = newSocket;
        connection.isReconnecting = false;
        connection.missedPongs = 0;
        
        // Reset metrics
        connection.metrics.isZombie = false;
        connection.metrics.latency = [];
        connection.metrics.averageLatency = 0;
        connection.metrics.packetLoss = 0;
        
        // Start monitoring
        this.startPingScheduler(connectionId);
        
        // Emit event
        this.emit('connection:reconnected', {
          connectionId,
          reconnects: connection.metrics.reconnects,
          metrics: connection.metrics
        });
      });
      
      newSocket.addEventListener('error', () => {
        connection.isReconnecting = false;
        this.handleError(connectionId);
      });
      
      // Set up new message handlers
      newSocket.addEventListener('message', (event) => {
        this.handleMessage(connectionId, event);
      });
      
      newSocket.addEventListener('close', () => {
        connection.isReconnecting = false;
        this.handleClose(connectionId);
      });
    } catch (error) {
      console.error(`Error reconnecting to ${connectionId}:`, error);
      connection.isReconnecting = false;
      
      // Emit event
      this.emit('connection:reconnect_failed', {
        connectionId,
        reconnects: connection.metrics.reconnects,
        error
      });
      
      // Try again
      this.reconnectIfNeeded(connectionId);
    }
  }
  
  /**
   * Clear all timers for a connection
   */
  private clearAllTimers(connectionId: string): void {
    const connection = this.connections.get(connectionId);
    if (!connection) {
      return;
    }
    
    // Clear ping timer
    if (connection.timers.ping !== null) {
      clearInterval(connection.timers.ping);
      connection.timers.ping = null;
    }
    
    // Clear pong timer
    if (connection.timers.pong !== null) {
      clearTimeout(connection.timers.pong);
      connection.timers.pong = null;
    }
    
    // Clear reconnect timer
    if (connection.timers.reconnect !== null) {
      clearTimeout(connection.timers.reconnect);
      connection.timers.reconnect = null;
    }
  }
  
  /**
   * Detect the current browser
   */
  private detectBrowser(): string {
    const userAgent = navigator.userAgent;
    
    if (userAgent.indexOf('Chrome') > -1) {
      return 'chrome';
    } else if (userAgent.indexOf('Firefox') > -1) {
      return 'firefox';
    } else if (userAgent.indexOf('Edge') > -1) {
      return 'edge';
    } else if (userAgent.indexOf('Safari') > -1) {
      return 'safari';
    } else {
      return 'unknown';
    }
  }
  
  /**
   * Optimize settings based on the detected browser
   */
  private optimizeSettingsForBrowser(settings: ConnectionHealthSettings): ConnectionHealthSettings {
    const optimizedSettings = { ...settings };
    
    switch (this.browser) {
      case 'firefox':
        // Firefox needs longer timeouts for background tabs
        optimizedSettings.pingInterval = 45000; // 45 seconds
        optimizedSettings.pongTimeout = 8000;   // 8 seconds
        break;
        
      case 'edge':
        // Edge has stricter throttling for background tabs
        optimizedSettings.pingInterval = 60000; // 1 minute
        optimizedSettings.pongTimeout = 10000;  // 10 seconds
        optimizedSettings.zombieThreshold = 2;  // More aggressive zombie detection
        break;
        
      case 'safari':
        // Safari has the strictest background tab throttling
        optimizedSettings.pingInterval = 90000; // 1.5 minutes
        optimizedSettings.pongTimeout = 15000;  // 15 seconds
        optimizedSettings.reconnectDelay = 2000; // 2 seconds
        break;
        
      default:
        // Chrome and others - use defaults
        break;
    }
    
    return optimizedSettings;
  }
  
  /**
   * Update connection health settings
   */
  public updateSettings(settings: Partial<ConnectionHealthSettings>): void {
    this.settings = {
      ...this.settings,
      ...settings
    };
    
    // Restart ping schedulers for all connections
    for (const connectionId of this.connections.keys()) {
      this.startPingScheduler(connectionId);
    }
  }
  
  /**
   * Clean up resources
   */
  public cleanup(): void {
    // Clear all timers and unregister all connections
    for (const connectionId of this.connections.keys()) {
      this.unregisterConnection(connectionId);
    }
    
    // Remove all event listeners
    this.removeAllEventListeners();
  }
}

export default ConnectionHealthMonitor;
