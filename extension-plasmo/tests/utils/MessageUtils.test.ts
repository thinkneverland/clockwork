/**
 * MessageUtils tests
 * 
 * Tests the message passing utilities to ensure type-safe and reliable
 * communication between content scripts, background scripts, and DevTools panels.
 */

import * as MessageUtils from '../../lib/utils/MessageUtils';
import * as BrowserUtils from '../../lib/utils/BrowserUtils';

// Mock BrowserUtils
jest.mock('../../lib/utils/BrowserUtils', () => ({
  getBrowserAPI: jest.fn()
}));

describe('MessageUtils', () => {
  describe('createMessage', () => {
    test('creates a properly formatted message', () => {
      const action = MessageUtils.MessageActionType.DETECT_LIVEWIRE;
      const data = { foo: 'bar' };
      const tabId = 123;
      
      const message = MessageUtils.createMessage(action, data, tabId);
      
      expect(message).toEqual({
        action,
        data,
        tabId,
        timestamp: expect.any(Number)
      });
    });
    
    test('sets timestamp automatically', () => {
      const now = Date.now();
      jest.spyOn(Date, 'now').mockReturnValue(now);
      
      const message = MessageUtils.createMessage(MessageUtils.MessageActionType.ERROR);
      
      expect(message.timestamp).toBe(now);
      
      // Restore Date.now
      jest.restoreAllMocks();
    });
  });
  
  describe('isMessageAction', () => {
    test('returns true for matching action', () => {
      const message = {
        action: MessageUtils.MessageActionType.CONNECT_SERVER,
        data: { url: 'ws://localhost:8080' }
      };
      
      const result = MessageUtils.isMessageAction(message, MessageUtils.MessageActionType.CONNECT_SERVER);
      
      expect(result).toBe(true);
    });
    
    test('returns false for non-matching action', () => {
      const message = {
        action: MessageUtils.MessageActionType.ERROR,
        error: 'Something went wrong'
      };
      
      const result = MessageUtils.isMessageAction(message, MessageUtils.MessageActionType.CONNECT_SERVER);
      
      expect(result).toBe(false);
    });
  });
  
  describe('sendToBackground', () => {
    beforeEach(() => {
      const mockRuntime = {
        sendMessage: jest.fn((message, callback) => {
          callback({ success: true });
        }),
        lastError: null
      };
      
      (BrowserUtils.getBrowserAPI as jest.Mock).mockReturnValue({
        runtime: mockRuntime
      });
    });
    
    test('sends message to background script', async () => {
      const message = MessageUtils.createMessage(MessageUtils.MessageActionType.DETECT_LIVEWIRE);
      
      const response = await MessageUtils.sendToBackground(message);
      
      const api = BrowserUtils.getBrowserAPI();
      expect(api.runtime.sendMessage).toHaveBeenCalledWith(
        expect.objectContaining({
          action: MessageUtils.MessageActionType.DETECT_LIVEWIRE,
          timestamp: expect.any(Number)
        }),
        expect.any(Function)
      );
      expect(response).toEqual({ success: true });
    });
    
    test('handles runtime error', async () => {
      const api = BrowserUtils.getBrowserAPI();
      api.runtime.lastError = { message: 'Error sending message' };
      
      const message = MessageUtils.createMessage(MessageUtils.MessageActionType.DETECT_LIVEWIRE);
      const response = await MessageUtils.sendToBackground(message);
      
      expect(response).toBeUndefined();
    });
    
    test('handles missing runtime API', async () => {
      (BrowserUtils.getBrowserAPI as jest.Mock).mockReturnValue({});
      
      const message = MessageUtils.createMessage(MessageUtils.MessageActionType.DETECT_LIVEWIRE);
      const response = await MessageUtils.sendToBackground(message);
      
      expect(response).toBeUndefined();
    });
  });
  
  describe('sendToTab', () => {
    beforeEach(() => {
      const mockTabs = {
        sendMessage: jest.fn((tabId, message, callback) => {
          callback({ success: true });
        })
      };
      
      const mockRuntime = {
        lastError: null
      };
      
      (BrowserUtils.getBrowserAPI as jest.Mock).mockReturnValue({
        tabs: mockTabs,
        runtime: mockRuntime
      });
    });
    
    test('sends message to specific tab', async () => {
      const tabId = 123;
      const message = MessageUtils.createMessage(MessageUtils.MessageActionType.DETECT_LIVEWIRE);
      
      const response = await MessageUtils.sendToTab(tabId, message);
      
      const api = BrowserUtils.getBrowserAPI();
      expect(api.tabs.sendMessage).toHaveBeenCalledWith(
        tabId,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.DETECT_LIVEWIRE,
          tabId,
          timestamp: expect.any(Number)
        }),
        expect.any(Function)
      );
      expect(response).toEqual({ success: true });
    });
    
    test('handles runtime error', async () => {
      const api = BrowserUtils.getBrowserAPI();
      api.runtime.lastError = { message: 'Error sending message' };
      
      const tabId = 123;
      const message = MessageUtils.createMessage(MessageUtils.MessageActionType.DETECT_LIVEWIRE);
      const response = await MessageUtils.sendToTab(tabId, message);
      
      expect(response).toBeUndefined();
    });
    
    test('handles missing tabs API', async () => {
      (BrowserUtils.getBrowserAPI as jest.Mock).mockReturnValue({
        runtime: { lastError: null }
      });
      
      const tabId = 123;
      const message = MessageUtils.createMessage(MessageUtils.MessageActionType.DETECT_LIVEWIRE);
      const response = await MessageUtils.sendToTab(tabId, message);
      
      expect(response).toBeUndefined();
    });
  });
  
  describe('broadcastToDevTools', () => {
    test('sends message via runtime API', () => {
      const mockRuntime = {
        sendMessage: jest.fn()
      };
      
      (BrowserUtils.getBrowserAPI as jest.Mock).mockReturnValue({
        runtime: mockRuntime
      });
      
      const message = MessageUtils.createMessage(MessageUtils.MessageActionType.COMPONENTS_LIST);
      MessageUtils.broadcastToDevTools(message);
      
      expect(mockRuntime.sendMessage).toHaveBeenCalledWith(
        expect.objectContaining({
          action: MessageUtils.MessageActionType.COMPONENTS_LIST,
          timestamp: expect.any(Number)
        })
      );
    });
    
    test('handles missing runtime API', () => {
      (BrowserUtils.getBrowserAPI as jest.Mock).mockReturnValue({});
      
      const message = MessageUtils.createMessage(MessageUtils.MessageActionType.COMPONENTS_LIST);
      
      // Should not throw error
      expect(() => MessageUtils.broadcastToDevTools(message)).not.toThrow();
    });
  });
  
  describe('addMessageListener', () => {
    test('adds listener to runtime.onMessage', () => {
      const mockOnMessage = {
        addListener: jest.fn()
      };
      
      (BrowserUtils.getBrowserAPI as jest.Mock).mockReturnValue({
        runtime: { onMessage: mockOnMessage }
      });
      
      const callback = jest.fn();
      MessageUtils.addMessageListener(callback);
      
      expect(mockOnMessage.addListener).toHaveBeenCalledWith(callback);
    });
    
    test('handles missing runtime API', () => {
      (BrowserUtils.getBrowserAPI as jest.Mock).mockReturnValue({});
      
      const callback = jest.fn();
      
      // Should not throw error
      expect(() => MessageUtils.addMessageListener(callback)).not.toThrow();
    });
  });
  
  describe('removeMessageListener', () => {
    test('removes listener from runtime.onMessage', () => {
      const mockOnMessage = {
        removeListener: jest.fn()
      };
      
      (BrowserUtils.getBrowserAPI as jest.Mock).mockReturnValue({
        runtime: { onMessage: mockOnMessage }
      });
      
      const callback = jest.fn();
      MessageUtils.removeMessageListener(callback);
      
      expect(mockOnMessage.removeListener).toHaveBeenCalledWith(callback);
    });
    
    test('handles missing runtime API', () => {
      (BrowserUtils.getBrowserAPI as jest.Mock).mockReturnValue({});
      
      const callback = jest.fn();
      
      // Should not throw error
      expect(() => MessageUtils.removeMessageListener(callback)).not.toThrow();
    });
  });
  
  describe('createConnection', () => {
    test('creates a port connection', () => {
      const mockRuntime = {
        connect: jest.fn().mockReturnValue({ name: 'test-port' })
      };
      
      (BrowserUtils.getBrowserAPI as jest.Mock).mockReturnValue({
        runtime: mockRuntime
      });
      
      const result = MessageUtils.createConnection('test-connection');
      
      expect(mockRuntime.connect).toHaveBeenCalledWith(undefined, {
        name: 'test-connection'
      });
      expect(result).toEqual({ name: 'test-port' });
    });
    
    test('throws when runtime API is missing', () => {
      (BrowserUtils.getBrowserAPI as jest.Mock).mockReturnValue({});
      
      expect(() => MessageUtils.createConnection('test-connection')).toThrow('Runtime API not available');
    });
  });
  
  describe('port messaging', () => {
    let mockPort: any;
    
    beforeEach(() => {
      mockPort = {
        postMessage: jest.fn(),
        onMessage: {
          addListener: jest.fn(),
          removeListener: jest.fn()
        }
      };
    });
    
    test('sendThroughPort sends message with timestamp', () => {
      const now = Date.now();
      jest.spyOn(Date, 'now').mockReturnValue(now);
      
      const message = MessageUtils.createMessage(MessageUtils.MessageActionType.COMPONENT_DETAILS);
      MessageUtils.sendThroughPort(mockPort, message);
      
      expect(mockPort.postMessage).toHaveBeenCalledWith(
        expect.objectContaining({
          action: MessageUtils.MessageActionType.COMPONENT_DETAILS,
          timestamp: now
        })
      );
      
      // Restore Date.now
      jest.restoreAllMocks();
    });
    
    test('addPortListener adds listener to port.onMessage', () => {
      const callback = jest.fn();
      MessageUtils.addPortListener(mockPort, callback);
      
      expect(mockPort.onMessage.addListener).toHaveBeenCalledWith(callback);
    });
    
    test('removePortListener removes listener from port.onMessage', () => {
      const callback = jest.fn();
      MessageUtils.removePortListener(mockPort, callback);
      
      expect(mockPort.onMessage.removeListener).toHaveBeenCalledWith(callback);
    });
  });
});
