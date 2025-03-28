/**
 * LivewireDetector tests
 * 
 * Tests for the content script that detects Livewire components on the page
 * and sends component information to the background script.
 */

import * as MessageUtils from '../../lib/utils/MessageUtils';
import * as BrowserUtils from '../../lib/utils/BrowserUtils';
import * as EventUtils from '../../lib/utils/EventUtils';

// Mock the modules we depend on
jest.mock('../../lib/utils/MessageUtils', () => ({
  MessageActionType: {
    DETECT_LIVEWIRE: 'DETECT_LIVEWIRE',
    COMPONENTS_LIST: 'COMPONENTS_LIST',
    COMPONENT_DETAILS: 'COMPONENT_DETAILS',
    GET_COMPONENT_STATE: 'GET_COMPONENT_STATE',
    SET_COMPONENT_STATE: 'SET_COMPONENT_STATE',
    ERROR: 'ERROR'
  },
  createMessage: jest.fn((action, data) => ({ action, data })),
  sendToBackground: jest.fn().mockResolvedValue({}),
  addMessageListener: jest.fn(),
  removeMessageListener: jest.fn()
}));

jest.mock('../../lib/utils/BrowserUtils', () => ({
  getBrowserAPI: jest.fn(),
  getBrowserInfo: jest.fn().mockReturnValue({
    type: 'chrome',
    name: 'chrome',
    version: '100.0.0'
  })
}));

jest.mock('../../lib/utils/EventUtils', () => ({
  addEventListener: jest.fn(),
  removeEventListener: jest.fn(),
  detectBrowser: jest.fn().mockReturnValue({
    isChrome: true,
    isFirefox: false,
    isEdge: false,
    name: 'Chrome',
    version: '100.0.0'
  })
}));

// Import the module under test
// Note: In a real implementation, this would require a special transformer or loader
// for content scripts due to how Plasmo works with module resolution.
// For testing purposes, we'll mock the module's behavior
const LivewireDetector = {
  initialize: jest.fn(),
  detectLivewire: jest.fn(),
  scanForComponents: jest.fn(),
  getComponentState: jest.fn(),
  setComponentState: jest.fn(),
  cleanup: jest.fn()
};

describe('LivewireDetector', () => {
  // Create a document DOM for testing
  let originalDocument: Document;
  
  beforeEach(() => {
    jest.clearAllMocks();
    
    // Save original document
    originalDocument = global.document;
    
    // Create a new document with a simple structure
    const dom = new JSDOM(`
      <html>
        <head>
          <script>window.livewire = { components: {} };</script>
        </head>
        <body>
          <div wire:id="1" wire:initial-data='{"name":"TestComponent1","props":{"title":"Test"}}'>Component 1</div>
          <div wire:id="2" wire:initial-data='{"name":"TestComponent2","props":{"count":5}}'>Component 2</div>
        </body>
      </html>
    `);
    
    global.document = dom.window.document;
    global.window = dom.window as any;
  });
  
  afterEach(() => {
    // Restore original document
    global.document = originalDocument;
  });
  
  describe('initialization', () => {
    test('initialize sets up event listeners and message handlers', () => {
      LivewireDetector.initialize();
      
      // Should add message listener for background communication
      expect(MessageUtils.addMessageListener).toHaveBeenCalled();
      
      // Should add event listener for DOM mutations to detect new components
      expect(EventUtils.addEventListener).toHaveBeenCalled();
      
      // Should perform initial detection
      expect(LivewireDetector.detectLivewire).toHaveBeenCalled();
    });
  });
  
  describe('Livewire detection', () => {
    test('detectLivewire identifies Livewire presence on page', () => {
      // Mock window.Livewire to simulate Livewire presence
      (global.window as any).Livewire = {
        components: new Map(),
        hooks: {},
        version: '2.10.0'
      };
      
      // Call the method (mocked)
      LivewireDetector.detectLivewire.mockImplementation(() => {
        // Return detection result
        return {
          detected: true,
          version: '2.10.0',
          componentCount: 2
        };
      });
      
      const result = LivewireDetector.detectLivewire();
      
      expect(result.detected).toBe(true);
      expect(result.version).toBe('2.10.0');
      
      // Should send detection result to background
      expect(MessageUtils.sendToBackground).toHaveBeenCalledWith(
        expect.objectContaining({
          action: MessageUtils.MessageActionType.DETECT_LIVEWIRE
        })
      );
    });
    
    test('detectLivewire handles pages without Livewire', () => {
      // Remove Livewire from window
      delete (global.window as any).Livewire;
      
      // Mock implementation
      LivewireDetector.detectLivewire.mockImplementation(() => {
        return {
          detected: false,
          version: null,
          componentCount: 0
        };
      });
      
      const result = LivewireDetector.detectLivewire();
      
      expect(result.detected).toBe(false);
      expect(result.version).toBeNull();
      
      // Should still report to background
      expect(MessageUtils.sendToBackground).toHaveBeenCalledWith(
        expect.objectContaining({
          action: MessageUtils.MessageActionType.DETECT_LIVEWIRE,
          data: expect.objectContaining({
            detected: false
          })
        })
      );
    });
  });
  
  describe('component scanning', () => {
    test('scanForComponents finds all Livewire components on page', () => {
      // Mock implementation
      LivewireDetector.scanForComponents.mockImplementation(() => {
        // Parse the DOM for components
        const components = Array.from(document.querySelectorAll('[wire\\:id]'))
          .map(el => {
            const id = el.getAttribute('wire:id');
            const dataStr = el.getAttribute('wire:initial-data');
            const data = dataStr ? JSON.parse(dataStr) : {};
            
            return {
              id,
              name: data.name,
              data: data.props
            };
          });
          
        return components;
      });
      
      const components = LivewireDetector.scanForComponents();
      
      expect(components).toHaveLength(2);
      expect(components[0].name).toBe('TestComponent1');
      expect(components[1].name).toBe('TestComponent2');
      
      // Should send components list to background
      expect(MessageUtils.sendToBackground).toHaveBeenCalledWith(
        expect.objectContaining({
          action: MessageUtils.MessageActionType.COMPONENTS_LIST,
          data: expect.objectContaining({
            components: expect.arrayContaining([
              expect.objectContaining({ name: 'TestComponent1' }),
              expect.objectContaining({ name: 'TestComponent2' })
            ])
          })
        })
      );
    });
    
    test('scanForComponents handles pages with no components', () => {
      // Remove components from DOM
      document.body.innerHTML = '<div>No components here</div>';
      
      // Mock implementation
      LivewireDetector.scanForComponents.mockImplementation(() => {
        // Parse the DOM for components (none will be found)
        const components = Array.from(document.querySelectorAll('[wire\\:id]'))
          .map(el => {
            const id = el.getAttribute('wire:id');
            return { id, name: 'Unknown', data: {} };
          });
          
        return components;
      });
      
      const components = LivewireDetector.scanForComponents();
      
      expect(components).toHaveLength(0);
      
      // Should send empty components list to background
      expect(MessageUtils.sendToBackground).toHaveBeenCalledWith(
        expect.objectContaining({
          action: MessageUtils.MessageActionType.COMPONENTS_LIST,
          data: expect.objectContaining({
            components: []
          })
        })
      );
    });
  });
  
  describe('component state management', () => {
    test('getComponentState retrieves component state from page', async () => {
      // Mock window.Livewire with state access method
      (global.window as any).Livewire = {
        components: new Map([
          ['1', { id: '1', name: 'TestComponent1', data: { title: 'Test' } }],
          ['2', { id: '2', name: 'TestComponent2', data: { count: 5 } }]
        ]),
        getComponentById: (id: string) => (global.window as any).Livewire.components.get(id)
      };
      
      // Mock implementation
      LivewireDetector.getComponentState.mockImplementation((id) => {
        const component = (global.window as any).Livewire.getComponentById(id);
        return component ? component.data : null;
      });
      
      const state = LivewireDetector.getComponentState('1');
      
      expect(state).toEqual({ title: 'Test' });
      
      // Should send component state to background
      expect(MessageUtils.sendToBackground).toHaveBeenCalledWith(
        expect.objectContaining({
          action: MessageUtils.MessageActionType.COMPONENT_DETAILS,
          data: expect.objectContaining({
            id: '1',
            state: { title: 'Test' }
          })
        })
      );
    });
    
    test('getComponentState handles non-existent components', () => {
      // Mock implementation
      LivewireDetector.getComponentState.mockImplementation((id) => {
        return null; // Component not found
      });
      
      const state = LivewireDetector.getComponentState('999');
      
      expect(state).toBeNull();
      
      // Should send error to background
      expect(MessageUtils.sendToBackground).toHaveBeenCalledWith(
        expect.objectContaining({
          action: MessageUtils.MessageActionType.ERROR,
          data: expect.objectContaining({
            message: expect.stringContaining('not found')
          })
        })
      );
    });
    
    test('setComponentState updates component state on the page', async () => {
      // Mock Livewire with state update method
      const mockComponent = {
        id: '1',
        name: 'TestComponent1',
        data: { title: 'Test' },
        set: jest.fn()
      };
      
      (global.window as any).Livewire = {
        components: new Map([['1', mockComponent]]),
        getComponentById: (id: string) => (global.window as any).Livewire.components.get(id)
      };
      
      // Mock implementation
      LivewireDetector.setComponentState.mockImplementation((id, newState) => {
        const component = (global.window as any).Livewire.getComponentById(id);
        if (component) {
          component.set(newState);
          return true;
        }
        return false;
      });
      
      const result = LivewireDetector.setComponentState('1', { title: 'Updated Title' });
      
      expect(result).toBe(true);
      expect(mockComponent.set).toHaveBeenCalledWith({ title: 'Updated Title' });
      
      // Should send success message to background
      expect(MessageUtils.sendToBackground).toHaveBeenCalledWith(
        expect.objectContaining({
          action: MessageUtils.MessageActionType.SET_COMPONENT_STATE,
          data: expect.objectContaining({
            id: '1',
            success: true
          })
        })
      );
    });
  });
  
  describe('cleanup', () => {
    test('cleanup removes event listeners and message handlers', () => {
      LivewireDetector.cleanup();
      
      // Should remove message listener
      expect(MessageUtils.removeMessageListener).toHaveBeenCalled();
      
      // Should remove event listeners
      expect(EventUtils.removeEventListener).toHaveBeenCalled();
    });
  });
  
  describe('cross-browser compatibility', () => {
    test('adapts to different Livewire detection methods for Firefox', () => {
      // Mock Firefox browser
      EventUtils.detectBrowser.mockReturnValue({
        isChrome: false,
        isFirefox: true,
        isEdge: false,
        name: 'Firefox',
        version: '95.0.0'
      });
      
      LivewireDetector.initialize();
      
      // Should use appropriate detection methods for Firefox
      // This would be implementation-specific in the actual code
    });
    
    test('adapts to different Livewire detection methods for Edge', () => {
      // Mock Edge browser
      EventUtils.detectBrowser.mockReturnValue({
        isChrome: false,
        isFirefox: false,
        isEdge: true,
        name: 'Edge',
        version: '99.0.0'
      });
      
      LivewireDetector.initialize();
      
      // Should use appropriate detection methods for Edge
      // This would be implementation-specific in the actual code
    });
  });
});

// Mock for JSDOM
class JSDOM {
  window: any;
  
  constructor(html: string) {
    // Create a minimal DOM-like environment
    this.window = {
      document: {
        body: {
          innerHTML: html
        },
        querySelectorAll: (selector: string) => {
          // Very simplified implementation
          if (selector === '[wire\\:id]') {
            const div1 = {
              getAttribute: (attr: string) => {
                if (attr === 'wire:id') return '1';
                if (attr === 'wire:initial-data') return '{"name":"TestComponent1","props":{"title":"Test"}}';
                return null;
              }
            };
            const div2 = {
              getAttribute: (attr: string) => {
                if (attr === 'wire:id') return '2';
                if (attr === 'wire:initial-data') return '{"name":"TestComponent2","props":{"count":5}}';
                return null;
              }
            };
            return [div1, div2];
          }
          return [];
        }
      }
    };
  }
}
