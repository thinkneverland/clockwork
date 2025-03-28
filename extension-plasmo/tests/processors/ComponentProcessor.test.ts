/**
 * ComponentProcessor tests
 * 
 * Tests for the component data processing utility that handles
 * Livewire component hierarchy, property tracking, and state management.
 */

import { ComponentProcessor } from '../../lib/processors/ComponentProcessor';
import { BrowserUtils } from '../../lib/utils/BrowserUtils';
import * as StorageUtils from '../../lib/utils/StorageUtils';

// Mock dependencies
jest.mock('../../lib/utils/BrowserUtils', () => ({
  BrowserUtils: {
    getBrowserAPI: jest.fn(),
    getBrowserInfo: jest.fn()
  }
}));

jest.mock('../../lib/utils/StorageUtils', () => ({
  get: jest.fn(),
  set: jest.fn(),
  remove: jest.fn()
}));

describe('ComponentProcessor', () => {
  let processor: ComponentProcessor;
  
  // Sample component data for testing
  const mockComponents = [
    {
      id: 'comp-1',
      name: 'Counter',
      fingerprint: 'abc123',
      renderTime: 15,
      hash: 'hash1',
      ancestorIds: [],
      childrenIds: ['comp-2'],
      file: 'app/Http/Livewire/Counter.php',
      line: 10,
      properties: {
        count: {
          value: 0,
          type: 'number',
          visibility: 'public'
        },
        title: {
          value: 'My Counter',
          type: 'string',
          visibility: 'public'
        },
        computedValue: {
          value: 'Computed',
          type: 'string',
          visibility: 'public',
          computed: true
        }
      },
      methods: [
        {
          name: 'increment',
          params: []
        },
        {
          name: 'decrement',
          params: []
        },
        {
          name: 'setValue',
          params: ['value']
        }
      ],
      events: ['counter-updated', 'counter-reset'],
      mountTime: Date.now() - 5000
    },
    {
      id: 'comp-2',
      name: 'Display',
      fingerprint: 'def456',
      renderTime: 8,
      hash: 'hash2',
      ancestorIds: ['comp-1'],
      childrenIds: [],
      file: 'app/Http/Livewire/Display.php',
      line: 5,
      properties: {
        value: {
          value: 0,
          type: 'number',
          visibility: 'public'
        },
        format: {
          value: 'default',
          type: 'string',
          visibility: 'protected'
        }
      },
      methods: [
        {
          name: 'refresh',
          params: []
        }
      ],
      events: ['display-updated'],
      mountTime: Date.now() - 4500
    }
  ];
  
  beforeEach(() => {
    jest.clearAllMocks();
    
    // Setup storage mock
    StorageUtils.get.mockImplementation(async (key) => {
      if (key === 'component-cache') {
        return mockComponents;
      }
      if (key === 'component-history') {
        return {};
      }
      if (key === 'component-changes') {
        return {};
      }
      return null;
    });
    
    // Create processor with mock storage
    processor = new ComponentProcessor();
  });
  
  describe('component management', () => {
    test('initializes with existing components from storage', async () => {
      await processor.initialize();
      
      expect(StorageUtils.get).toHaveBeenCalledWith('component-cache');
      
      const components = await processor.getComponents();
      expect(components).toEqual(mockComponents);
    });
    
    test('processes new component data', async () => {
      await processor.initialize();
      
      const newComponent = {
        id: 'comp-3',
        name: 'NewComponent',
        fingerprint: 'ghi789',
        renderTime: 10,
        hash: 'hash3',
        ancestorIds: [],
        childrenIds: [],
        file: 'app/Http/Livewire/NewComponent.php',
        line: 15,
        properties: {
          enabled: {
            value: true,
            type: 'boolean',
            visibility: 'public'
          }
        },
        methods: [],
        events: [],
        mountTime: Date.now()
      };
      
      await processor.processComponent(newComponent);
      
      // Should save to storage
      expect(StorageUtils.set).toHaveBeenCalledWith(
        'component-cache',
        expect.arrayContaining([
          expect.objectContaining({ id: 'comp-1' }),
          expect.objectContaining({ id: 'comp-2' }),
          expect.objectContaining({ id: 'comp-3' })
        ])
      );
      
      // Should include the new component in results
      const components = await processor.getComponents();
      expect(components).toEqual(expect.arrayContaining([
        expect.objectContaining({ id: 'comp-3' })
      ]));
    });
    
    test('updates existing component data', async () => {
      await processor.initialize();
      
      // Update existing component
      const updatedComponent = {
        ...mockComponents[0],
        properties: {
          ...mockComponents[0].properties,
          count: {
            value: 5,
            type: 'number',
            visibility: 'public'
          }
        }
      };
      
      await processor.processComponent(updatedComponent);
      
      // Should update component in storage
      expect(StorageUtils.set).toHaveBeenCalledWith(
        'component-cache',
        expect.arrayContaining([
          expect.objectContaining({
            id: 'comp-1',
            properties: expect.objectContaining({
              count: expect.objectContaining({
                value: 5
              })
            })
          })
        ])
      );
      
      // Should include the updated component in results
      const components = await processor.getComponents();
      const updatedComp = components.find(c => c.id === 'comp-1');
      expect(updatedComp?.properties.count.value).toBe(5);
    });
    
    test('removes component when destroyed', async () => {
      await processor.initialize();
      
      await processor.removeComponent('comp-2');
      
      // Should update storage without the removed component
      expect(StorageUtils.set).toHaveBeenCalledWith(
        'component-cache',
        expect.not.arrayContaining([
          expect.objectContaining({ id: 'comp-2' })
        ])
      );
      
      // Should not include the removed component in results
      const components = await processor.getComponents();
      expect(components).toEqual([
        expect.objectContaining({ id: 'comp-1' })
      ]);
    });
    
    test('clears all components', async () => {
      await processor.initialize();
      
      await processor.clearComponents();
      
      // Should clear component cache
      expect(StorageUtils.set).toHaveBeenCalledWith('component-cache', []);
      
      // Should return empty array
      const components = await processor.getComponents();
      expect(components).toEqual([]);
    });
  });
  
  describe('component hierarchy', () => {
    test('builds component hierarchy tree', async () => {
      await processor.initialize();
      
      const hierarchy = await processor.buildComponentHierarchy();
      
      // Should have one root component
      expect(hierarchy).toEqual([
        {
          component: expect.objectContaining({ id: 'comp-1' }),
          children: [
            {
              component: expect.objectContaining({ id: 'comp-2' }),
              children: []
            }
          ]
        }
      ]);
    });
    
    test('finds component by ID', async () => {
      await processor.initialize();
      
      const component = await processor.getComponentById('comp-2');
      
      expect(component).toEqual(expect.objectContaining({
        id: 'comp-2',
        name: 'Display'
      }));
      
      // Non-existent component should return null
      const nonExistent = await processor.getComponentById('non-existent');
      expect(nonExistent).toBeNull();
    });
    
    test('finds component by name', async () => {
      await processor.initialize();
      
      const components = await processor.findComponentsByName('Counter');
      
      expect(components).toEqual([
        expect.objectContaining({
          id: 'comp-1',
          name: 'Counter'
        })
      ]);
      
      // Partial match should work too
      const partialMatches = await processor.findComponentsByName('Count');
      expect(partialMatches).toEqual([
        expect.objectContaining({
          id: 'comp-1',
          name: 'Counter'
        })
      ]);
      
      // Non-existent component should return empty array
      const nonExistent = await processor.findComponentsByName('NonExistent');
      expect(nonExistent).toEqual([]);
    });
    
    test('finds component ancestors', async () => {
      await processor.initialize();
      
      const ancestors = await processor.getComponentAncestors('comp-2');
      
      expect(ancestors).toEqual([
        expect.objectContaining({ id: 'comp-1' })
      ]);
      
      // Component without ancestors should return empty array
      const noAncestors = await processor.getComponentAncestors('comp-1');
      expect(noAncestors).toEqual([]);
    });
    
    test('finds component descendants', async () => {
      await processor.initialize();
      
      const descendants = await processor.getComponentDescendants('comp-1');
      
      expect(descendants).toEqual([
        expect.objectContaining({ id: 'comp-2' })
      ]);
      
      // Component without descendants should return empty array
      const noDescendants = await processor.getComponentDescendants('comp-2');
      expect(noDescendants).toEqual([]);
    });
  });
  
  describe('property tracking', () => {
    test('tracks property changes', async () => {
      await processor.initialize();
      
      // Initial state
      const initialComponent = mockComponents[0];
      
      // Updated state
      const updatedComponent = {
        ...initialComponent,
        properties: {
          ...initialComponent.properties,
          count: {
            value: 5,
            type: 'number',
            visibility: 'public'
          },
          title: {
            value: 'Updated Counter',
            type: 'string',
            visibility: 'public'
          }
        }
      };
      
      // Process update
      await processor.trackPropertyChanges(initialComponent, updatedComponent);
      
      // Should store changes
      expect(StorageUtils.set).toHaveBeenCalledWith(
        'component-changes',
        expect.objectContaining({
          [initialComponent.id]: expect.arrayContaining([
            expect.objectContaining({
              property: 'count',
              from: 0,
              to: 5,
              timestamp: expect.any(Number)
            }),
            expect.objectContaining({
              property: 'title',
              from: 'My Counter',
              to: 'Updated Counter',
              timestamp: expect.any(Number)
            })
          ])
        })
      );
      
      // Get tracked changes
      const changes = await processor.getPropertyChanges(initialComponent.id);
      
      expect(changes).toEqual([
        expect.objectContaining({
          property: 'count',
          from: 0,
          to: 5
        }),
        expect.objectContaining({
          property: 'title',
          from: 'My Counter',
          to: 'Updated Counter'
        })
      ]);
    });
    
    test('ignores unchanged properties', async () => {
      await processor.initialize();
      
      // Initial state
      const initialComponent = mockComponents[0];
      
      // Updated state with only one changed property
      const updatedComponent = {
        ...initialComponent,
        properties: {
          ...initialComponent.properties,
          count: {
            value: 5,
            type: 'number',
            visibility: 'public'
          }
          // title remains the same
        }
      };
      
      // Process update
      await processor.trackPropertyChanges(initialComponent, updatedComponent);
      
      // Get tracked changes
      const changes = await processor.getPropertyChanges(initialComponent.id);
      
      // Should only include changed property
      expect(changes).toEqual([
        expect.objectContaining({
          property: 'count',
          from: 0,
          to: 5
        })
      ]);
      
      // Should not include unchanged property
      expect(changes).not.toEqual(
        expect.arrayContaining([
          expect.objectContaining({
            property: 'title'
          })
        ])
      );
    });
    
    test('handles nested property changes', async () => {
      await processor.initialize();
      
      // Component with nested properties
      const componentWithNested = {
        ...mockComponents[0],
        properties: {
          ...mockComponents[0].properties,
          user: {
            value: {
              name: 'John',
              settings: {
                theme: 'light',
                notifications: true
              }
            },
            type: 'object',
            visibility: 'public'
          }
        }
      };
      
      // Updated with nested changes
      const updatedWithNested = {
        ...componentWithNested,
        properties: {
          ...componentWithNested.properties,
          user: {
            value: {
              name: 'John', // unchanged
              settings: {
                theme: 'dark', // changed
                notifications: true // unchanged
              }
            },
            type: 'object',
            visibility: 'public'
          }
        }
      };
      
      // Process update
      await processor.trackPropertyChanges(componentWithNested, updatedWithNested);
      
      // Get tracked changes
      const changes = await processor.getPropertyChanges(componentWithNested.id);
      
      // Should detect nested change
      expect(changes).toEqual([
        expect.objectContaining({
          property: 'user.settings.theme',
          from: 'light',
          to: 'dark'
        })
      ]);
    });
    
    test('clears property change history', async () => {
      await processor.initialize();
      
      // Setup some changes in the mock
      StorageUtils.get.mockImplementation(async (key) => {
        if (key === 'component-changes') {
          return {
            'comp-1': [
              {
                property: 'count',
                from: 0,
                to: 5,
                timestamp: Date.now() - 1000
              }
            ]
          };
        }
        return null;
      });
      
      await processor.clearPropertyChanges('comp-1');
      
      // Should clear changes for the component
      expect(StorageUtils.set).toHaveBeenCalledWith(
        'component-changes',
        expect.objectContaining({
          'comp-1': []
        })
      );
    });
  });
  
  describe('state editing', () => {
    test('validates property edits', async () => {
      await processor.initialize();
      
      // Valid edit for number property
      const validNumberEdit = await processor.validatePropertyEdit(
        'comp-1', 
        'count', 
        10
      );
      expect(validNumberEdit.valid).toBe(true);
      
      // Invalid edit (string for number property)
      const invalidNumberEdit = await processor.validatePropertyEdit(
        'comp-1', 
        'count', 
        'not-a-number'
      );
      expect(invalidNumberEdit.valid).toBe(false);
      
      // Valid edit for string property
      const validStringEdit = await processor.validatePropertyEdit(
        'comp-1', 
        'title', 
        'New Title'
      );
      expect(validStringEdit.valid).toBe(true);
      
      // Edit for computed property (should be invalid)
      const computedEdit = await processor.validatePropertyEdit(
        'comp-1', 
        'computedValue', 
        'New Value'
      );
      expect(computedEdit.valid).toBe(false);
      expect(computedEdit.reason).toContain('computed');
      
      // Edit for protected property (should be invalid)
      const protectedEdit = await processor.validatePropertyEdit(
        'comp-2', 
        'format', 
        'New Format'
      );
      expect(protectedEdit.valid).toBe(false);
      expect(protectedEdit.reason).toContain('protected');
      
      // Edit for non-existent property (should be invalid)
      const nonExistentEdit = await processor.validatePropertyEdit(
        'comp-1', 
        'nonExistent', 
        'value'
      );
      expect(nonExistentEdit.valid).toBe(false);
      expect(nonExistentEdit.reason).toContain('not found');
    });
    
    test('prepares property update payload', async () => {
      await processor.initialize();
      
      const payload = await processor.preparePropertyUpdatePayload(
        'comp-1',
        'count',
        10
      );
      
      expect(payload).toEqual({
        componentId: 'comp-1',
        fingerprint: 'abc123',
        property: 'count',
        value: 10
      });
    });
    
    test('handles batch property updates', async () => {
      await processor.initialize();
      
      const updates = [
        { property: 'count', value: 10 },
        { property: 'title', value: 'New Title' }
      ];
      
      const payloads = await processor.prepareBatchUpdatePayload('comp-1', updates);
      
      expect(payloads).toEqual([
        {
          componentId: 'comp-1',
          fingerprint: 'abc123',
          property: 'count',
          value: 10
        },
        {
          componentId: 'comp-1',
          fingerprint: 'abc123',
          property: 'title',
          value: 'New Title'
        }
      ]);
    });
  });
  
  describe('performance metrics', () => {
    test('tracks component render times', async () => {
      await processor.initialize();
      
      // Process a few render events
      await processor.recordRenderTime('comp-1', 12); // 12ms
      await processor.recordRenderTime('comp-1', 18); // 18ms
      await processor.recordRenderTime('comp-1', 15); // 15ms
      
      // Get render metrics
      const metrics = await processor.getRenderMetrics('comp-1');
      
      expect(metrics).toEqual({
        componentId: 'comp-1',
        renderCount: 3,
        averageRenderTime: 15, // (12 + 18 + 15) / 3
        minRenderTime: 12,
        maxRenderTime: 18,
        recentRenderTimes: [12, 18, 15]
      });
    });
    
    test('detects slow renders', async () => {
      await processor.initialize();
      
      // Set threshold (in ms) for slow renders
      await processor.setSlowRenderThreshold(20);
      
      // Record some render times
      await processor.recordRenderTime('comp-1', 15); // normal
      await processor.recordRenderTime('comp-1', 25); // slow
      await processor.recordRenderTime('comp-1', 18); // normal
      await processor.recordRenderTime('comp-1', 30); // slow
      
      // Get slow renders
      const slowRenders = await processor.getSlowRenders();
      
      expect(slowRenders).toEqual([
        {
          componentId: 'comp-1',
          componentName: 'Counter',
          renderTime: 25,
          timestamp: expect.any(Number)
        },
        {
          componentId: 'comp-1',
          componentName: 'Counter',
          renderTime: 30,
          timestamp: expect.any(Number)
        }
      ]);
    });
  });
  
  describe('cross-browser compatibility', () => {
    test('handles storage consistently across browsers', async () => {
      // Test for Chrome
      BrowserUtils.getBrowserInfo.mockReturnValue({ type: 'chrome', name: 'Chrome', version: '100.0.0' });
      
      const chromeProcessor = new ComponentProcessor();
      await chromeProcessor.initialize();
      await chromeProcessor.processComponent(mockComponents[0]);
      
      // Should use browser storage API consistently
      expect(StorageUtils.set).toHaveBeenCalledWith('component-cache', expect.any(Array));
      
      // Reset mocks
      jest.clearAllMocks();
      
      // Test for Firefox
      BrowserUtils.getBrowserInfo.mockReturnValue({ type: 'firefox', name: 'Firefox', version: '95.0.0' });
      
      const firefoxProcessor = new ComponentProcessor();
      await firefoxProcessor.initialize();
      await firefoxProcessor.processComponent(mockComponents[0]);
      
      // Should use browser storage API consistently
      expect(StorageUtils.set).toHaveBeenCalledWith('component-cache', expect.any(Array));
    });
  });
});
