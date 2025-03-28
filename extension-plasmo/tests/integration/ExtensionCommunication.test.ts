/**
 * Extension Communication Integration Tests
 * 
 * Tests for the message passing between various extension components:
 * - Content scripts to background service worker
 * - Background service worker to DevTools panel
 * - DevTools panel to content scripts
 */

import { BackgroundMessenger } from '../../background/BackgroundMessenger';
import { ContentScriptMessenger } from '../../contents/ContentScriptMessenger';
import { DevToolsMessenger } from '../../devtools/DevToolsMessenger';
import * as MessageUtils from '../../lib/utils/MessageUtils';
import { BrowserUtils } from '../../lib/utils/BrowserUtils';

// Mock dependencies
jest.mock('../../lib/utils/MessageUtils', () => ({
  MessageActionType: {
    GET_COMPONENTS: 'GET_COMPONENTS',
    UPDATE_COMPONENT: 'UPDATE_COMPONENT',
    GET_EVENTS: 'GET_EVENTS',
    GET_QUERIES: 'GET_QUERIES',
    EXECUTE_METHOD: 'EXECUTE_METHOD',
    CLEAR_DATA: 'CLEAR_DATA'
  },
  createMessage: jest.fn((action, data) => ({ action, data })),
  sendThroughPort: jest.fn(),
  sendToContent: jest.fn(),
  sendToBackground: jest.fn(),
  sendToDevTools: jest.fn()
}));

jest.mock('../../lib/utils/BrowserUtils', () => ({
  BrowserUtils: {
    getBrowserAPI: jest.fn(),
    getBrowserInfo: jest.fn(),
    isChrome: jest.fn().mockReturnValue(true),
    isFirefox: jest.fn().mockReturnValue(false),
    isEdge: jest.fn().mockReturnValue(false)
  }
}));

describe('Extension Communication', () => {
  // Mock browser API
  const mockBrowserAPI = {
    runtime: {
      sendMessage: jest.fn(),
      onMessage: {
        addListener: jest.fn(),
        removeListener: jest.fn()
      },
      connect: jest.fn().mockReturnValue({
        onMessage: {
          addListener: jest.fn()
        },
        onDisconnect: {
          addListener: jest.fn()
        },
        postMessage: jest.fn()
      })
    },
    tabs: {
      query: jest.fn(),
      sendMessage: jest.fn(),
      executeScript: jest.fn()
    }
  };
  
  // Mock port for connection-based messaging
  const mockPort = {
    name: 'tapped-devtools-panel',
    postMessage: jest.fn(),
    onMessage: {
      addListener: jest.fn(),
      removeListener: jest.fn()
    },
    onDisconnect: {
      addListener: jest.fn()
    }
  };
  
  beforeEach(() => {
    jest.clearAllMocks();
    
    // Setup browser API mock
    BrowserUtils.getBrowserAPI.mockReturnValue(mockBrowserAPI);
    BrowserUtils.getBrowserInfo.mockReturnValue({ 
      type: 'chrome', 
      name: 'Chrome', 
      version: '100.0.0' 
    });
  });
  
  describe('Content Script to Background Communication', () => {
    let contentMessenger: ContentScriptMessenger;
    let backgroundMessenger: BackgroundMessenger;
    
    beforeEach(() => {
      contentMessenger = new ContentScriptMessenger();
      backgroundMessenger = new BackgroundMessenger();
    });
    
    test('content script can send component data to background', () => {
      // Setup message handlers
      const messageHandler = jest.fn();
      backgroundMessenger.addMessageHandler(
        MessageUtils.MessageActionType.GET_COMPONENTS, 
        messageHandler
      );
      
      // Setup mock background message listener
      const messageListener = mockBrowserAPI.runtime.onMessage.addListener.mock.calls[0][0];
      
      // Component data to send
      const componentData = {
        id: 'comp-1',
        name: 'Counter',
        properties: {
          count: { value: 0, type: 'number' }
        }
      };
      
      // Send component data from content script
      contentMessenger.sendComponentData(componentData);
      
      // Verify message was sent
      expect(MessageUtils.sendToBackground).toHaveBeenCalledWith(
        expect.objectContaining({
          action: MessageUtils.MessageActionType.GET_COMPONENTS,
          data: componentData
        })
      );
      
      // Simulate background receiving the message
      const message = {
        action: MessageUtils.MessageActionType.GET_COMPONENTS,
        data: componentData
      };
      
      messageListener(message, {}, () => {});
      
      // Background message handler should be called with the data
      expect(messageHandler).toHaveBeenCalledWith(componentData, expect.any(Object));
    });
    
    test('content script can receive method execution requests', () => {
      // Setup message handlers
      const executeMethodHandler = jest.fn();
      contentMessenger.addMessageHandler(
        MessageUtils.MessageActionType.EXECUTE_METHOD,
        executeMethodHandler
      );
      
      // Setup mock content script message listener
      const messageListener = mockBrowserAPI.runtime.onMessage.addListener.mock.calls[0][0];
      
      // Method execution request
      const methodRequest = {
        componentId: 'comp-1',
        method: 'increment',
        params: []
      };
      
      // Simulate background sending the method execution request
      const message = {
        action: MessageUtils.MessageActionType.EXECUTE_METHOD,
        data: methodRequest
      };
      
      // Send to specific tab (simulating background to content script)
      mockBrowserAPI.tabs.sendMessage.mockImplementation((tabId, msg, callback) => {
        messageListener(msg, {}, callback);
      });
      
      // Background sends method execution request
      backgroundMessenger.executeComponentMethod(123, methodRequest);
      
      // Verify message was sent to the tab
      expect(mockBrowserAPI.tabs.sendMessage).toHaveBeenCalledWith(
        123,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.EXECUTE_METHOD,
          data: methodRequest
        }),
        expect.any(Function)
      );
      
      // Content script handler should be called with the request
      expect(executeMethodHandler).toHaveBeenCalledWith(methodRequest, expect.any(Object));
    });
  });
  
  describe('Background to DevTools Communication', () => {
    let backgroundMessenger: BackgroundMessenger;
    let devToolsMessenger: DevToolsMessenger;
    
    beforeEach(() => {
      // Initialize messengers
      backgroundMessenger = new BackgroundMessenger();
      devToolsMessenger = new DevToolsMessenger();
      
      // Setup mock connection
      mockBrowserAPI.runtime.connect.mockReturnValue(mockPort);
    });
    
    test('DevTools can connect to background and receive data', () => {
      // DevTools connects to background
      devToolsMessenger.connect();
      
      // Verify connection was made
      expect(mockBrowserAPI.runtime.connect).toHaveBeenCalledWith({
        name: 'tapped-devtools-panel'
      });
      
      // Setup message handler in DevTools
      const componentsHandler = jest.fn();
      devToolsMessenger.addMessageHandler(
        MessageUtils.MessageActionType.GET_COMPONENTS,
        componentsHandler
      );
      
      // Get the message listener registered by DevTools
      const messageListener = mockPort.onMessage.addListener.mock.calls[0][0];
      
      // Component data to send
      const componentsData = [
        {
          id: 'comp-1',
          name: 'Counter',
          properties: {
            count: { value: 0, type: 'number' }
          }
        }
      ];
      
      // Background sends component data to all connected DevTools
      backgroundMessenger.broadcastToDevTools(
        MessageUtils.MessageActionType.GET_COMPONENTS,
        componentsData
      );
      
      // Simulate port message from background to DevTools
      const message = {
        action: MessageUtils.MessageActionType.GET_COMPONENTS,
        data: componentsData
      };
      
      // Call the listener directly since we're mocking
      messageListener(message);
      
      // DevTools handler should be called with the data
      expect(componentsHandler).toHaveBeenCalledWith(componentsData);
    });
    
    test('DevTools can request data from background', () => {
      // DevTools connects to background
      devToolsMessenger.connect();
      
      // DevTools requests events data
      devToolsMessenger.getEvents();
      
      // Verify message was sent through port
      expect(mockPort.postMessage).toHaveBeenCalledWith(
        expect.objectContaining({
          action: MessageUtils.MessageActionType.GET_EVENTS
        })
      );
      
      // Setup handler in background
      const eventsHandler = jest.fn().mockReturnValue([
        { id: 'event-1', name: 'counter-updated', data: { count: 5 } }
      ]);
      
      backgroundMessenger.addMessageHandler(
        MessageUtils.MessageActionType.GET_EVENTS,
        eventsHandler
      );
      
      // Get the port message listener in background
      const portListeners = mockBrowserAPI.runtime.onConnect.addListener.mock.calls.map(call => call[0]);
      const portMessageHandler = mockPort.onMessage.addListener.mock.calls[0][0];
      
      // Simulate DevTools requesting events
      const message = {
        action: MessageUtils.MessageActionType.GET_EVENTS
      };
      
      // Call the handler directly since we're mocking
      portMessageHandler(message);
      
      // Background handler should be called
      expect(eventsHandler).toHaveBeenCalled();
      
      // Background should send response back through port
      expect(mockPort.postMessage).toHaveBeenCalledWith(
        expect.objectContaining({
          action: MessageUtils.MessageActionType.GET_EVENTS,
          data: expect.any(Array)
        })
      );
    });
  });
  
  describe('DevTools to Content Script Communication (via Background)', () => {
    let contentMessenger: ContentScriptMessenger;
    let backgroundMessenger: BackgroundMessenger;
    let devToolsMessenger: DevToolsMessenger;
    
    beforeEach(() => {
      // Initialize messengers
      contentMessenger = new ContentScriptMessenger();
      backgroundMessenger = new BackgroundMessenger();
      devToolsMessenger = new DevToolsMessenger();
      
      // Setup mock connection
      mockBrowserAPI.runtime.connect.mockReturnValue(mockPort);
      
      // DevTools connects to background
      devToolsMessenger.connect();
    });
    
    test('DevTools can send component property update to content script', () => {
      // Setup content script handler
      const updateHandler = jest.fn();
      contentMessenger.addMessageHandler(
        MessageUtils.MessageActionType.UPDATE_COMPONENT,
        updateHandler
      );
      
      // Setup background as router between DevTools and content script
      backgroundMessenger.addMessageHandler(
        MessageUtils.MessageActionType.UPDATE_COMPONENT,
        (data, sender) => {
          // Background relays to content script in specific tab
          return backgroundMessenger.sendToContentScript(
            123, // Tab ID from inspected window
            MessageUtils.MessageActionType.UPDATE_COMPONENT,
            data
          );
        }
      );
      
      // Property update data
      const updateData = {
        componentId: 'comp-1',
        property: 'count',
        value: 10
      };
      
      // DevTools sends update request
      devToolsMessenger.updateComponentProperty(updateData);
      
      // Verify message was sent through port
      expect(mockPort.postMessage).toHaveBeenCalledWith(
        expect.objectContaining({
          action: MessageUtils.MessageActionType.UPDATE_COMPONENT,
          data: updateData
        })
      );
      
      // Get the port message listener in background
      const portMessageHandler = mockPort.onMessage.addListener.mock.calls[0][0];
      
      // Simulate background receiving message from DevTools
      const message = {
        action: MessageUtils.MessageActionType.UPDATE_COMPONENT,
        data: updateData
      };
      
      // Background processes port message from DevTools
      portMessageHandler(message);
      
      // Background should relay to content script
      expect(mockBrowserAPI.tabs.sendMessage).toHaveBeenCalledWith(
        123,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.UPDATE_COMPONENT,
          data: updateData
        }),
        expect.any(Function)
      );
      
      // Setup mock content message listener
      const contentMessageListener = mockBrowserAPI.runtime.onMessage.addListener.mock.calls[0][0];
      
      // Simulate content script receiving message
      contentMessageListener(message, {}, () => {});
      
      // Content script handler should be called with update data
      expect(updateHandler).toHaveBeenCalledWith(updateData, expect.any(Object));
    });
    
    test('DevTools can request component method execution in content script', () => {
      // Setup content script handler
      const executeMethodHandler = jest.fn();
      contentMessenger.addMessageHandler(
        MessageUtils.MessageActionType.EXECUTE_METHOD,
        executeMethodHandler
      );
      
      // Setup background as router
      backgroundMessenger.addMessageHandler(
        MessageUtils.MessageActionType.EXECUTE_METHOD,
        (data, sender) => {
          // Background relays to content script in specific tab
          return backgroundMessenger.sendToContentScript(
            123, // Tab ID from inspected window
            MessageUtils.MessageActionType.EXECUTE_METHOD,
            data
          );
        }
      );
      
      // Method execution request
      const methodRequest = {
        componentId: 'comp-1',
        method: 'increment',
        params: []
      };
      
      // DevTools sends method execution request
      devToolsMessenger.executeComponentMethod(methodRequest);
      
      // Verify message was sent through port
      expect(mockPort.postMessage).toHaveBeenCalledWith(
        expect.objectContaining({
          action: MessageUtils.MessageActionType.EXECUTE_METHOD,
          data: methodRequest
        })
      );
      
      // Get the port message listener in background
      const portMessageHandler = mockPort.onMessage.addListener.mock.calls[0][0];
      
      // Simulate background receiving message from DevTools
      const message = {
        action: MessageUtils.MessageActionType.EXECUTE_METHOD,
        data: methodRequest
      };
      
      // Background processes port message from DevTools
      portMessageHandler(message);
      
      // Background should relay to content script
      expect(mockBrowserAPI.tabs.sendMessage).toHaveBeenCalledWith(
        123,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.EXECUTE_METHOD,
          data: methodRequest
        }),
        expect.any(Function)
      );
      
      // Setup mock content message listener
      const contentMessageListener = mockBrowserAPI.runtime.onMessage.addListener.mock.calls[0][0];
      
      // Simulate content script receiving message
      contentMessageListener(message, {}, () => {});
      
      // Content script handler should be called with method request
      expect(executeMethodHandler).toHaveBeenCalledWith(methodRequest, expect.any(Object));
    });
  });
  
  describe('Cross-Browser Compatibility', () => {
    test('message passing works consistently in Chrome', () => {
      // Configure for Chrome
      BrowserUtils.getBrowserInfo.mockReturnValue({ 
        type: 'chrome', 
        name: 'Chrome', 
        version: '100.0.0' 
      });
      BrowserUtils.isChrome.mockReturnValue(true);
      BrowserUtils.isFirefox.mockReturnValue(false);
      BrowserUtils.isEdge.mockReturnValue(false);
      
      const contentMessenger = new ContentScriptMessenger();
      
      // Send a message
      const data = { test: 'data' };
      contentMessenger.sendMessage(MessageUtils.MessageActionType.GET_COMPONENTS, data);
      
      // Should use Chrome's messaging API
      expect(MessageUtils.sendToBackground).toHaveBeenCalled();
    });
    
    test('message passing works consistently in Firefox', () => {
      // Configure for Firefox
      BrowserUtils.getBrowserInfo.mockReturnValue({ 
        type: 'firefox', 
        name: 'Firefox', 
        version: '95.0.0' 
      });
      BrowserUtils.isChrome.mockReturnValue(false);
      BrowserUtils.isFirefox.mockReturnValue(true);
      BrowserUtils.isEdge.mockReturnValue(false);
      
      const contentMessenger = new ContentScriptMessenger();
      
      // Send a message
      const data = { test: 'data' };
      contentMessenger.sendMessage(MessageUtils.MessageActionType.GET_COMPONENTS, data);
      
      // Should still use the same abstracted API, but with Firefox specific implementation
      expect(MessageUtils.sendToBackground).toHaveBeenCalled();
    });
  });
});
