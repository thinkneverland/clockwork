/**
 * McpClient - MCP Protocol client implementation
 * 
 * Handles communication with the Tapped server using the MCP protocol.
 * Includes connection management, message handling, and health monitoring.
 */

import {
  ConnectionStatus,
  McpClientInterface,
  McpConnectionOptions,
  McpEventListener,
  McpMessage
} from '../types/mcp';
import { WebSocketWrapper, WebSocketEvent } from './WebSocketWrapper';

export class McpClient implements McpClientInterface {
  private socket: WebSocketWrapper;
  private messageQueue: McpMessage[] = [];
  private eventListeners: Map<string, Set<McpEventListener>> = new Map();
  private healthCheckInterval: number | null = null;
  private queueProcessInterval: number | null = null;
  private messageCounter: number = 0;
  private messageCallbacks: Map<string, (response: any) => void> = new Map();
  private options: Required<McpConnectionOptions>;
  private connected: boolean = false;
  private tabId: number | undefined;
  
  constructor(options: McpConnectionOptions) {
    this.options = {
      url: options.url,
      autoReconnect: options.autoReconnect ?? true,
      reconnectInterval: options.reconnectInterval ?? 2000,
      maxReconnectAttempts: options.maxReconnectAttempts ?? 10,
      healthCheckInterval: options.healthCheckInterval ?? 30000,
      connectionTimeout: options.connectionTimeout ?? 15000,
      debug: options.debug ?? false
    };
    
    // Create the WebSocket wrapper
    this.socket = new WebSocketWrapper(this.options.url, {
      autoReconnect: this.options.autoReconnect,
      reconnectInterval: this.options.reconnectInterval,
      maxReconnectAttempts: this.options.maxReconnectAttempts,
      connectionTimeout: this.options.connectionTimeout,
      debug: this.options.debug
    });
    
    // Set up socket event handlers
    this.setupSocketEventHandlers();
    
    // Get the tab ID if possible
    this.detectTabId();
    
    this.log('McpClient initialized');
  }

  /**
   * Connect to the MCP server
   */
  public async connect(): Promise<void> {
    try {
      this.log('Connecting to MCP server');
      await this.socket.connect();
      this.connected = true;
      
      // Start health check and queue processing after connection
      this.startHealthCheck();
      this.startQueueProcessing();
      
      // Send handshake message
      await this.sendHandshake();
      
      this.log('Connected to MCP server');
    } catch (error) {
      this.log('Failed to connect to MCP server', error);
      this.connected = false;
      throw error;
    }
  }

  /**
   * Disconnect from the MCP server
   */
  public async disconnect(): Promise<void> {
    this.stopHealthCheck();
    this.stopQueueProcessing();
    
    // Clear message queue
    this.messageQueue = [];
    
    this.connected = false;
    await this.socket.disconnect();
    this.log('Disconnected from MCP server');
  }

  /**
   * Send a message to the MCP server
   */
  public async send(message: McpMessage): Promise<void> {
    // Add default metadata
    if (!message.meta) {
      message.meta = {};
    }
    
    message.meta.timestamp = message.meta.timestamp ?? Date.now();
    message.meta.tabId = message.meta.tabId ?? this.tabId;
    
    // If we're not connected, queue the message
    if (!this.connected || !this.socket.send(JSON.stringify(message))) {
      this.messageQueue.push(message);
      this.log('Queued message', message);
    } else {
      this.log('Sent message', message);
    }
  }

  /**
   * Send a message and wait for a response
   */
  public async sendAndWait<T>(message: McpMessage, timeout: number = 10000): Promise<T> {
    return new Promise((resolve, reject) => {
      // Generate a unique ID for this message
      const messageId = `msg_${++this.messageCounter}`;
      message.meta = {
        ...message.meta,
        messageId
      };
      
      // Set up response timeout
      const timeoutId = window.setTimeout(() => {
        this.messageCallbacks.delete(messageId);
        reject(new Error(`Timeout waiting for response to message ${messageId}`));
      }, timeout);
      
      // Register callback for the response
      this.messageCallbacks.set(messageId, (response: any) => {
        clearTimeout(timeoutId);
        this.messageCallbacks.delete(messageId);
        resolve(response as T);
      });
      
      // Send the message
      this.send(message).catch(error => {
        clearTimeout(timeoutId);
        this.messageCallbacks.delete(messageId);
        reject(error);
      });
    });
  }

  /**
   * Register an event listener
   */
  public on(event: string, callback: McpEventListener): void {
    let listeners = this.eventListeners.get(event);
    if (!listeners) {
      listeners = new Set();
      this.eventListeners.set(event, listeners);
    }
    listeners.add(callback);
  }

  /**
   * Remove an event listener
   */
  public off(event: string, callback: McpEventListener): void {
    const listeners = this.eventListeners.get(event);
    if (listeners) {
      listeners.delete(callback);
      if (listeners.size === 0) {
        this.eventListeners.delete(event);
      }
    }
  }

  /**
   * Get the current connection status
   */
  public getStatus(): ConnectionStatus {
    return {
      ...this.socket.getStatus(),
      connected: this.connected
    };
  }

  /**
   * Clean up all resources
   */
  public cleanup(): void {
    this.stopHealthCheck();
    this.stopQueueProcessing();
    this.socket.cleanup();
    
    // Clear all event listeners
    this.eventListeners.clear();
    this.messageCallbacks.clear();
    this.messageQueue = [];
    
    this.log('McpClient cleaned up');
  }

  /**
   * Set up socket event handlers
   */
  private setupSocketEventHandlers(): void {
    // Handle socket open event
    this.socket.on('open', () => {
      this.triggerEvent('connect', {});
      
      // Process any queued messages
      this.processMessageQueue();
    });
    
    // Handle socket message event
    this.socket.on('message', (event) => {
      try {
        const data = JSON.parse(event.data);
        this.log('Received message', data);
        
        // Check if this is a response to a specific message
        if (data.meta?.responseToId && this.messageCallbacks.has(data.meta.responseToId)) {
          const callback = this.messageCallbacks.get(data.meta.responseToId);
          if (callback) {
            callback(data);
          }
          return;
        }
        
        // Trigger event based on message type
        if (data.type) {
          this.triggerEvent(data.type, data.payload || {});
        }
        
        // Also trigger a generic 'message' event
        this.triggerEvent('message', data);
      } catch (error) {
        this.log('Error processing message', error, event.data);
      }
    });
    
    // Handle socket close event
    this.socket.on('close', () => {
      this.connected = false;
      this.triggerEvent('disconnect', {});
    });
    
    // Handle socket error event
    this.socket.on('error', (error) => {
      this.triggerEvent('error', { error });
    });
    
    // Handle socket reconnect event
    this.socket.on('reconnect', (data) => {
      this.connected = true;
      this.triggerEvent('reconnect', data);
      
      // Resend handshake after reconnection
      this.sendHandshake();
    });
    
    // Handle reconnecting event
    this.socket.on('reconnecting', (data) => {
      this.triggerEvent('reconnecting', data);
    });
  }

  /**
   * Send the initial handshake message
   */
  private async sendHandshake(): Promise<void> {
    await this.send({
      type: 'handshake',
      payload: {
        client: 'tapped-extension',
        version: '1.0.0',
        browser: navigator.userAgent,
        tabId: this.tabId
      }
    });
  }

  /**
   * Process the message queue
   */
  private processMessageQueue(): void {
    if (this.messageQueue.length === 0 || !this.connected) {
      return;
    }
    
    this.log(`Processing ${this.messageQueue.length} queued messages`);
    
    // Take a copy of the queue and clear it
    const queuedMessages = [...this.messageQueue];
    this.messageQueue = [];
    
    // Send all queued messages
    queuedMessages.forEach(message => {
      this.send(message).catch(error => {
        this.log('Failed to send queued message', error, message);
      });
    });
  }

  /**
   * Start the health check interval
   */
  private startHealthCheck(): void {
    this.stopHealthCheck();
    
    if (this.options.healthCheckInterval <= 0) {
      return;
    }
    
    this.log(`Starting health check with interval ${this.options.healthCheckInterval}ms`);
    
    this.healthCheckInterval = window.setInterval(() => {
      if (this.connected) {
        this.socket.ping();
      }
    }, this.options.healthCheckInterval);
  }

  /**
   * Stop the health check interval
   */
  private stopHealthCheck(): void {
    if (this.healthCheckInterval !== null) {
      clearInterval(this.healthCheckInterval);
      this.healthCheckInterval = null;
    }
  }

  /**
   * Start the queue processing interval
   */
  private startQueueProcessing(): void {
    this.stopQueueProcessing();
    
    this.queueProcessInterval = window.setInterval(() => {
      if (this.connected && this.messageQueue.length > 0) {
        this.processMessageQueue();
      }
    }, 5000); // Process queue every 5 seconds
  }

  /**
   * Stop the queue processing interval
   */
  private stopQueueProcessing(): void {
    if (this.queueProcessInterval !== null) {
      clearInterval(this.queueProcessInterval);
      this.queueProcessInterval = null;
    }
  }

  /**
   * Trigger an event to all listeners
   */
  private triggerEvent(event: string, data: any): void {
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
    
    // Also trigger to wildcard listeners
    const wildcardListeners = this.eventListeners.get('*');
    if (wildcardListeners) {
      wildcardListeners.forEach(listener => {
        try {
          listener({ event, data });
        } catch (error) {
          this.log(`Error in wildcard listener for ${event}`, error);
        }
      });
    }
  }

  /**
   * Try to detect the current tab ID
   */
  private detectTabId(): void {
    if (typeof chrome !== 'undefined' && chrome.tabs) {
      chrome.tabs.getCurrent((tab) => {
        if (tab) {
          this.tabId = tab.id;
        }
      });
    }
  }

  /**
   * Log debug messages if debug is enabled
   */
  private log(...args: any[]): void {
    if (this.options.debug) {
      console.log('[McpClient]', ...args);
    }
  }
}
