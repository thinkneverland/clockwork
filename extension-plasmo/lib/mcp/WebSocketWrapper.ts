/**
 * WebSocketWrapper - Cross-browser compatible WebSocket implementation
 * 
 * Ensures proper cleanup of WebSocket resources to prevent memory leaks,
 * provides cross-browser compatibility, and enhances error handling.
 */

import { ConnectionStatus } from '../types/mcp';
import EventUtils from '../utils/EventUtils';

export type WebSocketEvent = 'open' | 'message' | 'close' | 'error' | 'reconnect' | 'reconnecting';
export type WebSocketEventListener = (event: any) => void;

export interface WebSocketWrapperOptions {
  autoReconnect?: boolean;
  reconnectInterval?: number;
  maxReconnectAttempts?: number;
  connectionTimeout?: number;
  debug?: boolean;
}

export class WebSocketWrapper {
  private socket: WebSocket | null = null;
  private url: string;
  private options: Required<WebSocketWrapperOptions>;
  private eventListeners: Map<WebSocketEvent, Set<WebSocketEventListener>> = new Map();
  private reconnectTimer: number | null = null;
  private reconnectAttempts: number = 0;
  private connectionTimeoutTimer: number | null = null;
  private lastPingTime: number = 0;
  private lastPongTime: number = 0;
  private isClosing: boolean = false;
  private browser = EventUtils.detectBrowser();
  
  private _status: ConnectionStatus = {
    connected: false,
    connecting: false,
    reconnectAttempts: 0,
    healthStatus: 'unhealthy'
  };

  constructor(url: string, options: WebSocketWrapperOptions = {}) {
    this.url = url;
    
    // Set default options
    this.options = {
      autoReconnect: options.autoReconnect ?? true,
      reconnectInterval: options.reconnectInterval ?? 2000,
      maxReconnectAttempts: options.maxReconnectAttempts ?? 10,
      connectionTimeout: options.connectionTimeout ?? 15000,
      debug: options.debug ?? false
    };
    
    // Initialize event listener sets
    ['open', 'message', 'close', 'error', 'reconnect', 'reconnecting'].forEach(event => {
      this.eventListeners.set(event as WebSocketEvent, new Set());
    });
    
    this.log('WebSocketWrapper initialized for', url);
  }

  /**
   * Connect to the WebSocket server
   */
  public async connect(): Promise<void> {
    if (this.socket && (this.socket.readyState === WebSocket.OPEN || this.socket.readyState === WebSocket.CONNECTING)) {
      this.log('Already connected or connecting');
      return;
    }
    
    this._status.connecting = true;
    this.isClosing = false;
    
    return new Promise<void>((resolve, reject) => {
      try {
        this.log('Connecting to', this.url);
        this.socket = new WebSocket(this.url);
        
        // Set up connection timeout
        this.connectionTimeoutTimer = window.setTimeout(() => {
          if (this.socket && this.socket.readyState !== WebSocket.OPEN) {
            this.log('Connection timeout');
            this._status.error = 'Connection timeout';
            this.socket.close();
            reject(new Error('Connection timeout'));
          }
        }, this.options.connectionTimeout);
        
        // Handle socket open event
        EventUtils.addEventListener(this.socket, 'open', (event) => {
          this.log('Connection opened');
          
          // Clear connection timeout
          if (this.connectionTimeoutTimer) {
            clearTimeout(this.connectionTimeoutTimer);
            this.connectionTimeoutTimer = null;
          }
          
          this._status.connected = true;
          this._status.connecting = false;
          this._status.lastConnected = Date.now();
          this._status.healthStatus = 'healthy';
          this._status.error = undefined;
          this.reconnectAttempts = 0;
          this._status.reconnectAttempts = 0;
          
          this.triggerEvent('open', event);
          resolve();
        });
        
        // Handle socket message event
        EventUtils.addEventListener(this.socket, 'message', (event) => {
          // Check for pong messages for health monitoring
          if (event.data === 'pong') {
            this.lastPongTime = Date.now();
            this._status.latency = this.lastPongTime - this.lastPingTime;
            return;
          }
          
          this.triggerEvent('message', event);
        });
        
        // Handle socket close event
        EventUtils.addEventListener(this.socket, 'close', (event) => {
          this.log('Connection closed', event);
          
          // Clear connection timeout
          if (this.connectionTimeoutTimer) {
            clearTimeout(this.connectionTimeoutTimer);
            this.connectionTimeoutTimer = null;
          }
          
          this._status.connected = false;
          this._status.connecting = false;
          this._status.healthStatus = 'unhealthy';
          
          // Attempt to reconnect if auto-reconnect is enabled and this wasn't a deliberate closure
          if (this.options.autoReconnect && !this.isClosing) {
            this.attemptReconnect();
          }
          
          this.triggerEvent('close', event);
          
          // Clean up this socket instance
          this.cleanupSocket();
        });
        
        // Handle socket error event
        EventUtils.addEventListener(this.socket, 'error', (event) => {
          this.log('Connection error', event);
          this._status.error = 'Connection error';
          this._status.healthStatus = 'unhealthy';
          this.triggerEvent('error', event);
          
          // Don't reject here as the close event will also fire
        });
      } catch (error) {
        this.log('Failed to create WebSocket', error);
        this._status.connecting = false;
        this._status.error = 'Failed to create WebSocket';
        this._status.healthStatus = 'unhealthy';
        reject(error);
      }
    });
  }

  /**
   * Disconnect from the WebSocket server
   */
  public async disconnect(): Promise<void> {
    if (!this.socket) {
      return;
    }
    
    this.isClosing = true;
    
    return new Promise<void>((resolve) => {
      if (this.socket) {
        // For already closed sockets, just clean up
        if (this.socket.readyState === WebSocket.CLOSED || this.socket.readyState === WebSocket.CLOSING) {
          this.cleanupSocket();
          resolve();
          return;
        }
        
        // Listen for the close event
        const onClose = () => {
          if (this.socket) {
            EventUtils.removeEventListener(this.socket, 'close', onClose);
          }
          this.cleanupSocket();
          resolve();
        };
        
        // Add one-time close listener
        EventUtils.addEventListener(this.socket, 'close', onClose);
        
        // Close the socket
        this.socket.close();
      } else {
        resolve();
      }
    });
  }

  /**
   * Send data through the WebSocket
   */
  public send(data: string | ArrayBuffer | Blob): boolean {
    if (!this.socket || this.socket.readyState !== WebSocket.OPEN) {
      this.log('Cannot send message, socket not open');
      return false;
    }
    
    try {
      this.socket.send(data);
      return true;
    } catch (error) {
      this.log('Failed to send message', error);
      return false;
    }
  }

  /**
   * Add event listener
   */
  public on(event: WebSocketEvent, listener: WebSocketEventListener): void {
    const listeners = this.eventListeners.get(event);
    if (listeners) {
      listeners.add(listener);
    }
  }

  /**
   * Remove event listener
   */
  public off(event: WebSocketEvent, listener: WebSocketEventListener): void {
    const listeners = this.eventListeners.get(event);
    if (listeners) {
      listeners.delete(listener);
    }
  }

  /**
   * Send a ping to check connection health
   */
  public ping(): void {
    if (!this.socket || this.socket.readyState !== WebSocket.OPEN) {
      return;
    }
    
    this.lastPingTime = Date.now();
    this.send('ping');
  }

  /**
   * Get the current connection status
   */
  public getStatus(): ConnectionStatus {
    return { ...this._status };
  }

  /**
   * Clean up all resources
   */
  public cleanup(): void {
    this.cleanupReconnectTimer();
    
    if (this.connectionTimeoutTimer) {
      clearTimeout(this.connectionTimeoutTimer);
      this.connectionTimeoutTimer = null;
    }
    
    this.cleanupSocket();
    
    // Clear all event listeners
    this.eventListeners.forEach(listeners => {
      listeners.clear();
    });
  }

  /**
   * Attempt to reconnect to the server
   */
  private attemptReconnect(): void {
    // Clean up any existing reconnect timer
    this.cleanupReconnectTimer();
    
    // Check if we've exceeded max reconnect attempts
    if (this.reconnectAttempts >= this.options.maxReconnectAttempts) {
      this.log('Maximum reconnect attempts reached');
      return;
    }
    
    this.reconnectAttempts++;
    this._status.reconnectAttempts = this.reconnectAttempts;
    this.triggerEvent('reconnecting', { attempts: this.reconnectAttempts });
    
    // Use exponential backoff for reconnect interval
    const delay = Math.min(
      30000, // Max 30 seconds
      this.options.reconnectInterval * Math.pow(1.5, this.reconnectAttempts - 1)
    );
    
    this.log(`Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`);
    
    // Set up reconnect timer
    this.reconnectTimer = window.setTimeout(() => {
      this.reconnectTimer = null;
      
      this.connect()
        .then(() => {
          this.log('Reconnected successfully');
          this.triggerEvent('reconnect', { attempts: this.reconnectAttempts });
        })
        .catch(error => {
          this.log('Reconnect failed', error);
          // The connection attempt will trigger reconnect on failure
        });
    }, delay);
  }

  /**
   * Clean up reconnect timer
   */
  private cleanupReconnectTimer(): void {
    if (this.reconnectTimer) {
      clearTimeout(this.reconnectTimer);
      this.reconnectTimer = null;
    }
  }

  /**
   * Clean up socket instance and its event listeners
   */
  private cleanupSocket(): void {
    if (!this.socket) {
      return;
    }
    
    // Remove all event listeners
    EventUtils.cleanupEventListeners(this.socket);
    
    // Clear the socket reference
    this.socket = null;
  }

  /**
   * Trigger an event to all listeners
   */
  private triggerEvent(event: WebSocketEvent, data: any): void {
    const listeners = this.eventListeners.get(event);
    if (listeners) {
      listeners.forEach(listener => {
        try {
          listener(data);
        } catch (error) {
          this.log(`Error in ${event} listener`, error);
        }
      });
    }
  }

  /**
   * Log debug messages if debug is enabled
   */
  private log(...args: any[]): void {
    if (this.options.debug) {
      console.log('[WebSocketWrapper]', ...args);
    }
  }
}
