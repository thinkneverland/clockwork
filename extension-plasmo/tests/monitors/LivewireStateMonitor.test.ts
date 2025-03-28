/**
 * LivewireStateMonitor tests
 * 
 * Tests for the system that detects, tracks, and observes changes to Livewire component states
 * in the browser, allowing real-time inspection and modification.
 */

import { LivewireStateMonitor } from '../../lib/monitors/LivewireStateMonitor';
import { ComponentProcessor } from '../../lib/processors/ComponentProcessor';
import * as MessageUtils from '../../lib/utils/MessageUtils';
import * as StorageUtils from '../../lib/utils/StorageUtils';

// Mock dependencies
jest.mock('../../lib/processors/ComponentProcessor');
jest.mock('../../lib/utils/MessageUtils', () => ({
  MessageActionType: {
    GET_COMPONENTS: 'GET_COMPONENTS',
    UPDATE_COMPONENT: 'UPDATE_COMPONENT'
  },
  createMessage: jest.fn((action, data) => ({ action, data })),
  sendToBackground: jest.fn(),
  sendThroughPort: jest.fn()
}));

jest.mock('../../lib/utils/StorageUtils', () => ({
  get: jest.fn(),
  set: jest.fn(),
  remove: jest.fn()
}));

describe('LivewireStateMonitor', () => {
  let monitor: LivewireStateMonitor;
  let mockComponentProcessor: jest.Mocked<ComponentProcessor>;
  
  // Simulated DOM with Livewire components for testing
  const setupDOM = () => {
    // Clear the document body
    document.body.innerHTML = '';
    
    // Create a Livewire component
    const counterComponent = document.createElement('div');
    counterComponent.setAttribute('wire:id', 'comp-1');
    counterComponent.setAttribute('wire:initial-data', JSON.stringify({
      fingerprint: 'abc123',
      id: 'comp-1',
      name: 'counter',
      data: {
        count: 0,
        title: 'Counter Component'
      },
      errors: [],
      events: []
    }));
    
    // Create a nested Livewire component
    const displayComponent = document.createElement('div');
    displayComponent.setAttribute('wire:id', 'comp-2');
    displayComponent.setAttribute('wire:initial-data', JSON.stringify({
      fingerprint: 'def456',
      id: 'comp-2',
      name: 'display',
      data: {
        value: 0,
        format: 'default'
      },
      errors: [],
      events: []
    }));
    
    // Add components to DOM
    counterComponent.appendChild(displayComponent);
    document.body.appendChild(counterComponent);
    
    // Create non-Livewire element
    const regularDiv = document.createElement('div');
    regularDiv.textContent = 'Regular DIV';
    document.body.appendChild(regularDiv);
  };
  
  beforeEach(() => {
    jest.clearAllMocks();
    setupDOM();
    
    // Setup mock component processor
    mockComponentProcessor = new ComponentProcessor() as jest.Mocked<ComponentProcessor>;
    mockComponentProcessor.processComponent.mockResolvedValue();
    mockComponentProcessor.removeComponent.mockResolvedValue();
    mockComponentProcessor.getComponentById.mockImplementation(async (id) => {
      if (id === 'comp-1') {
        return {
          id: 'comp-1',
          name: 'counter',
          fingerprint: 'abc123',
          properties: {
            count: { value: 0, type: 'number' },
            title: { value: 'Counter Component', type: 'string' }
          }
        };
      }
      if (id === 'comp-2') {
        return {
          id: 'comp-2',
          name: 'display',
          fingerprint: 'def456',
          properties: {
            value: { value: 0, type: 'number' },
            format: { value: 'default', type: 'string' }
          }
        };
      }
      return null;
    });
    
    // Create monitor
    monitor = new LivewireStateMonitor(mockComponentProcessor);
  });
  
  describe('component detection', () => {
    test('detects all Livewire components on the page', async () => {
      await monitor.initialize();
      
      // Should find both Livewire components
      expect(mockComponentProcessor.processComponent).toHaveBeenCalledTimes(2);
      
      // Should process the components with correct data
      expect(mockComponentProcessor.processComponent).toHaveBeenCalledWith(expect.objectContaining({
        id: 'comp-1',
        name: 'counter',
        fingerprint: 'abc123',
        properties: expect.objectContaining({
          count: expect.any(Object),
          title: expect.any(Object)
        })
      }));
      
      expect(mockComponentProcessor.processComponent).toHaveBeenCalledWith(expect.objectContaining({
        id: 'comp-2',
        name: 'display',
        fingerprint: 'def456',
        properties: expect.objectContaining({
          value: expect.any(Object),
          format: expect.any(Object)
        })
      }));
    });
    
    test('detects component hierarchy', async () => {
      await monitor.initialize();
      
      // The counter component should have display as a child
      expect(mockComponentProcessor.processComponent).toHaveBeenCalledWith(
        expect.objectContaining({
          id: 'comp-1',
          childrenIds: ['comp-2']
        })
      );
      
      // The display component should have counter as an ancestor
      expect(mockComponentProcessor.processComponent).toHaveBeenCalledWith(
        expect.objectContaining({
          id: 'comp-2',
          ancestorIds: ['comp-1']
        })
      );
    });
    
    test('detects dynamically added components', async () => {
      // Initialize first
      await monitor.initialize();
      
      // Reset processor mock to track new calls
      mockComponentProcessor.processComponent.mockClear();
      
      // Create a new component
      const newComponent = document.createElement('div');
      newComponent.setAttribute('wire:id', 'comp-3');
      newComponent.setAttribute('wire:initial-data', JSON.stringify({
        fingerprint: 'ghi789',
        id: 'comp-3',
        name: 'newComponent',
        data: {
          enabled: true
        },
        errors: [],
        events: []
      }));
      
      // Add it to the DOM
      document.body.appendChild(newComponent);
      
      // Manually trigger mutation observer callback
      (monitor as any).mutationCallback([{
        type: 'childList',
        addedNodes: [newComponent],
        removedNodes: []
      }]);
      
      // Should process the new component
      expect(mockComponentProcessor.processComponent).toHaveBeenCalledWith(
        expect.objectContaining({
          id: 'comp-3',
          name: 'newComponent',
          fingerprint: 'ghi789'
        })
      );
    });
    
    test('detects removed components', async () => {
      // Initialize first
      await monitor.initialize();
      
      // Get the Livewire component
      const counterComponent = document.querySelector('[wire\\:id="comp-1"]');
      
      // Remove it from DOM
      if (counterComponent && counterComponent.parentNode) {
        counterComponent.parentNode.removeChild(counterComponent);
      }
      
      // Manually trigger mutation observer callback
      (monitor as any).mutationCallback([{
        type: 'childList',
        addedNodes: [],
        removedNodes: [counterComponent]
      }]);
      
      // Should remove both components (parent and child)
      expect(mockComponentProcessor.removeComponent).toHaveBeenCalledWith('comp-1');
      expect(mockComponentProcessor.removeComponent).toHaveBeenCalledWith('comp-2');
    });
  });
  
  describe('livewire updates', () => {
    beforeEach(() => {
      // Mock window Livewire object
      (window as any).Livewire = {
        hook: jest.fn((name, callback) => {
          // Store the hook callback for testing
          (window as any).Livewire.hooks = (window as any).Livewire.hooks || {};
          (window as any).Livewire.hooks[name] = callback;
        }),
        find: jest.fn((id) => {
          // Simulate component object
          return {
            id,
            get: jest.fn((property) => {
              if (id === 'comp-1') {
                if (property === 'count') return 0;
                if (property === 'title') return 'Counter Component';
              }
              if (id === 'comp-2') {
                if (property === 'value') return 0;
                if (property === 'format') return 'default';
              }
              return null;
            }),
            call: jest.fn()
          };
        })
      };
    });
    
    test('listens for Livewire component updates', async () => {
      await monitor.initialize();
      
      // Verify hooks were registered
      expect((window as any).Livewire.hook).toHaveBeenCalledWith('element.initialized', expect.any(Function));
      expect((window as any).Livewire.hook).toHaveBeenCalledWith('element.updating', expect.any(Function));
      expect((window as any).Livewire.hook).toHaveBeenCalledWith('element.updated', expect.any(Function));
      expect((window as any).Livewire.hook).toHaveBeenCalledWith('element.removed', expect.any(Function));
      
      // Simulate component update
      const updateCallback = (window as any).Livewire.hooks['element.updated'];
      
      // Mock update data
      const updateData = {
        component: {
          id: 'comp-1',
          get: jest.fn((property) => {
            if (property === 'count') return 5; // Updated count
            if (property === 'title') return 'Counter Component';
            return null;
          }),
          name: 'counter',
          fingerprint: 'abc123',
          data: {
            count: 5, // Updated count
            title: 'Counter Component'
          }
        },
        snapshot: {
          data: {
            count: 5,
            title: 'Counter Component'
          }
        }
      };
      
      // Call update hook
      updateCallback(updateData);
      
      // Should process component with updated data
      expect(mockComponentProcessor.processComponent).toHaveBeenCalledWith(
        expect.objectContaining({
          id: 'comp-1',
          properties: expect.objectContaining({
            count: { value: 5, type: 'number' }
          })
        })
      );
    });
    
    test('tracks component property changes', async () => {
      await monitor.initialize();
      
      // Get update hook
      const updateCallback = (window as any).Livewire.hooks['element.updated'];
      
      // Mock previous state
      mockComponentProcessor.getComponentById.mockResolvedValueOnce({
        id: 'comp-1',
        name: 'counter',
        fingerprint: 'abc123',
        properties: {
          count: { value: 0, type: 'number' },
          title: { value: 'Counter Component', type: 'string' }
        }
      });
      
      // Mock update data with changed value
      const updateData = {
        component: {
          id: 'comp-1',
          name: 'counter',
          fingerprint: 'abc123',
          get: jest.fn((property) => {
            if (property === 'count') return 5; // Updated count
            if (property === 'title') return 'Counter Component';
            return null;
          }),
          data: {
            count: 5, // Updated count
            title: 'Counter Component'
          }
        },
        snapshot: {
          data: {
            count: 5,
            title: 'Counter Component'
          }
        }
      };
      
      // Call update hook
      await updateCallback(updateData);
      
      // Should track property changes
      expect(mockComponentProcessor.trackPropertyChanges).toHaveBeenCalledWith(
        expect.objectContaining({
          id: 'comp-1',
          properties: expect.objectContaining({
            count: { value: 0, type: 'number' }
          })
        }),
        expect.objectContaining({
          id: 'comp-1',
          properties: expect.objectContaining({
            count: { value: 5, type: 'number' }
          })
        })
      );
    });
    
    test('detects component methods', async () => {
      // Setup a Livewire component with methods in the prototype
      (window as any).Livewire.find.mockImplementation((id) => {
        if (id === 'comp-1') {
          const component = {
            id: 'comp-1',
            get: jest.fn((property) => {
              if (property === 'count') return 0;
              if (property === 'title') return 'Counter Component';
              return null;
            }),
            call: jest.fn()
          };
          
          // Add methods to prototype
          Object.setPrototypeOf(component, {
            increment: function() {},
            decrement: function() {},
            setValue: function(value) {}
          });
          
          return component;
        }
        return null;
      });
      
      await monitor.initialize();
      
      // Should detect component methods
      expect(mockComponentProcessor.processComponent).toHaveBeenCalledWith(
        expect.objectContaining({
          id: 'comp-1',
          methods: expect.arrayContaining([
            { name: 'increment', params: [] },
            { name: 'decrement', params: [] },
            { name: 'setValue', params: ['value'] }
          ])
        })
      );
    });
  });
  
  describe('component property updates', () => {
    test('can update component properties', async () => {
      await monitor.initialize();
      
      // Setup Livewire component mock
      const mockComponent = {
        id: 'comp-1',
        get: jest.fn(),
        set: jest.fn(),
        call: jest.fn()
      };
      (window as any).Livewire.find.mockReturnValue(mockComponent);
      
      // Update property
      await monitor.updateComponentProperty('comp-1', 'count', 10);
      
      // Should set the property value
      expect(mockComponent.set).toHaveBeenCalledWith('count', 10);
    });
    
    test('handles batch property updates', async () => {
      await monitor.initialize();
      
      // Setup Livewire component mock
      const mockComponent = {
        id: 'comp-1',
        get: jest.fn(),
        set: jest.fn(),
        call: jest.fn()
      };
      (window as any).Livewire.find.mockReturnValue(mockComponent);
      
      // Batch updates
      const updates = [
        { property: 'count', value: 10 },
        { property: 'title', value: 'Updated Counter' }
      ];
      
      await monitor.updateComponentBatch('comp-1', updates);
      
      // Should set all properties
      expect(mockComponent.set).toHaveBeenCalledWith('count', 10);
      expect(mockComponent.set).toHaveBeenCalledWith('title', 'Updated Counter');
    });
    
    test('can execute component methods', async () => {
      await monitor.initialize();
      
      // Setup Livewire component mock
      const mockComponent = {
        id: 'comp-1',
        get: jest.fn(),
        set: jest.fn(),
        call: jest.fn()
      };
      (window as any).Livewire.find.mockReturnValue(mockComponent);
      
      // Execute method without params
      await monitor.executeComponentMethod('comp-1', 'increment', []);
      
      // Should call the method
      expect(mockComponent.call).toHaveBeenCalledWith('increment');
      
      // Execute method with params
      await monitor.executeComponentMethod('comp-1', 'setValue', [42]);
      
      // Should call the method with params
      expect(mockComponent.call).toHaveBeenCalledWith('setValue', 42);
    });
  });
  
  describe('component highlighting', () => {
    test('highlights component in the page', async () => {
      await monitor.initialize();
      
      // Create spy for element style manipulation
      const addClassSpy = jest.spyOn(Element.prototype, 'classList', 'get');
      
      // Highlight component
      await monitor.highlightComponent('comp-1');
      
      // Should add highlight class to the element
      expect(addClassSpy).toHaveBeenCalled();
      
      // Clean up
      addClassSpy.mockRestore();
    });
  });
  
  describe('livewire version detection', () => {
    test('detects Livewire v2', async () => {
      // Mock Livewire v2
      (window as any).Livewire = {
        hook: jest.fn(),
        find: jest.fn(),
        version: '2.10.7'
      };
      
      await monitor.initialize();
      
      // Should detect Livewire v2
      expect((monitor as any).livewireVersion).toBe(2);
    });
    
    test('detects Livewire v3', async () => {
      // Mock Livewire v3
      (window as any).Livewire = {
        hook: jest.fn(),
        find: jest.fn(),
        version: '3.0.0'
      };
      
      await monitor.initialize();
      
      // Should detect Livewire v3
      expect((monitor as any).livewireVersion).toBe(3);
    });
    
    test('uses appropriate hooks for different Livewire versions', async () => {
      // Mock Livewire v2
      (window as any).Livewire = {
        hook: jest.fn(),
        find: jest.fn(),
        version: '2.10.7',
        components: { 
          components: {
            'comp-1': { id: 'comp-1', data: { count: 0 } }
          }
        }
      };
      
      await monitor.initialize();
      
      // Should use v2 specific hooks
      expect((window as any).Livewire.hook).toHaveBeenCalledWith('element.initialized', expect.any(Function));
      
      // Reset mock
      jest.clearAllMocks();
      
      // Mock Livewire v3
      (window as any).Livewire = {
        hook: jest.fn(),
        find: jest.fn(),
        version: '3.0.0',
        all: () => [{ id: 'comp-1', name: 'counter', data: { count: 0 } }]
      };
      
      await monitor.initialize();
      
      // Should use v3 specific hooks
      expect((window as any).Livewire.hook).toHaveBeenCalledWith('component.initialized', expect.any(Function));
    });
  });
  
  describe('error handling', () => {
    test('gracefully handles components without proper wire:initial-data', async () => {
      // Create a malformed component
      const malformedComponent = document.createElement('div');
      malformedComponent.setAttribute('wire:id', 'comp-bad');
      malformedComponent.setAttribute('wire:initial-data', 'not-valid-json');
      document.body.appendChild(malformedComponent);
      
      // Initialize should not throw
      await expect(monitor.initialize()).resolves.not.toThrow();
      
      // Should still process valid components
      expect(mockComponentProcessor.processComponent).toHaveBeenCalledWith(
        expect.objectContaining({ id: 'comp-1' })
      );
    });
    
    test('handles missing Livewire object', async () => {
      // Remove Livewire object
      delete (window as any).Livewire;
      
      // Initialize should not throw
      await expect(monitor.initialize()).resolves.not.toThrow();
      
      // Should set livewireDetected to false
      expect((monitor as any).livewireDetected).toBe(false);
    });
  });
  
  describe('observer cleanup', () => {
    test('disconnects observers when stopped', async () => {
      await monitor.initialize();
      
      // Mock disconnect methods
      const mutationObserverDisconnect = jest.fn();
      const resizeObserverDisconnect = jest.fn();
      
      // Replace observers with mocks
      (monitor as any).mutationObserver = { disconnect: mutationObserverDisconnect };
      (monitor as any).resizeObserver = { disconnect: resizeObserverDisconnect };
      
      // Stop monitoring
      await monitor.stop();
      
      // Should disconnect observers
      expect(mutationObserverDisconnect).toHaveBeenCalled();
      expect(resizeObserverDisconnect).toHaveBeenCalled();
    });
  });
});
