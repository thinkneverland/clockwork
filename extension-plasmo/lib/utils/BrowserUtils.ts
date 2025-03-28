/**
 * BrowserUtils - Cross-browser compatibility utilities
 * 
 * Provides a unified API for browser-specific functionality,
 * leveraging Plasmo's cross-browser capabilities while adding
 * additional utilities and standardization.
 */

import { detectBrowser } from './EventUtils';

// Type definitions for cross-browser compatibility
type BrowserType = 'chrome' | 'firefox' | 'edge' | 'safari' | 'opera' | 'unknown';

/**
 * Get browser API object (chrome, browser, etc.) based on environment
 */
export function getBrowserAPI(): typeof chrome {
  if (typeof chrome !== 'undefined') {
    return chrome;
  } else if (typeof browser !== 'undefined') {
    // @ts-ignore - browser is not in the type definitions but exists in Firefox
    return browser;
  } else {
    // Fallback for non-extension environments
    console.warn('No browser extension API detected');
    // Return a minimal object to prevent errors
    return {} as typeof chrome;
  }
}

/**
 * Get information about the current browser environment
 */
export function getBrowserInfo(): {
  type: BrowserType;
  name: string;
  version: string;
  isExtensionContext: boolean;
} {
  const browser = detectBrowser();
  
  let type: BrowserType = 'unknown';
  if (browser.isChrome) type = 'chrome';
  else if (browser.isFirefox) type = 'firefox';
  else if (browser.isEdge) type = 'edge';
  else if (browser.isSafari) type = 'safari';
  else if (browser.isOpera) type = 'opera';
  
  return {
    type,
    name: browser.name,
    version: browser.version,
    isExtensionContext: typeof chrome !== 'undefined' && !!chrome.runtime
  };
}

/**
 * Send a message to the background script/service worker
 * with unified API across browsers
 */
export async function sendMessage<T, R>(
  message: T
): Promise<R | undefined> {
  const api = getBrowserAPI();
  
  if (!api.runtime) {
    console.error('Runtime API not available');
    return undefined;
  }
  
  return new Promise<R | undefined>((resolve) => {
    try {
      api.runtime.sendMessage(message, (response: R) => {
        const error = api.runtime.lastError;
        if (error) {
          console.error('Error sending message:', error);
          resolve(undefined);
        } else {
          resolve(response);
        }
      });
    } catch (error) {
      console.error('Failed to send message:', error);
      resolve(undefined);
    }
  });
}

/**
 * Send a message to a specific tab
 */
export async function sendMessageToTab<T, R>(
  tabId: number,
  message: T
): Promise<R | undefined> {
  const api = getBrowserAPI();
  
  if (!api.tabs) {
    console.error('Tabs API not available');
    return undefined;
  }
  
  return new Promise<R | undefined>((resolve) => {
    try {
      api.tabs.sendMessage(tabId, message, (response: R) => {
        const error = api.runtime.lastError;
        if (error) {
          console.error(`Error sending message to tab ${tabId}:`, error);
          resolve(undefined);
        } else {
          resolve(response);
        }
      });
    } catch (error) {
      console.error(`Failed to send message to tab ${tabId}:`, error);
      resolve(undefined);
    }
  });
}

/**
 * Create a connection to another extension context
 */
export function connect(
  connectInfo?: chrome.runtime.ConnectInfo
): chrome.runtime.Port {
  const api = getBrowserAPI();
  return api.runtime.connect(connectInfo);
}

/**
 * Connect to a specific tab
 */
export function connectToTab(
  tabId: number,
  connectInfo?: chrome.runtime.ConnectInfo
): chrome.runtime.Port {
  const api = getBrowserAPI();
  return api.tabs.connect(tabId, connectInfo);
}

/**
 * Get the current tab
 */
export async function getCurrentTab(): Promise<chrome.tabs.Tab | undefined> {
  const api = getBrowserAPI();
  
  if (!api.tabs) {
    console.error('Tabs API not available');
    return undefined;
  }
  
  return new Promise<chrome.tabs.Tab | undefined>((resolve) => {
    api.tabs.query({ active: true, currentWindow: true }, (tabs) => {
      if (!tabs || tabs.length === 0) {
        resolve(undefined);
      } else {
        resolve(tabs[0]);
      }
    });
  });
}

/**
 * Execute a script in a tab
 */
export async function executeScript<T>(
  tabId: number,
  options: chrome.scripting.ScriptInjection<any[], T>
): Promise<T[] | undefined> {
  const api = getBrowserAPI();
  
  if (!api.scripting) {
    // Fallback for older Chrome versions and Firefox
    if (api.tabs && api.tabs.executeScript) {
      return new Promise<T[] | undefined>((resolve) => {
        try {
          // @ts-ignore - API differences between browsers
          api.tabs.executeScript(
            tabId,
            { code: options.func.toString() + `(${JSON.stringify(options.args || [])})` },
            (results: T[]) => {
              const error = api.runtime.lastError;
              if (error) {
                console.error('Error executing script:', error);
                resolve(undefined);
              } else {
                resolve(results);
              }
            }
          );
        } catch (error) {
          console.error('Failed to execute script:', error);
          resolve(undefined);
        }
      });
    }
    
    console.error('Scripting API not available');
    return undefined;
  }
  
  try {
    return await api.scripting.executeScript(options);
  } catch (error) {
    console.error('Failed to execute script:', error);
    return undefined;
  }
}

/**
 * Check if extension has the required permissions
 */
export async function hasPermissions(
  permissions: chrome.permissions.Permissions
): Promise<boolean> {
  const api = getBrowserAPI();
  
  if (!api.permissions) {
    console.error('Permissions API not available');
    return false;
  }
  
  return new Promise<boolean>((resolve) => {
    api.permissions.contains(permissions, (result) => {
      resolve(result);
    });
  });
}

/**
 * Detect browser-specific features and capabilities
 */
export function getFeatureSupport(): {
  supportsManifestV3: boolean;
  supportsScripting: boolean;
  supportsTabGroups: boolean;
  supportsDevtools: boolean;
} {
  const api = getBrowserAPI();
  
  return {
    supportsManifestV3: !!api.action, // action API is only in Manifest V3
    supportsScripting: !!api.scripting,
    supportsTabGroups: !!api.tabGroups,
    supportsDevtools: !!api.devtools
  };
}

// Export a default object with all utilities
export default {
  getBrowserAPI,
  getBrowserInfo,
  sendMessage,
  sendMessageToTab,
  connect,
  connectToTab,
  getCurrentTab,
  executeScript,
  hasPermissions,
  getFeatureSupport
};
