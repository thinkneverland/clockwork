/**
 * MCP MessageFormatter tests
 * 
 * Tests for the utility that formats and parses messages for the MCP protocol
 * used for communication between the extension and the Tapped server.
 */

import { MessageFormatter } from '../../lib/mcp/MessageFormatter';

describe('MessageFormatter', () => {
  let formatter: MessageFormatter;
  
  beforeEach(() => {
    formatter = new MessageFormatter();
  });
  
  describe('message formatting', () => {
    test('formats component request message correctly', () => {
      const message = formatter.formatComponentRequest('123', 'component-id-456');
      
      expect(message).toEqual({
        type: 'request',
        id: '123',
        action: 'get_component',
        data: {
          component_id: 'component-id-456'
        }
      });
    });
    
    test('formats component list request message correctly', () => {
      const message = formatter.formatComponentListRequest('456');
      
      expect(message).toEqual({
        type: 'request',
        id: '456',
        action: 'get_components',
        data: {}
      });
    });
    
    test('formats component state update message correctly', () => {
      const message = formatter.formatComponentStateUpdate('789', 'component-id-123', 'title', 'New Title');
      
      expect(message).toEqual({
        type: 'request',
        id: '789',
        action: 'update_component_state',
        data: {
          component_id: 'component-id-123',
          path: 'title',
          value: 'New Title'
        }
      });
    });
    
    test('formats full component state update message correctly', () => {
      const newState = {
        title: 'New Title',
        count: 5,
        items: ['Item 1', 'Item 2']
      };
      
      const message = formatter.formatFullComponentStateUpdate('123', 'component-id-456', newState);
      
      expect(message).toEqual({
        type: 'request',
        id: '123',
        action: 'update_full_component_state',
        data: {
          component_id: 'component-id-456',
          state: newState
        }
      });
    });
    
    test('formats component method call message correctly', () => {
      const params = ['param1', 42, true];
      const message = formatter.formatComponentMethodCall('123', 'component-id-456', 'increment', params);
      
      expect(message).toEqual({
        type: 'request',
        id: '123',
        action: 'call_component_method',
        data: {
          component_id: 'component-id-456',
          method: 'increment',
          params: params
        }
      });
    });
    
    test('formats event subscription message correctly', () => {
      const message = formatter.formatEventSubscription('123', ['component_update', 'livewire_event']);
      
      expect(message).toEqual({
        type: 'request',
        id: '123',
        action: 'subscribe',
        data: {
          events: ['component_update', 'livewire_event']
        }
      });
    });
    
    test('generates unique IDs for requests', () => {
      const message1 = formatter.formatComponentListRequest();
      const message2 = formatter.formatComponentListRequest();
      
      expect(message1.id).not.toEqual(message2.id);
    });
  });
  
  describe('message parsing', () => {
    test('parses component response correctly', () => {
      const rawResponse = {
        type: 'response',
        id: '123',
        status: 'success',
        data: {
          id: 'component-id-456',
          name: 'Counter',
          fingerprint: 'abc123',
          state: {
            count: 42,
            title: 'Test Counter'
          },
          computed: ['formattedCount'],
          methods: [
            { name: 'increment', parameters: [] },
            { name: 'decrement', parameters: [] },
            { name: 'setValue', parameters: ['value'] }
          ],
          listeners: ['count-changed', 'reset']
        }
      };
      
      const parsed = formatter.parseComponentResponse(rawResponse);
      
      expect(parsed).toEqual({
        id: 'component-id-456',
        name: 'Counter',
        fingerprint: 'abc123',
        state: {
          count: 42,
          title: 'Test Counter'
        },
        computed: ['formattedCount'],
        methods: [
          { name: 'increment', parameters: [] },
          { name: 'decrement', parameters: [] },
          { name: 'setValue', parameters: ['value'] }
        ],
        listeners: ['count-changed', 'reset']
      });
    });
    
    test('parses component list response correctly', () => {
      const rawResponse = {
        type: 'response',
        id: '456',
        status: 'success',
        data: {
          components: [
            {
              id: 'component-id-123',
              name: 'Counter',
              fingerprint: 'abc123',
              nested: false
            },
            {
              id: 'component-id-456',
              name: 'TodoList',
              fingerprint: 'def456',
              nested: true
            }
          ]
        }
      };
      
      const parsed = formatter.parseComponentListResponse(rawResponse);
      
      expect(parsed).toEqual([
        {
          id: 'component-id-123',
          name: 'Counter',
          fingerprint: 'abc123',
          nested: false
        },
        {
          id: 'component-id-456',
          name: 'TodoList',
          fingerprint: 'def456',
          nested: true
        }
      ]);
    });
    
    test('parses component update event correctly', () => {
      const rawEvent = {
        type: 'event',
        event: 'component_update',
        data: {
          id: 'component-id-123',
          name: 'Counter',
          fingerprint: 'abc123',
          state: {
            count: 43,
            title: 'Test Counter'
          },
          updates: [
            { path: 'count', from: 42, to: 43 }
          ]
        }
      };
      
      const parsed = formatter.parseComponentUpdateEvent(rawEvent);
      
      expect(parsed).toEqual({
        id: 'component-id-123',
        name: 'Counter',
        fingerprint: 'abc123',
        state: {
          count: 43,
          title: 'Test Counter'
        },
        updates: [
          { path: 'count', from: 42, to: 43 }
        ]
      });
    });
    
    test('parses Livewire event correctly', () => {
      const rawEvent = {
        type: 'event',
        event: 'livewire_event',
        data: {
          name: 'count-changed',
          params: [43],
          component_id: 'component-id-123'
        }
      };
      
      const parsed = formatter.parseLivewireEvent(rawEvent);
      
      expect(parsed).toEqual({
        name: 'count-changed',
        params: [43],
        componentId: 'component-id-123'
      });
    });
    
    test('parses error response correctly', () => {
      const rawResponse = {
        type: 'response',
        id: '789',
        status: 'error',
        error: {
          code: 'component_not_found',
          message: 'The requested component was not found'
        }
      };
      
      const parsed = formatter.parseErrorResponse(rawResponse);
      
      expect(parsed).toEqual({
        code: 'component_not_found',
        message: 'The requested component was not found'
      });
    });
    
    test('detects response type correctly', () => {
      const componentResponse = {
        type: 'response',
        id: '123',
        status: 'success',
        data: {
          id: 'component-id-456',
          name: 'Counter'
        }
      };
      
      const errorResponse = {
        type: 'response',
        id: '789',
        status: 'error',
        error: {
          code: 'component_not_found',
          message: 'The requested component was not found'
        }
      };
      
      const componentUpdateEvent = {
        type: 'event',
        event: 'component_update',
        data: {
          id: 'component-id-123'
        }
      };
      
      const livewireEvent = {
        type: 'event',
        event: 'livewire_event',
        data: {
          name: 'count-changed'
        }
      };
      
      expect(formatter.isSuccessResponse(componentResponse)).toBe(true);
      expect(formatter.isErrorResponse(errorResponse)).toBe(true);
      expect(formatter.isComponentUpdateEvent(componentUpdateEvent)).toBe(true);
      expect(formatter.isLivewireEvent(livewireEvent)).toBe(true);
      
      expect(formatter.isSuccessResponse(errorResponse)).toBe(false);
      expect(formatter.isErrorResponse(componentResponse)).toBe(false);
      expect(formatter.isComponentUpdateEvent(livewireEvent)).toBe(false);
      expect(formatter.isLivewireEvent(componentUpdateEvent)).toBe(false);
    });
  });
  
  describe('utilities', () => {
    test('generates valid message IDs', () => {
      const id1 = formatter.generateId();
      const id2 = formatter.generateId();
      
      // IDs should be strings
      expect(typeof id1).toBe('string');
      expect(typeof id2).toBe('string');
      
      // IDs should not be empty
      expect(id1.length).toBeGreaterThan(0);
      
      // IDs should be unique
      expect(id1).not.toEqual(id2);
    });
    
    test('handles null or undefined fields correctly', () => {
      const componentWithMissingFields = {
        type: 'response',
        id: '123',
        status: 'success',
        data: {
          id: 'component-id-456',
          name: 'Counter',
          // Missing fingerprint
          // Partial state
          state: {
            count: 42
          }
          // Missing computed, methods, listeners
        }
      };
      
      const parsed = formatter.parseComponentResponse(componentWithMissingFields);
      
      // Should fill in missing fields with defaults
      expect(parsed).toEqual({
        id: 'component-id-456',
        name: 'Counter',
        fingerprint: '', // Default for missing
        state: {
          count: 42
        },
        computed: [], // Default for missing
        methods: [], // Default for missing
        listeners: [] // Default for missing
      });
    });
    
    test('handles empty arrays correctly', () => {
      const componentWithEmptyArrays = {
        type: 'response',
        id: '123',
        status: 'success',
        data: {
          id: 'component-id-456',
          name: 'Counter',
          fingerprint: 'abc123',
          state: {},
          computed: [],
          methods: [],
          listeners: []
        }
      };
      
      const parsed = formatter.parseComponentResponse(componentWithEmptyArrays);
      
      // Empty arrays should remain empty
      expect(parsed.computed).toEqual([]);
      expect(parsed.methods).toEqual([]);
      expect(parsed.listeners).toEqual([]);
    });
    
    test('transforms snake_case to camelCase in events', () => {
      const snakeCaseEvent = {
        type: 'event',
        event: 'livewire_event',
        data: {
          name: 'user_logged_in',
          params: [123],
          component_id: 'component-id-123',
          happened_at: '2023-01-01T12:00:00Z'
        }
      };
      
      const parsed = formatter.parseLivewireEvent(snakeCaseEvent);
      
      // Keys should be camelCase in the output
      expect(parsed).toEqual({
        name: 'user_logged_in',
        params: [123],
        componentId: 'component-id-123', // Note: this was transformed
        happenedAt: '2023-01-01T12:00:00Z' // Note: this was transformed
      });
    });
  });
});
