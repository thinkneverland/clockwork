/**
 * ConnectionStore - Manage connection configurations and persistence
 * 
 * Handles storing and retrieving connection information for the extension,
 * with cross-browser compatibility and memory management optimizations.
 */

import { Storage } from "@plasmohq/storage"
import EventUtils from "../utils/EventUtils"

export interface ConnectionConfig {
  id: string
  url: string
  name: string
  autoConnect: boolean
  lastConnected?: number
  healthStatus?: 'healthy' | 'degraded' | 'unhealthy'
  meta?: Record<string, any>
}

export type ConnectionChangeListener = (connections: ConnectionConfig[]) => void

export class ConnectionStore {
  private storage: Storage
  private connections: ConnectionConfig[] = []
  private listeners: ConnectionChangeListener[] = []
  private browser = EventUtils.detectBrowser()
  private initialized = false
  
  constructor() {
    // Use Plasmo storage API for cross-browser compatibility
    this.storage = new Storage({
      area: "local"
    })
    
    // Initialize the connection store
    this.init()
  }
  
  /**
   * Initialize the connection store
   */
  private async init(): Promise<void> {
    if (this.initialized) {
      return
    }
    
    try {
      const storedConnections = await this.storage.get("connections")
      if (storedConnections) {
        this.connections = JSON.parse(storedConnections)
      }
      
      this.initialized = true
      this.notifyListeners()
    } catch (error) {
      console.error("Failed to initialize ConnectionStore:", error)
    }
  }
  
  /**
   * Get all connections
   */
  public async getConnections(): Promise<ConnectionConfig[]> {
    if (!this.initialized) {
      await this.init()
    }
    
    return [...this.connections]
  }
  
  /**
   * Get a connection by ID
   */
  public async getConnection(id: string): Promise<ConnectionConfig | null> {
    if (!this.initialized) {
      await this.init()
    }
    
    return this.connections.find(conn => conn.id === id) || null
  }
  
  /**
   * Add a new connection
   */
  public async addConnection(connection: Omit<ConnectionConfig, "id">): Promise<ConnectionConfig> {
    if (!this.initialized) {
      await this.init()
    }
    
    const newConnection: ConnectionConfig = {
      ...connection,
      id: this.generateId()
    }
    
    this.connections.push(newConnection)
    await this.saveConnections()
    
    return newConnection
  }
  
  /**
   * Update an existing connection
   */
  public async updateConnection(id: string, update: Partial<ConnectionConfig>): Promise<ConnectionConfig | null> {
    if (!this.initialized) {
      await this.init()
    }
    
    const index = this.connections.findIndex(conn => conn.id === id)
    if (index === -1) {
      return null
    }
    
    // Create updated connection object
    const updatedConnection = {
      ...this.connections[index],
      ...update,
      id // Ensure ID doesn't change
    }
    
    this.connections[index] = updatedConnection
    await this.saveConnections()
    
    return updatedConnection
  }
  
  /**
   * Remove a connection
   */
  public async removeConnection(id: string): Promise<boolean> {
    if (!this.initialized) {
      await this.init()
    }
    
    const initialLength = this.connections.length
    this.connections = this.connections.filter(conn => conn.id !== id)
    
    if (this.connections.length !== initialLength) {
      await this.saveConnections()
      return true
    }
    
    return false
  }
  
  /**
   * Update connection health status
   */
  public async updateConnectionHealth(
    id: string, 
    healthStatus: 'healthy' | 'degraded' | 'unhealthy'
  ): Promise<ConnectionConfig | null> {
    return this.updateConnection(id, { 
      healthStatus,
      lastConnected: healthStatus === 'healthy' ? Date.now() : undefined
    })
  }
  
  /**
   * Subscribe to connection changes
   */
  public subscribe(listener: ConnectionChangeListener): () => void {
    this.listeners.push(listener)
    
    // Notify with current state
    if (this.initialized) {
      listener([...this.connections])
    }
    
    // Return unsubscribe function
    return () => {
      const index = this.listeners.indexOf(listener)
      if (index !== -1) {
        this.listeners.splice(index, 1)
      }
    }
  }
  
  /**
   * Save connections to storage
   */
  private async saveConnections(): Promise<void> {
    try {
      await this.storage.set("connections", JSON.stringify(this.connections))
      this.notifyListeners()
    } catch (error) {
      console.error("Failed to save connections:", error)
    }
  }
  
  /**
   * Notify all listeners of connection changes
   */
  private notifyListeners(): void {
    const connections = [...this.connections]
    this.listeners.forEach(listener => {
      try {
        listener(connections)
      } catch (error) {
        console.error("Error in connection change listener:", error)
      }
    })
  }
  
  /**
   * Generate a unique ID for a connection
   */
  private generateId(): string {
    return `conn_${Date.now()}_${Math.random().toString(36).substring(2, 11)}`
  }
  
  /**
   * Clean up all resources and listeners
   */
  public cleanup(): void {
    this.listeners = []
  }
}
