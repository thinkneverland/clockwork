/**
 * ConnectionRegistry - Track and monitor active WebSocket connections
 * 
 * Maintains a registry of all active WebSocket connections and monitors their health.
 * Provides automatic cleanup of zombie connections and connection pooling for better performance.
 */

import { WebSocketWrapper } from './WebSocketWrapper';
import { ConnectionStatus } from '../types/mcp';

export interface ConnectionEntry {
  id: string;
  connection: WebSocketWrapper;
  url: string;
  createdAt: number;
  lastActiveAt: number;
  tabId?: number;
  meta?: Record<string, any>;
}

export class ConnectionRegistry {
  private connections: Map<string, ConnectionEntry> = new Map();
  private monitorInterval: number | null = null;
  private healthCheckInterval: number = 30000; // 30 seconds
  private cleanupThreshold: number = 5 * 60 * 1000; // 5 minutes
  private debug: boolean = false;
  
  constructor(options: { 
    healthCheckInterval?: number;
    cleanupThreshold?: number;
    debug?: boolean;
  } = {}) {
    this.healthCheckInterval = options.healthCheckInterval ?? this.healthCheckInterval;
    this.cleanupThreshold = options.cleanupThreshold ?? this.cleanupThreshold;
    this.debug = options.debug ?? this.debug;
    
    this.log('ConnectionRegistry initialized');
  }

  /**
   * Register a new connection
   */
  public registerConnection(
    id: string, 
    connection: WebSocketWrapper,
    meta: Record<string, any> = {}
  ): void {
    const now = Date.now();
    
    this.connections.set(id, {
      id,
      connection,
      url: meta.url ?? 'unknown',
      createdAt: now,
      lastActiveAt: now,
      tabId: meta.tabId,
      meta
    });
    
    this.log(`Registered connection ${id}`);
    
    // Start monitoring if this is the first connection
    if (this.connections.size === 1) {
      this.startMonitoring();
    }
  }

  /**
   * Unregister a connection
   */
  public unregisterConnection(id: string): boolean {
    const entry = this.connections.get(id);
    if (!entry) {
      return false;
    }
    
    this.log(`Unregistering connection ${id}`);
    
    // Clean up the connection
    entry.connection.cleanup();
    
    // Remove from registry
    this.connections.delete(id);
    
    // Stop monitoring if no more connections
    if (this.connections.size === 0) {
      this.stopMonitoring();
    }
    
    return true;
  }

  /**
   * Get a connection by ID
   */
  public getConnection(id: string): WebSocketWrapper | null {
    const entry = this.connections.get(id);
    return entry ? entry.connection : null;
  }

  /**
   * Get all connection entries
   */
  public getAllConnections(): ConnectionEntry[] {
    return Array.from(this.connections.values());
  }

  /**
   * Get connection statuses for all connections
   */
  public getConnectionStatuses(): Record<string, ConnectionStatus> {
    const statuses: Record<string, ConnectionStatus> = {};
    
    this.connections.forEach((entry, id) => {
      statuses[id] = entry.connection.getStatus();
    });
    
    return statuses;
  }

  /**
   * Update connection last activity time
   */
  public updateConnectionActivity(id: string): boolean {
    const entry = this.connections.get(id);
    if (!entry) {
      return false;
    }
    
    entry.lastActiveAt = Date.now();
    return true;
  }

  /**
   * Start monitoring connections
   */
  public startMonitoring(): void {
    if (this.monitorInterval) {
      return;
    }
    
    this.log('Starting connection monitoring');
    
    this.monitorInterval = window.setInterval(() => {
      this.monitorConnections();
    }, this.healthCheckInterval);
    
    // Run an initial check
    this.monitorConnections();
  }

  /**
   * Stop monitoring connections
   */
  public stopMonitoring(): void {
    if (this.monitorInterval) {
      clearInterval(this.monitorInterval);
      this.monitorInterval = null;
      this.log('Stopped connection monitoring');
    }
  }

  /**
   * Clean up all connections and stop monitoring
   */
  public cleanup(): void {
    this.log('Cleaning up all connections');
    
    this.stopMonitoring();
    
    // Clean up all connections
    this.connections.forEach((entry) => {
      entry.connection.cleanup();
    });
    
    this.connections.clear();
  }

  /**
   * Monitor connections for health and cleanup zombies
   */
  private monitorConnections(): void {
    const now = Date.now();
    const connectionsToRemove: string[] = [];
    
    this.log(`Monitoring ${this.connections.size} connections`);
    
    this.connections.forEach((entry, id) => {
      const status = entry.connection.getStatus();
      
      // Check if this is a zombie connection (inactive for too long)
      const inactiveTime = now - entry.lastActiveAt;
      if (inactiveTime > this.cleanupThreshold) {
        this.log(`Connection ${id} inactive for ${inactiveTime}ms, marking for removal`);
        connectionsToRemove.push(id);
        return;
      }
      
      // For connected sockets, send ping to check health
      if (status.connected) {
        entry.connection.ping();
      }
      // For disconnected sockets that should be connected, attempt reconnect
      else if (!status.connecting && status.healthStatus !== 'healthy') {
        this.log(`Connection ${id} unhealthy, attempting reconnect`);
        entry.connection.connect().catch(error => {
          this.log(`Failed to reconnect ${id}`, error);
        });
      }
    });
    
    // Remove zombie connections
    connectionsToRemove.forEach(id => {
      this.unregisterConnection(id);
    });
  }

  /**
   * Log debug messages if debug is enabled
   */
  private log(...args: any[]): void {
    if (this.debug) {
      console.log('[ConnectionRegistry]', ...args);
    }
  }
}
