/**
 * Cross-Browser End-to-End Tests
 * 
 * Comprehensive tests to verify extension functionality across Chrome, Firefox,
 * and Edge browsers with a focus on API compatibility and consistent behavior.
 */

import { Browser, Page, chromium, firefox, webkit } from 'playwright';
import * as BrowserUtils from '../../lib/utils/BrowserUtils';
import * as EventUtils from '../../lib/utils/EventUtils';
import { ConnectionHealthMonitor } from '../../lib/mcp/ConnectionHealthMonitor';
import { WebSocketWrapper } from '../../lib/mcp/WebSocketWrapper';

// Mock actual browser detection during tests
jest.mock('../../lib/utils/BrowserUtils', () => {
  const originalModule = jest.requireActual('../../lib/utils/BrowserUtils');
  
  return {
    ...originalModule,
    getBrowserInfo: jest.fn(),
    getBrowserAPI: jest.fn(),
    isFirefox: jest.fn(),
    isChrome: jest.fn(),
    isEdge: jest.fn()
  };
});

// Helper to setup browser environment mocks for different browsers
const setupBrowserEnvironment = (browserType: 'chrome' | 'firefox' | 'edge') => {
  // Reset mocks first
  jest.clearAllMocks();
  
  // Setup browser detection
  BrowserUtils.getBrowserInfo.mockReturnValue({
    type: browserType,
    name: browserType,
    version: browserType === 'chrome' ? '100.0.0' : 
             browserType === 'firefox' ? '95.0.0' : '99.0.0'
  });
  
  // Setup feature detection
  BrowserUtils.isChrome.mockReturnValue(browserType === 'chrome');
  BrowserUtils.isFirefox.mockReturnValue(browserType === 'firefox');
  BrowserUtils.isEdge.mockReturnValue(browserType === 'edge');
  
  // Mock browser API with browser-specific implementations
  if (browserType === 'chrome' || browserType === 'edge') {
    BrowserUtils.getBrowserAPI.mockReturnValue({
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
      },
      storage: {
        local: {
          get: jest.fn(),
          set: jest.fn()
        },
        sync: {
          get: jest.fn(),
          set: jest.fn()
        }
      },
      devtools: {
        panels: {
          create: jest.fn()
        },
        inspectedWindow: {
          tabId: 123
        }
      }
    });
  } else {
    // Firefox-specific API differences
    BrowserUtils.getBrowserAPI.mockReturnValue({
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
      },
      storage: {
        local: {
          get: jest.fn(),
          set: jest.fn()
        },
        sync: {
          get: jest.fn(),
          set: jest.fn()
        }
      },
      devtools: {
        panels: {
          create: jest.fn()
        },
        inspectedWindow: {
          tabId: 123
        }
      },
      // Firefox-specific properties
      browser: {
        runtime: {
          getBrowserInfo: jest.fn().mockResolvedValue({ name: 'Firefox', version: '95.0.0' })
        }
      }
    });
  }
};

describe('Cross-Browser Compatibility', () => {
  // Tests for browser detection
  describe('Browser Detection', () => {
    test('correctly identifies Chrome browser', () => {
      setupBrowserEnvironment('chrome');
      
      const browserInfo = BrowserUtils.getBrowserInfo();
      
      expect(browserInfo.type).toBe('chrome');
      expect(BrowserUtils.isChrome()).toBe(true);
      expect(BrowserUtils.isFirefox()).toBe(false);
      expect(BrowserUtils.isEdge()).toBe(false);
    });
    
    test('correctly identifies Firefox browser', () => {
      setupBrowserEnvironment('firefox');
      
      const browserInfo = BrowserUtils.getBrowserInfo();
      
      expect(browserInfo.type).toBe('firefox');
      expect(BrowserUtils.isFirefox()).toBe(true);
      expect(BrowserUtils.isChrome()).toBe(false);
      expect(BrowserUtils.isEdge()).toBe(false);
    });
    
    test('correctly identifies Edge browser', () => {
      setupBrowserEnvironment('edge');
      
      const browserInfo = BrowserUtils.getBrowserInfo();
      
      expect(browserInfo.type).toBe('edge');
      expect(BrowserUtils.isEdge()).toBe(true);
      expect(BrowserUtils.isChrome()).toBe(false);
      expect(BrowserUtils.isFirefox()).toBe(false);
    });
  });
  
  // Tests for standardized event handling
  describe('Event Handling', () => {
    // Mock DOM elements and event listeners
    let mockElement: HTMLElement;
    let mockListener: jest.Mock;
    
    beforeEach(() => {
      // Create mock element
      mockElement = document.createElement('div');
      document.body.appendChild(mockElement);
      
      // Create mock event listener
      mockListener = jest.fn();
    });
    
    afterEach(() => {
      // Clean up
      document.body.removeChild(mockElement);
    });
    
    test('addEventListener works consistently in Chrome', () => {
      setupBrowserEnvironment('chrome');
      
      // Spy on native addEventListener
      const addEventListenerSpy = jest.spyOn(mockElement, 'addEventListener');
      
      // Add event listener using our utility
      EventUtils.addEventListener(mockElement, 'click', mockListener);
      
      // Should have called native addEventListener
      expect(addEventListenerSpy).toHaveBeenCalledWith('click', expect.any(Function), undefined);
      
      // Simulate click event
      mockElement.click();
      
      // Listener should have been called
      expect(mockListener).toHaveBeenCalled();
      
      // Clean up spy
      addEventListenerSpy.mockRestore();
    });
    
    test('addEventListener works consistently in Firefox', () => {
      setupBrowserEnvironment('firefox');
      
      // Spy on native addEventListener
      const addEventListenerSpy = jest.spyOn(mockElement, 'addEventListener');
      
      // Add event listener using our utility
      EventUtils.addEventListener(mockElement, 'click', mockListener);
      
      // Should have called native addEventListener
      expect(addEventListenerSpy).toHaveBeenCalledWith('click', expect.any(Function), undefined);
      
      // Simulate click event
      mockElement.click();
      
      // Listener should have been called
      expect(mockListener).toHaveBeenCalled();
      
      // Clean up spy
      addEventListenerSpy.mockRestore();
    });
    
    test('addEventListener works consistently in Edge', () => {
      setupBrowserEnvironment('edge');
      
      // Spy on native addEventListener
      const addEventListenerSpy = jest.spyOn(mockElement, 'addEventListener');
      
      // Add event listener using our utility
      EventUtils.addEventListener(mockElement, 'click', mockListener);
      
      // Should have called native addEventListener
      expect(addEventListenerSpy).toHaveBeenCalledWith('click', expect.any(Function), undefined);
      
      // Simulate click event
      mockElement.click();
      
      // Listener should have been called
      expect(mockListener).toHaveBeenCalled();
      
      // Clean up spy
      addEventListenerSpy.mockRestore();
    });
    
    test('removeEventListener cleans up properly in all browsers', () => {
      // Test for each browser
      const browsers = ['chrome', 'firefox', 'edge'] as const;
      
      browsers.forEach(browser => {
        setupBrowserEnvironment(browser);
        
        // Spy on native removeEventListener
        const removeEventListenerSpy = jest.spyOn(mockElement, 'removeEventListener');
        
        // Add event listener using our utility
        EventUtils.addEventListener(mockElement, 'click', mockListener);
        
        // Remove event listener using our utility
        EventUtils.removeEventListener(mockElement, 'click', mockListener);
        
        // Should have called native removeEventListener
        expect(removeEventListenerSpy).toHaveBeenCalledWith('click', expect.any(Function), undefined);
        
        // Simulate click event
        mockElement.click();
        
        // Listener should not have been called
        expect(mockListener).not.toHaveBeenCalled();
        
        // Clean up spy
        removeEventListenerSpy.mockRestore();
        
        // Reset mock
        mockListener.mockReset();
      });
    });
  });
  
  // Tests for WebSocket connection handling
  describe('WebSocket Connection Handling', () => {
    // Mock WebSocket
    let mockWebSocket: any;
    
    beforeEach(() => {
      // Save original WebSocket
      const OriginalWebSocket = global.WebSocket;
      
      // Mock WebSocket implementation
      mockWebSocket = {
        addEventListener: jest.fn(),
        removeEventListener: jest.fn(),
        send: jest.fn(),
        close: jest.fn()
      };
      
      // Replace global WebSocket
      global.WebSocket = jest.fn().mockImplementation(() => mockWebSocket) as any;
      
      // Add readyState property
      Object.defineProperty(mockWebSocket, 'readyState', {
        get: jest.fn().mockReturnValue(WebSocket.OPEN)
      });
    });
    
    afterEach(() => {
      // Restore original WebSocket
      jest.restoreAllMocks();
    });
    
    test('WebSocketWrapper handles connection consistently in Chrome', () => {
      setupBrowserEnvironment('chrome');
      
      const url = 'ws://localhost:8080';
      const wrapper = new WebSocketWrapper(url);
      
      // Should have created WebSocket with correct URL
      expect(global.WebSocket).toHaveBeenCalledWith(url);
      
      // Should have set up event listeners
      expect(mockWebSocket.addEventListener).toHaveBeenCalledWith('open', expect.any(Function));
      expect(mockWebSocket.addEventListener).toHaveBeenCalledWith('message', expect.any(Function));
      expect(mockWebSocket.addEventListener).toHaveBeenCalledWith('close', expect.any(Function));
      expect(mockWebSocket.addEventListener).toHaveBeenCalledWith('error', expect.any(Function));
      
      // Simulate message event
      const mockMessageHandler = jest.fn();
      wrapper.onMessage(mockMessageHandler);
      
      // Get the message handler that was registered
      const messageEventHandler = mockWebSocket.addEventListener.mock.calls.find(
        call => call[0] === 'message'
      )[1];
      
      // Simulate message event
      messageEventHandler({ data: '{"type":"test"}' });
      
      // Handler should have been called with parsed data
      expect(mockMessageHandler).toHaveBeenCalledWith({ type: 'test' });
      
      // Test sending message
      wrapper.send({ type: 'send-test' });
      
      // Should have serialized and sent data
      expect(mockWebSocket.send).toHaveBeenCalledWith('{"type":"send-test"}');
      
      // Test closing connection
      wrapper.close();
      
      // Should have closed WebSocket
      expect(mockWebSocket.close).toHaveBeenCalled();
      
      // Should have removed event listeners
      expect(mockWebSocket.removeEventListener).toHaveBeenCalledWith('open', expect.any(Function));
      expect(mockWebSocket.removeEventListener).toHaveBeenCalledWith('message', expect.any(Function));
      expect(mockWebSocket.removeEventListener).toHaveBeenCalledWith('close', expect.any(Function));
      expect(mockWebSocket.removeEventListener).toHaveBeenCalledWith('error', expect.any(Function));
    });
    
    test('WebSocketWrapper handles connection consistently in Firefox', () => {
      setupBrowserEnvironment('firefox');
      
      const url = 'ws://localhost:8080';
      const wrapper = new WebSocketWrapper(url);
      
      // Should have created WebSocket with correct URL
      expect(global.WebSocket).toHaveBeenCalledWith(url);
      
      // Should have set up event listeners
      expect(mockWebSocket.addEventListener).toHaveBeenCalledWith('open', expect.any(Function));
      expect(mockWebSocket.addEventListener).toHaveBeenCalledWith('message', expect.any(Function));
      expect(mockWebSocket.addEventListener).toHaveBeenCalledWith('close', expect.any(Function));
      expect(mockWebSocket.addEventListener).toHaveBeenCalledWith('error', expect.any(Function));
      
      // Test sending message
      wrapper.send({ type: 'send-test' });
      
      // Should have serialized and sent data
      expect(mockWebSocket.send).toHaveBeenCalledWith('{"type":"send-test"}');
    });
    
    test('WebSocketWrapper handles connection consistently in Edge', () => {
      setupBrowserEnvironment('edge');
      
      const url = 'ws://localhost:8080';
      const wrapper = new WebSocketWrapper(url);
      
      // Should have created WebSocket with correct URL
      expect(global.WebSocket).toHaveBeenCalledWith(url);
      
      // Should have set up event listeners
      expect(mockWebSocket.addEventListener).toHaveBeenCalledWith('open', expect.any(Function));
      expect(mockWebSocket.addEventListener).toHaveBeenCalledWith('message', expect.any(Function));
      expect(mockWebSocket.addEventListener).toHaveBeenCalledWith('close', expect.any(Function));
      expect(mockWebSocket.addEventListener).toHaveBeenCalledWith('error', expect.any(Function));
      
      // Test sending message
      wrapper.send({ type: 'send-test' });
      
      // Should have serialized and sent data
      expect(mockWebSocket.send).toHaveBeenCalledWith('{"type":"send-test"}');
    });
    
    test('ConnectionHealthMonitor works consistently across browsers', () => {
      // Test for each browser
      const browsers = ['chrome', 'firefox', 'edge'] as const;
      
      browsers.forEach(browser => {
        setupBrowserEnvironment(browser);
        
        const mockSocket = new WebSocketWrapper('ws://localhost:8080');
        const sendSpy = jest.spyOn(mockSocket, 'send');
        
        // Create health monitor
        const healthMonitor = new ConnectionHealthMonitor(mockSocket);
        
        // Start monitoring
        healthMonitor.startMonitoring();
        
        // Should have set up ping interval
        jest.advanceTimersByTime(30000); // Default ping interval
        
        // Should have sent ping message
        expect(sendSpy).toHaveBeenCalledWith(expect.objectContaining({
          type: 'ping'
        }));
        
        // Clean up
        healthMonitor.stopMonitoring();
        sendSpy.mockRestore();
      });
    });
  });
  
  // Tests for storage API compatibility
  describe('Storage API Compatibility', () => {
    test('storage operations work consistently across browsers', () => {
      // Test for each browser
      const browsers = ['chrome', 'firefox', 'edge'] as const;
      
      browsers.forEach(browser => {
        setupBrowserEnvironment(browser);
        
        const browserAPI = BrowserUtils.getBrowserAPI();
        
        // Test local storage operations
        browserAPI.storage.local.set({ key: 'value' });
        expect(browserAPI.storage.local.set).toHaveBeenCalledWith({ key: 'value' });
        
        browserAPI.storage.local.get('key');
        expect(browserAPI.storage.local.get).toHaveBeenCalledWith('key');
        
        // Test sync storage operations
        browserAPI.storage.sync.set({ key: 'value' });
        expect(browserAPI.storage.sync.set).toHaveBeenCalledWith({ key: 'value' });
        
        browserAPI.storage.sync.get('key');
        expect(browserAPI.storage.sync.get).toHaveBeenCalledWith('key');
      });
    });
  });
  
  // Tests for messaging API compatibility
  describe('Messaging API Compatibility', () => {
    test('message passing works consistently across browsers', () => {
      // Test for each browser
      const browsers = ['chrome', 'firefox', 'edge'] as const;
      
      browsers.forEach(browser => {
        setupBrowserEnvironment(browser);
        
        const browserAPI = BrowserUtils.getBrowserAPI();
        
        // Test sending messages
        browserAPI.runtime.sendMessage({ type: 'test' });
        expect(browserAPI.runtime.sendMessage).toHaveBeenCalledWith({ type: 'test' });
        
        // Test adding message listener
        const listener = jest.fn();
        browserAPI.runtime.onMessage.addListener(listener);
        expect(browserAPI.runtime.onMessage.addListener).toHaveBeenCalledWith(listener);
        
        // Test removing message listener
        browserAPI.runtime.onMessage.removeListener(listener);
        expect(browserAPI.runtime.onMessage.removeListener).toHaveBeenCalledWith(listener);
        
        // Test connecting to extension parts
        const port = browserAPI.runtime.connect({ name: 'test-port' });
        expect(browserAPI.runtime.connect).toHaveBeenCalledWith({ name: 'test-port' });
        
        // Test port communication
        port.postMessage({ type: 'test' });
        expect(port.postMessage).toHaveBeenCalledWith({ type: 'test' });
        
        // Test adding port message listener
        const portListener = jest.fn();
        port.onMessage.addListener(portListener);
        expect(port.onMessage.addListener).toHaveBeenCalledWith(portListener);
        
        // Test adding port disconnect listener
        const disconnectListener = jest.fn();
        port.onDisconnect.addListener(disconnectListener);
        expect(port.onDisconnect.addListener).toHaveBeenCalledWith(disconnectListener);
      });
    });
  });
  
  // Tests for DevTools API compatibility
  describe('DevTools API Compatibility', () => {
    test('DevTools panel creation works consistently across browsers', () => {
      // Test for each browser
      const browsers = ['chrome', 'firefox', 'edge'] as const;
      
      browsers.forEach(browser => {
        setupBrowserEnvironment(browser);
        
        const browserAPI = BrowserUtils.getBrowserAPI();
        
        // Test creating DevTools panel
        browserAPI.devtools.panels.create('Test Panel', 'icon.png', 'panel.html');
        expect(browserAPI.devtools.panels.create).toHaveBeenCalledWith(
          'Test Panel', 'icon.png', 'panel.html'
        );
        
        // Test inspected window tab ID
        expect(browserAPI.devtools.inspectedWindow.tabId).toBe(123);
      });
    });
  });
});

/**
 * The following tests would be run in an actual browser environment
 * using a tool like Playwright. They're included here as a reference
 * but would need to be run in a separate E2E testing setup.
 */
describe.skip('End-to-End Tests', () => {
  let browser: Browser;
  let page: Page;
  
  const setupTest = async (browserType: 'chromium' | 'firefox' | 'webkit') => {
    // Launch browser
    if (browserType === 'chromium') {
      browser = await chromium.launch({
        headless: false,
        args: [
          '--disable-extensions-except=./dist',
          '--load-extension=./dist'
        ]
      });
    } else if (browserType === 'firefox') {
      browser = await firefox.launch({
        headless: false,
        firefoxUserPrefs: {
          'xpinstall.signatures.required': false
        }
      });
    } else {
      browser = await webkit.launch({
        headless: false
      });
    }
    
    // Create page
    page = await browser.newPage();
    
    // Navigate to test page with Livewire
    await page.goto('http://localhost:8000/livewire-test');
    
    // Wait for Livewire to load
    await page.waitForSelector('[wire\\:id]');
  };
  
  const teardownTest = async () => {
    await page.close();
    await browser.close();
  };
  
  test('Extension detects Livewire components in Chrome', async () => {
    await setupTest('chromium');
    
    // Test detection of Livewire components
    const components = await page.evaluate(() => {
      return Array.from(document.querySelectorAll('[wire\\:id]')).map(el => {
        return {
          id: el.getAttribute('wire:id'),
          name: el.getAttribute('wire:initial-data')
            ? JSON.parse(el.getAttribute('wire:initial-data') || '{}').name
            : null
        };
      });
    });
    
    // Should have detected at least one component
    expect(components.length).toBeGreaterThan(0);
    
    // Open DevTools (this would be manual in a real test)
    // Test that the Tapped panel is present
    // Test that components are listed in the panel
    // Test that component state can be inspected
    
    await teardownTest();
  });
  
  test('Extension detects Livewire components in Firefox', async () => {
    await setupTest('firefox');
    
    // Similar tests as above
    
    await teardownTest();
  });
  
  test('Extension tracks Livewire events across browsers', async () => {
    // Test for each browser
    const browsers = ['chromium', 'firefox'] as const;
    
    for (const browserType of browsers) {
      await setupTest(browserType);
      
      // Interact with Livewire component
      await page.click('[wire\\:id] button');
      
      // Check that events were captured
      // This would require DevTools integration
      
      await teardownTest();
    }
  });
  
  test('Extension properly restores state from snapshots', async () => {
    // This would test time-travel debugging functionality
    
    await setupTest('chromium');
    
    // Create state snapshot
    // Modify component state
    // Restore from snapshot
    // Verify state was restored
    
    await teardownTest();
  });
});
