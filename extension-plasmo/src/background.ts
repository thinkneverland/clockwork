/**
 * Tapped Extension - Background Service Worker
 * 
 * Handles communication between content scripts and DevTools panel.
 * Manages connections to the Tapped server via MCP protocol.
 */

import { Storage } from "@plasmohq/storage"
import { McpClient } from "../lib/mcp/McpClient"
import { ConnectionRegistry } from "../lib/mcp/ConnectionRegistry"
import { McpMessage } from "../lib/types/mcp"

// Storage for extension settings
const storage = new Storage({ area: "local" })

// Track connections between tabs and devtools
interface DevToolsConnection {
  port: chrome.runtime.Port
  tabId: number
}

interface ContentScriptConnection {
  port: chrome.runtime.Port
  tabId: number
  hasLivewire: boolean
  livewireVersion?: string
}

// McpClient instances for each tab
const mcpClients: Map<number, McpClient> = new Map()
// Connection registry for all McpClient instances
const connectionRegistry = new ConnectionRegistry({ debug: true })
// DevTools connections by tabId
const devToolsConnections: Map<number, DevToolsConnection[]> = new Map()
// Content script connections by tabId
const contentScriptConnections: Map<number, ContentScriptConnection> = new Map()
// Tab metadata
const tabMetadata: Map<number, { url: string, title: string }> = new Map()

/**
 * Initialize the background service
 */
async function initialize() {
  console.log("[Tapped] Background service initializing")
  
  // Track tab updates to maintain metadata
  chrome.tabs.onUpdated.addListener((tabId, changeInfo, tab) => {
    if (tab.url) {
      tabMetadata.set(tabId, {
        url: tab.url,
        title: tab.title || "Unknown"
      })
    }
  })
  
  // Clean up when a tab is closed
  chrome.tabs.onRemoved.addListener((tabId) => {
    cleanupForTab(tabId)
  })
  
  // Listen for connection requests from DevTools and content scripts
  chrome.runtime.onConnect.addListener(handleConnection)
  
  // Set up message handling for one-time messages
  chrome.runtime.onMessage.addListener(handleMessage)
}

/**
 * Handle new port connections
 */
function handleConnection(port: chrome.runtime.Port) {
  const sender = port.sender
  const portName = port.name
  
  console.log(`[Tapped] New connection: ${portName}`)
  
  if (portName.startsWith("tapped-devtools-")) {
    const tabId = parseInt(portName.replace("tapped-devtools-", ""), 10)
    handleDevToolsConnection(port, tabId)
  } else if (portName.startsWith("tapped-content-")) {
    if (!sender || !sender.tab || !sender.tab.id) {
      console.warn("[Tapped] Content script connection missing tab information")
      return
    }
    
    const tabId = sender.tab.id
    handleContentScriptConnection(port, tabId)
  }
}

/**
 * Handle DevTools panel connection
 */
function handleDevToolsConnection(port: chrome.runtime.Port, tabId: number) {
  const connection: DevToolsConnection = { port, tabId }
  
  // Add to connections map
  if (!devToolsConnections.has(tabId)) {
    devToolsConnections.set(tabId, [])
  }
  devToolsConnections.get(tabId)?.push(connection)
  
  // Notify content scripts that DevTools is connected
  sendToContentScript(tabId, {
    action: "devtools-connected"
  })
  
  // Setup connection to MCP server if not already connected
  setupMcpConnection(tabId)
  
  // Handle messages from DevTools
  port.onMessage.addListener((message) => {
    handleDevToolsMessage(message, tabId, port)
  })
  
  // Handle disconnect
  port.onDisconnect.addListener(() => {
    handleDevToolsDisconnect(port, tabId)
  })
  
  // Send initial tab information
  port.postMessage({
    action: "tab-info",
    tabId,
    url: tabMetadata.get(tabId)?.url || "unknown",
    title: tabMetadata.get(tabId)?.title || "Unknown"
  })
  
  // Send Livewire status if available
  const contentScript = contentScriptConnections.get(tabId)
  if (contentScript) {
    port.postMessage({
      action: "livewire-status",
      hasLivewire: contentScript.hasLivewire,
      livewireVersion: contentScript.livewireVersion
    })
  } else {
    // Request Livewire detection if content script is connected
    sendToContentScript(tabId, {
      action: "detect-livewire"
    })
  }
}

/**
 * Handle content script connection
 */
function handleContentScriptConnection(port: chrome.runtime.Port, tabId: number) {
  const connection: ContentScriptConnection = {
    port,
    tabId,
    hasLivewire: false
  }
  
  contentScriptConnections.set(tabId, connection)
  
  // Store tab metadata
  chrome.tabs.get(tabId, (tab) => {
    if (tab.url) {
      tabMetadata.set(tabId, {
        url: tab.url,
        title: tab.title || "Unknown"
      })
    }
  })
  
  // Handle messages from content script
  port.onMessage.addListener((message) => {
    handleContentScriptMessage(message, tabId, port)
  })
  
  // Handle disconnect
  port.onDisconnect.addListener(() => {
    handleContentScriptDisconnect(port, tabId)
  })
  
  // Request Livewire detection
  port.postMessage({
    action: "detect-livewire"
  })
}

/**
 * Handle messages from DevTools panel
 */
function handleDevToolsMessage(message: any, tabId: number, port: chrome.runtime.Port) {
  console.log(`[Tapped] DevTools message:`, message)
  
  switch (message.action) {
    case "get-livewire-status":
      const contentScript = contentScriptConnections.get(tabId)
      port.postMessage({
        action: "livewire-status",
        hasLivewire: contentScript?.hasLivewire || false,
        livewireVersion: contentScript?.livewireVersion
      })
      break
      
    case "get-component":
      sendToContentScript(tabId, {
        action: "get-component",
        componentId: message.componentId
      })
      break
      
    case "highlight-component":
      sendToContentScript(tabId, {
        action: "highlight-component",
        componentId: message.componentId
      })
      break
      
    case "execute-method":
      sendToContentScript(tabId, {
        action: "execute-method",
        componentId: message.componentId,
        method: message.method,
        params: message.params || []
      })
      break
      
    case "update-property":
      sendToContentScript(tabId, {
        action: "update-property",
        componentId: message.componentId,
        property: message.property,
        value: message.value
      })
      break
      
    case "connect-to-server":
      connectToMcpServer(tabId, message.url)
      break
      
    case "disconnect-from-server":
      disconnectFromMcpServer(tabId)
      break
      
    case "send-to-server":
      if (mcpClients.has(tabId)) {
        const client = mcpClients.get(tabId)
        if (client) {
          const mcpMessage: McpMessage = {
            type: message.type,
            payload: message.payload,
            meta: {
              tabId,
              ...message.meta
            }
          }
          client.send(mcpMessage)
        }
      }
      break
  }
}

/**
 * Handle messages from content scripts
 */
function handleContentScriptMessage(message: any, tabId: number, port: chrome.runtime.Port) {
  console.log(`[Tapped] Content script message:`, message)
  
  const connection = contentScriptConnections.get(tabId)
  if (!connection) {
    return
  }
  
  switch (message.action) {
    case "livewire-detection":
      // Update stored Livewire status
      connection.hasLivewire = message.hasLivewire
      connection.livewireVersion = message.livewireVersion
      
      // Forward to DevTools
      sendToDevTools(tabId, {
        action: "livewire-status",
        hasLivewire: message.hasLivewire,
        livewireVersion: message.livewireVersion,
        components: message.components
      })
      break
      
    case "component-data":
      // Forward component data to DevTools
      sendToDevTools(tabId, {
        action: "component-data",
        component: message.component
      })
      break
      
    case "method-executed":
      // Forward method execution result to DevTools
      sendToDevTools(tabId, {
        action: "method-executed",
        componentId: message.componentId,
        method: message.method,
        result: message.result,
        error: message.error
      })
      break
      
    case "property-updated":
      // Forward property update result to DevTools
      sendToDevTools(tabId, {
        action: "property-updated",
        componentId: message.componentId,
        property: message.property,
        value: message.value,
        error: message.error
      })
      break
      
    case "error":
      // Forward errors to DevTools
      sendToDevTools(tabId, {
        action: "error",
        error: message.error
      })
      break
  }
}

/**
 * Handle DevTools panel disconnect
 */
function handleDevToolsDisconnect(port: chrome.runtime.Port, tabId: number) {
  console.log(`[Tapped] DevTools disconnected for tab ${tabId}`)
  
  // Remove from connections map
  const connections = devToolsConnections.get(tabId) || []
  const index = connections.findIndex(conn => conn.port === port)
  if (index >= 0) {
    connections.splice(index, 1)
  }
  
  if (connections.length === 0) {
    devToolsConnections.delete(tabId)
    
    // Notify content scripts
    sendToContentScript(tabId, {
      action: "devtools-disconnected"
    })
    
    // If no DevTools are connected, consider cleaning up the MCP connection
    if (!hasDevToolsConnection(tabId)) {
      disconnectFromMcpServer(tabId)
    }
  } else {
    devToolsConnections.set(tabId, connections)
  }
}

/**
 * Handle content script disconnect
 */
function handleContentScriptDisconnect(port: chrome.runtime.Port, tabId: number) {
  console.log(`[Tapped] Content script disconnected for tab ${tabId}`)
  
  contentScriptConnections.delete(tabId)
  
  // Notify DevTools
  sendToDevTools(tabId, {
    action: "content-script-disconnected"
  })
}

/**
 * Clean up resources for a tab
 */
function cleanupForTab(tabId: number) {
  console.log(`[Tapped] Cleaning up resources for tab ${tabId}`)
  
  // Remove from connections maps
  devToolsConnections.delete(tabId)
  contentScriptConnections.delete(tabId)
  tabMetadata.delete(tabId)
  
  // Disconnect from MCP server
  disconnectFromMcpServer(tabId)
}

/**
 * Check if there are any DevTools connections for a tab
 */
function hasDevToolsConnection(tabId: number): boolean {
  const connections = devToolsConnections.get(tabId)
  return Boolean(connections && connections.length > 0)
}

/**
 * Send a message to all connected DevTools for a tab
 */
function sendToDevTools(tabId: number, message: any) {
  const connections = devToolsConnections.get(tabId) || []
  
  connections.forEach(connection => {
    try {
      connection.port.postMessage(message)
    } catch (error) {
      console.error(`[Tapped] Error sending message to DevTools:`, error)
    }
  })
}

/**
 * Send a message to the content script for a tab
 */
function sendToContentScript(tabId: number, message: any) {
  const connection = contentScriptConnections.get(tabId)
  if (connection) {
    try {
      connection.port.postMessage(message)
    } catch (error) {
      console.error(`[Tapped] Error sending message to content script:`, error)
    }
  }
}

/**
 * Setup MCP connection for a tab
 */
async function setupMcpConnection(tabId: number) {
  // Check if already setup
  if (mcpClients.has(tabId)) {
    return
  }
  
  // Get saved connection URL for this URL pattern
  const tabInfo = tabMetadata.get(tabId)
  if (!tabInfo) {
    return
  }
  
  const savedConnections = await storage.get("connections") || "[]"
  const connections = JSON.parse(savedConnections)
  
  // Find a matching connection configuration
  const matchingConnection = connections.find((conn: any) => {
    return tabInfo.url.includes(conn.urlPattern)
  })
  
  if (matchingConnection && matchingConnection.autoConnect) {
    connectToMcpServer(tabId, matchingConnection.url)
  }
}

/**
 * Connect to an MCP server
 */
function connectToMcpServer(tabId: number, serverUrl: string) {
  console.log(`[Tapped] Connecting to MCP server ${serverUrl} for tab ${tabId}`)
  
  // Clean up existing connection if any
  disconnectFromMcpServer(tabId)
  
  // Create new MCP client
  const client = new McpClient({
    url: serverUrl,
    autoReconnect: true,
    debug: true
  })
  
  // Register event listeners
  client.on("connect", () => {
    sendToDevTools(tabId, {
      action: "server-connected",
      url: serverUrl
    })
  })
  
  client.on("disconnect", () => {
    sendToDevTools(tabId, {
      action: "server-disconnected"
    })
  })
  
  client.on("error", (error) => {
    sendToDevTools(tabId, {
      action: "server-error",
      error
    })
  })
  
  client.on("message", (message) => {
    sendToDevTools(tabId, {
      action: "server-message",
      message
    })
  })
  
  // Connect to the server
  client.connect().catch(error => {
    console.error(`[Tapped] Error connecting to MCP server:`, error)
    sendToDevTools(tabId, {
      action: "server-error",
      error: error.message
    })
  })
  
  // Store the client
  mcpClients.set(tabId, client)
  
  // Register with the connection registry
  const connectionId = `tab-${tabId}`
  // Add to registry - implement this part
}

/**
 * Disconnect from the MCP server
 */
function disconnectFromMcpServer(tabId: number) {
  const client = mcpClients.get(tabId)
  if (client) {
    console.log(`[Tapped] Disconnecting from MCP server for tab ${tabId}`)
    
    client.disconnect().catch(error => {
      console.error(`[Tapped] Error disconnecting from MCP server:`, error)
    })
    
    // Unregister from the connection registry
    const connectionId = `tab-${tabId}`
    connectionRegistry.unregisterConnection(connectionId)
    
    // Remove the client
    mcpClients.delete(tabId)
    
    // Notify DevTools
    sendToDevTools(tabId, {
      action: "server-disconnected"
    })
  }
}

/**
 * Handle one-time messages (not through a port)
 */
function handleMessage(
  message: any,
  sender: chrome.runtime.MessageSender,
  sendResponse: (response?: any) => void
) {
  if (message.action === "get-tab-info") {
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
      if (tabs.length > 0) {
        const tab = tabs[0]
        sendResponse({
          tabId: tab.id,
          url: tab.url,
          title: tab.title
        })
      } else {
        sendResponse({ error: "No active tab found" })
      }
    })
    return true // Indicate async response
  }
  
  return false
}

// Initialize the background service
initialize()

// Export for Plasmo
export {}
