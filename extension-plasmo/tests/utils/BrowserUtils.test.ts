/**
 * BrowserUtils tests
 * 
 * Tests the browser utilities to ensure consistent behavior
 * across different browser environments.
 */

import * as BrowserUtils from '../../lib/utils/BrowserUtils';

describe('BrowserUtils', () => {
  describe('getBrowserAPI', () => {
    const originalChrome = global.chrome;
    
    afterEach(() => {
      global.chrome = originalChrome;
    });
    
    test('returns chrome when available', () => {
      // Mock chrome API
      global.chrome = {
        runtime: {},
        tabs: {}
      } as any;
      
      expect(BrowserUtils.getBrowserAPI()).toBe(global.chrome);
    });
    
    test('returns empty object when no browser API is available', () => {
      global.chrome = undefined as any;
      
      const result = BrowserUtils.getBrowserAPI();
      expect(result).toEqual({});
    });
  });
  
  describe('getBrowserInfo', () => {
    test('returns browser information with type and version', () => {
      const info = BrowserUtils.getBrowserInfo();
      
      expect(info).toHaveProperty('type');
      expect(info).toHaveProperty('name');
      expect(info).toHaveProperty('version');
      expect(info).toHaveProperty('isExtensionContext');
    });
  });
  
  describe('sendMessage', () => {
    const originalChrome = global.chrome;
    
    beforeEach(() => {
      // Mock chrome API
      global.chrome = {
        runtime: {
          sendMessage: jest.fn((message, callback) => {
            callback({ success: true });
          }),
          lastError: null
        }
      } as any;
    });
    
    afterEach(() => {
      global.chrome = originalChrome;
    });
    
    test('sends message and returns response', async () => {
      const response = await BrowserUtils.sendMessage({ action: 'test' });
      
      expect(global.chrome.runtime.sendMessage).toHaveBeenCalledWith(
        { action: 'test' },
        expect.any(Function)
      );
      expect(response).toEqual({ success: true });
    });
    
    test('handles error in sendMessage', async () => {
      global.chrome.runtime.sendMessage = jest.fn((message, callback) => {
        global.chrome.runtime.lastError = { message: 'Error' };
        callback(undefined);
      });
      
      const response = await BrowserUtils.sendMessage({ action: 'test' });
      
      expect(response).toBeUndefined();
    });
    
    test('handles exceptions', async () => {
      global.chrome.runtime.sendMessage = jest.fn(() => {
        throw new Error('Failed');
      });
      
      const response = await BrowserUtils.sendMessage({ action: 'test' });
      
      expect(response).toBeUndefined();
    });
  });
  
  describe('sendMessageToTab', () => {
    const originalChrome = global.chrome;
    
    beforeEach(() => {
      // Mock chrome API
      global.chrome = {
        runtime: {
          lastError: null
        },
        tabs: {
          sendMessage: jest.fn((tabId, message, callback) => {
            callback({ success: true });
          })
        }
      } as any;
    });
    
    afterEach(() => {
      global.chrome = originalChrome;
    });
    
    test('sends message to tab and returns response', async () => {
      const response = await BrowserUtils.sendMessageToTab(123, { action: 'test' });
      
      expect(global.chrome.tabs.sendMessage).toHaveBeenCalledWith(
        123,
        { action: 'test' },
        expect.any(Function)
      );
      expect(response).toEqual({ success: true });
    });
    
    test('handles error in sendMessageToTab', async () => {
      global.chrome.runtime.lastError = { message: 'Error' };
      
      const response = await BrowserUtils.sendMessageToTab(123, { action: 'test' });
      
      expect(response).toBeUndefined();
    });
  });
  
  // Add more tests for other functions as needed
});
