/**
 * MessageUtils - Type-safe message passing utilities
 * 
 * Provides a unified and type-safe approach to message passing between
 * content scripts, background scripts, and DevTools panels.
 */

import { getBrowserAPI } from './BrowserUtils';

// Action types for message passing
export enum MessageActionType {
  // Content script actions
  DETECT_LIVEWIRE = 'detect-livewire',
  LIVEWIRE_DETECTED = 'livewire-detected',
  GET_COMPONENTS = 'get-components',
  GET_COMPONENT_DETAILS = 'get-component-details',
  UPDATE_COMPONENT = 'update-component',
  
  // Background/DevTools actions
  CONNECT_SERVER = 'connect-server',
  DISCONNECT_SERVER = 'disconnect-server',
  SERVER_CONNECTION_STATUS = 'server-connection-status',
  
  // Data actions
  COMPONENTS_LIST = 'components-list',
  COMPONENT_DETAILS = 'component-details',
  EVENTS_LIST = 'events-list',
  QUERIES_LIST = 'queries-list',
  REQUESTS_LIST = 'requests-list',
  
  // Error handling
  ERROR = 'error',
  LOG = 'log'
}

// Base message interface
export interface Message<T = any> {
  action: MessageActionType;
  tabId?: number;
  data?: T;
  error?: string;
  timestamp?: number;
}

// Type guard to check if a message has a specific action
export function isMessageAction<T = any>(
  message: Message<any>, 
  action: MessageActionType
): message is Message<T> {
  return message.action === action;
}

/**
 * Send a message to the background script
 */
export async function sendToBackground<T = any, R = any>(
  message: Message<T>
): Promise<R | undefined> {
  const api = getBrowserAPI();
  
  if (!api.runtime) {
    console.error('Runtime API not available');
    return undefined;
  }
  
  // Add timestamp if not provided
  const enhancedMessage: Message<T> = {
    ...message,
    timestamp: message.timestamp || Date.now()
  };
  
  return new Promise<R | undefined>((resolve) => {
    api.runtime.sendMessage(enhancedMessage, (response: R) => {
      const error = api.runtime.lastError;
      if (error) {
        console.error('Error sending message to background:', error);
        resolve(undefined);
      } else {
        resolve(response);
      }
    });
  });
}

/**
 * Send a message to a specific tab
 */
export async function sendToTab<T = any, R = any>(
  tabId: number,
  message: Message<T>
): Promise<R | undefined> {
  const api = getBrowserAPI();
  
  if (!api.tabs) {
    console.error('Tabs API not available');
    return undefined;
  }
  
  // Add timestamp and tabId if not provided
  const enhancedMessage: Message<T> = {
    ...message,
    tabId: message.tabId || tabId,
    timestamp: message.timestamp || Date.now()
  };
  
  return new Promise<R | undefined>((resolve) => {
    api.tabs.sendMessage(tabId, enhancedMessage, (response: R) => {
      const error = api.runtime.lastError;
      if (error) {
        console.error(`Error sending message to tab ${tabId}:`, error);
        resolve(undefined);
      } else {
        resolve(response);
      }
    });
  });
}

/**
 * Send a message to all DevTools panels
 */
export function broadcastToDevTools<T = any>(message: Message<T>): void {
  const api = getBrowserAPI();
  
  if (!api.runtime) {
    console.error('Runtime API not available');
    return;
  }
  
  // Add timestamp if not provided
  const enhancedMessage: Message<T> = {
    ...message,
    timestamp: message.timestamp || Date.now()
  };
  
  // In the background script, port connections to DevTools panels
  // would be stored and managed separately to broadcast messages
  api.runtime.sendMessage(enhancedMessage);
}

/**
 * Listen for messages from any context
 */
export function addMessageListener<T = any>(
  callback: (message: Message<T>, sender: chrome.runtime.MessageSender, sendResponse: (response?: any) => void) => void | boolean
): void {
  const api = getBrowserAPI();
  
  if (!api.runtime) {
    console.error('Runtime API not available');
    return;
  }
  
  api.runtime.onMessage.addListener(callback);
}

/**
 * Remove a previously added message listener
 */
export function removeMessageListener<T = any>(
  callback: (message: Message<T>, sender: chrome.runtime.MessageSender, sendResponse: (response?: any) => void) => void | boolean
): void {
  const api = getBrowserAPI();
  
  if (!api.runtime) {
    console.error('Runtime API not available');
    return;
  }
  
  api.runtime.onMessage.removeListener(callback);
}

/**
 * Create a port connection for long-lived communication
 */
export function createConnection(
  name: string,
  connectInfo?: chrome.runtime.ConnectInfo
): chrome.runtime.Port {
  const api = getBrowserAPI();
  
  if (!api.runtime) {
    throw new Error('Runtime API not available');
  }
  
  return api.runtime.connect(undefined, {
    name,
    ...connectInfo
  });
}

/**
 * Create a port connection to a specific tab
 */
export function createTabConnection(
  tabId: number,
  name: string,
  connectInfo?: chrome.runtime.ConnectInfo
): chrome.runtime.Port {
  const api = getBrowserAPI();
  
  if (!api.tabs) {
    throw new Error('Tabs API not available');
  }
  
  return api.tabs.connect(tabId, {
    name,
    ...connectInfo
  });
}

/**
 * Send a message through a port connection
 */
export function sendThroughPort<T = any>(
  port: chrome.runtime.Port,
  message: Message<T>
): void {
  // Add timestamp if not provided
  const enhancedMessage: Message<T> = {
    ...message,
    timestamp: message.timestamp || Date.now()
  };
  
  port.postMessage(enhancedMessage);
}

/**
 * Listen for messages on a port connection
 */
export function addPortListener<T = any>(
  port: chrome.runtime.Port,
  callback: (message: Message<T>) => void
): void {
  port.onMessage.addListener(callback);
}

/**
 * Remove a previously added port listener
 */
export function removePortListener<T = any>(
  port: chrome.runtime.Port,
  callback: (message: Message<T>) => void
): void {
  port.onMessage.removeListener(callback);
}

/**
 * Create a typed message with the correct structure
 */
export function createMessage<T = any>(
  action: MessageActionType,
  data?: T,
  tabId?: number
): Message<T> {
  return {
    action,
    data,
    tabId,
    timestamp: Date.now()
  };
}

export default {
  MessageActionType,
  isMessageAction,
  sendToBackground,
  sendToTab,
  broadcastToDevTools,
  addMessageListener,
  removeMessageListener,
  createConnection,
  createTabConnection,
  sendThroughPort,
  addPortListener,
  removePortListener,
  createMessage
};
