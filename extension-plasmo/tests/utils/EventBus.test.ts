/**
 * EventBus tests
 * 
 * Tests for the event bus utility that manages event subscriptions,
 * publishes events, and provides aggregation/filtering capabilities
 * for Livewire events and extension internal events.
 */

import { EventBus } from '../../lib/utils/EventBus';
import * as EventUtils from '../../lib/utils/EventUtils';
import * as StateOptimizer from '../../lib/utils/StateOptimizer';

// Mock dependencies
jest.mock('../../lib/utils/EventUtils', () => ({
  addEventListener: jest.fn(),
  removeEventListener: jest.fn(),
  dispatchEvent: jest.fn()
}));

jest.mock('../../lib/utils/StateOptimizer', () => ({
  debounce: jest.fn((fn) => {
    // Simple mock implementation of debounce that just returns the function
    return fn;
  }),
  throttle: jest.fn((fn) => {
    // Simple mock implementation of throttle that just returns the function
    return fn;
  })
}));

describe('EventBus', () => {
  let eventBus: EventBus;
  
  beforeEach(() => {
    jest.clearAllMocks();
    eventBus = new EventBus();
  });
  
  describe('subscription management', () => {
    test('subscribe adds event listener with callback', () => {
      const callback = jest.fn();
      const eventType = 'component:updated';
      
      eventBus.subscribe(eventType, callback);
      
      // Should have registered the callback
      expect(eventBus.hasSubscribers(eventType)).toBe(true);
      
      // Verify number of subscribers
      expect(eventBus.getSubscriberCount(eventType)).toBe(1);
    });
    
    test('unsubscribe removes event listener', () => {
      const callback = jest.fn();
      const eventType = 'component:updated';
      
      // Subscribe first
      const subscriptionId = eventBus.subscribe(eventType, callback);
      
      // Then unsubscribe
      eventBus.unsubscribe(subscriptionId);
      
      // Should have no subscribers now
      expect(eventBus.hasSubscribers(eventType)).toBe(false);
      expect(eventBus.getSubscriberCount(eventType)).toBe(0);
    });
    
    test('subscribeOnce automatically unsubscribes after event fires', () => {
      const callback = jest.fn();
      const eventType = 'component:updated';
      
      eventBus.subscribeOnce(eventType, callback);
      
      // Should have one subscriber before event
      expect(eventBus.getSubscriberCount(eventType)).toBe(1);
      
      // Publish event
      eventBus.publish(eventType, { componentId: '123' });
      
      // Should have called the callback
      expect(callback).toHaveBeenCalledWith({ componentId: '123' });
      
      // Should have automatically unsubscribed
      expect(eventBus.hasSubscribers(eventType)).toBe(false);
    });
    
    test('subscribeManyWithRegex subscribes to multiple events with regex pattern', () => {
      const callback = jest.fn();
      const pattern = /^component:.*/;
      
      eventBus.subscribeManyWithRegex(pattern, callback);
      
      // Publish matching event
      eventBus.publish('component:updated', { id: '1' });
      
      // Publish matching event
      eventBus.publish('component:added', { id: '2' });
      
      // Publish non-matching event
      eventBus.publish('request:sent', { url: 'test' });
      
      // Should only be called for matching events
      expect(callback).toHaveBeenCalledTimes(2);
      expect(callback).toHaveBeenCalledWith({ id: '1' });
      expect(callback).toHaveBeenCalledWith({ id: '2' });
    });
    
    test('unsubscribeAll removes all subscriptions for specified event', () => {
      const callback1 = jest.fn();
      const callback2 = jest.fn();
      const eventType = 'component:updated';
      
      eventBus.subscribe(eventType, callback1);
      eventBus.subscribe(eventType, callback2);
      
      // Should have two subscribers
      expect(eventBus.getSubscriberCount(eventType)).toBe(2);
      
      // Unsubscribe all
      eventBus.unsubscribeAll(eventType);
      
      // Should have no subscribers now
      expect(eventBus.hasSubscribers(eventType)).toBe(false);
    });
    
    test('unsubscribeAllEvents removes all subscriptions for all events', () => {
      const callback = jest.fn();
      
      eventBus.subscribe('component:updated', callback);
      eventBus.subscribe('request:sent', callback);
      eventBus.subscribe('error:occurred', callback);
      
      // Unsubscribe from all events
      eventBus.unsubscribeAllEvents();
      
      // Should have no subscribers for any event
      expect(eventBus.hasSubscribers('component:updated')).toBe(false);
      expect(eventBus.hasSubscribers('request:sent')).toBe(false);
      expect(eventBus.hasSubscribers('error:occurred')).toBe(false);
    });
  });
  
  describe('event publishing', () => {
    test('publish calls all registered callbacks for event type', () => {
      const callback1 = jest.fn();
      const callback2 = jest.fn();
      const eventType = 'component:updated';
      const eventData = { componentId: '123', name: 'Counter' };
      
      eventBus.subscribe(eventType, callback1);
      eventBus.subscribe(eventType, callback2);
      
      eventBus.publish(eventType, eventData);
      
      // Both callbacks should be called with event data
      expect(callback1).toHaveBeenCalledWith(eventData);
      expect(callback2).toHaveBeenCalledWith(eventData);
    });
    
    test('publishAsync calls all registered callbacks asynchronously', async () => {
      const callback1 = jest.fn();
      const callback2 = jest.fn();
      const eventType = 'component:updated';
      const eventData = { componentId: '123', name: 'Counter' };
      
      eventBus.subscribe(eventType, callback1);
      eventBus.subscribe(eventType, callback2);
      
      await eventBus.publishAsync(eventType, eventData);
      
      // Both callbacks should be called with event data
      expect(callback1).toHaveBeenCalledWith(eventData);
      expect(callback2).toHaveBeenCalledWith(eventData);
    });
    
    test('publishWithFilter only calls callbacks that pass filter', () => {
      const callback1 = jest.fn();
      const callback2 = jest.fn();
      const eventType = 'component:updated';
      const eventData = { componentId: '123', name: 'Counter' };
      
      eventBus.subscribe(eventType, callback1);
      eventBus.subscribe(eventType, callback2);
      
      // Publish with filter that only allows callback1
      eventBus.publishWithFilter(
        eventType, 
        eventData, 
        (callback) => callback === callback1
      );
      
      // Only callback1 should be called
      expect(callback1).toHaveBeenCalledWith(eventData);
      expect(callback2).not.toHaveBeenCalled();
    });
    
    test('publishDebounced debounces event publication', () => {
      const callback = jest.fn();
      const eventType = 'component:updated';
      const eventData = { componentId: '123', name: 'Counter' };
      
      eventBus.subscribe(eventType, callback);
      
      // Publish with debounce
      eventBus.publishDebounced(eventType, eventData, 100);
      
      // StateOptimizer.debounce should have been called
      expect(StateOptimizer.debounce).toHaveBeenCalled();
      
      // In our mock implementation, debounce just returns the function
      // So the callback should have been called
      expect(callback).toHaveBeenCalledWith(eventData);
    });
    
    test('publishThrottled throttles event publication', () => {
      const callback = jest.fn();
      const eventType = 'component:updated';
      const eventData = { componentId: '123', name: 'Counter' };
      
      eventBus.subscribe(eventType, callback);
      
      // Publish with throttle
      eventBus.publishThrottled(eventType, eventData, 100);
      
      // StateOptimizer.throttle should have been called
      expect(StateOptimizer.throttle).toHaveBeenCalled();
      
      // In our mock implementation, throttle just returns the function
      // So the callback should have been called
      expect(callback).toHaveBeenCalledWith(eventData);
    });
  });
  
  describe('event history', () => {
    test('getEventHistory returns empty array when no events recorded', () => {
      // Need to enable history
      eventBus.enableHistoryTracking(100);
      
      const history = eventBus.getEventHistory();
      
      expect(history).toEqual([]);
    });
    
    test('getEventHistory returns recorded events when history tracking enabled', () => {
      // Enable history with limit of 100 events
      eventBus.enableHistoryTracking(100);
      
      // Publish some events
      const event1 = { type: 'component:updated', data: { id: '1' } };
      const event2 = { type: 'component:added', data: { id: '2' } };
      
      eventBus.publish(event1.type, event1.data);
      eventBus.publish(event2.type, event2.data);
      
      const history = eventBus.getEventHistory();
      
      // Should have recorded both events
      expect(history.length).toBe(2);
      
      // Check event types and data (ignore timestamp which will be added)
      expect(history[0].type).toBe(event1.type);
      expect(history[0].data).toEqual(event1.data);
      expect(history[1].type).toBe(event2.type);
      expect(history[1].data).toEqual(event2.data);
    });
    
    test('getEventHistory respects history size limit', () => {
      // Enable history with limit of 2 events
      eventBus.enableHistoryTracking(2);
      
      // Publish 3 events
      eventBus.publish('event1', { id: '1' });
      eventBus.publish('event2', { id: '2' });
      eventBus.publish('event3', { id: '3' });
      
      const history = eventBus.getEventHistory();
      
      // Should only have kept the most recent 2 events
      expect(history.length).toBe(2);
      expect(history[0].type).toBe('event2');
      expect(history[1].type).toBe('event3');
    });
    
    test('clearEventHistory removes all recorded events', () => {
      // Enable history
      eventBus.enableHistoryTracking(100);
      
      // Publish some events
      eventBus.publish('event1', { id: '1' });
      eventBus.publish('event2', { id: '2' });
      
      // Clear history
      eventBus.clearEventHistory();
      
      const history = eventBus.getEventHistory();
      
      // Should be empty
      expect(history).toEqual([]);
    });
    
    test('disableHistoryTracking stops recording new events', () => {
      // Enable history
      eventBus.enableHistoryTracking(100);
      
      // Publish an event
      eventBus.publish('event1', { id: '1' });
      
      // Disable history tracking
      eventBus.disableHistoryTracking();
      
      // Publish another event
      eventBus.publish('event2', { id: '2' });
      
      const history = eventBus.getEventHistory();
      
      // Should only have the first event
      expect(history.length).toBe(1);
      expect(history[0].type).toBe('event1');
    });
  });
  
  describe('event filtering and aggregation', () => {
    test('getFilteredEvents returns events matching filter criteria', () => {
      // Enable history
      eventBus.enableHistoryTracking(100);
      
      // Publish some events
      eventBus.publish('component:updated', { id: '1', name: 'Counter' });
      eventBus.publish('component:updated', { id: '2', name: 'Todo' });
      eventBus.publish('request:sent', { url: '/api/data' });
      
      // Filter by type
      const componentEvents = eventBus.getFilteredEvents(
        (event) => event.type === 'component:updated'
      );
      
      // Should have 2 component events
      expect(componentEvents.length).toBe(2);
      
      // Filter by data property
      const counterEvents = eventBus.getFilteredEvents(
        (event) => event.data.name === 'Counter'
      );
      
      // Should have 1 counter event
      expect(counterEvents.length).toBe(1);
      expect(counterEvents[0].data.name).toBe('Counter');
    });
    
    test('getEventCountByType returns event counts grouped by type', () => {
      // Enable history
      eventBus.enableHistoryTracking(100);
      
      // Publish some events
      eventBus.publish('component:updated', { id: '1' });
      eventBus.publish('component:updated', { id: '2' });
      eventBus.publish('component:added', { id: '3' });
      eventBus.publish('request:sent', { url: '/api/data' });
      eventBus.publish('request:received', { url: '/api/data' });
      
      const counts = eventBus.getEventCountByType();
      
      // Should count events by type
      expect(counts['component:updated']).toBe(2);
      expect(counts['component:added']).toBe(1);
      expect(counts['request:sent']).toBe(1);
      expect(counts['request:received']).toBe(1);
    });
    
    test('getEventsByTimeRange returns events within specified time range', () => {
      // Enable history with custom timestamp creator for testing
      eventBus.enableHistoryTracking(100);
      
      // Mock the current time
      const now = Date.now();
      Date.now = jest.fn(() => now);
      
      // Publish first event
      eventBus.publish('event1', { id: '1' });
      
      // Advance time by 100ms
      Date.now = jest.fn(() => now + 100);
      
      // Publish second event
      eventBus.publish('event2', { id: '2' });
      
      // Advance time by another 100ms
      Date.now = jest.fn(() => now + 200);
      
      // Publish third event
      eventBus.publish('event3', { id: '3' });
      
      // Get events in range (now+50 to now+150)
      const eventsInRange = eventBus.getEventsByTimeRange(now + 50, now + 150);
      
      // Should only include the second event
      expect(eventsInRange.length).toBe(1);
      expect(eventsInRange[0].type).toBe('event2');
    });
  });
  
  describe('error handling', () => {
    test('subscribers with errors do not prevent other subscribers from receiving events', () => {
      const errorCallback = jest.fn().mockImplementation(() => {
        throw new Error('Test error');
      });
      const successCallback = jest.fn();
      
      // Add console.error spy to capture error logging
      const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation();
      
      eventBus.subscribe('test-event', errorCallback);
      eventBus.subscribe('test-event', successCallback);
      
      // Should not throw when published
      expect(() => {
        eventBus.publish('test-event', { data: 'test' });
      }).not.toThrow();
      
      // Error should be logged
      expect(consoleErrorSpy).toHaveBeenCalled();
      
      // Error callback should have been called and thrown
      expect(errorCallback).toHaveBeenCalled();
      
      // Success callback should still be called
      expect(successCallback).toHaveBeenCalled();
      
      // Cleanup
      consoleErrorSpy.mockRestore();
    });
  });
  
  describe('performance optimizations', () => {
    test('isSubscriptionActive returns false for unsubscribed subscriptions', () => {
      const callback = jest.fn();
      const subscriptionId = eventBus.subscribe('test-event', callback);
      
      // Should be active initially
      expect(eventBus.isSubscriptionActive(subscriptionId)).toBe(true);
      
      // Unsubscribe
      eventBus.unsubscribe(subscriptionId);
      
      // Should no longer be active
      expect(eventBus.isSubscriptionActive(subscriptionId)).toBe(false);
    });
    
    test('getActiveSubscriptionIds returns list of active subscription IDs', () => {
      const callback = jest.fn();
      
      // Add subscriptions
      const id1 = eventBus.subscribe('event1', callback);
      const id2 = eventBus.subscribe('event2', callback);
      const id3 = eventBus.subscribe('event3', callback);
      
      // Unsubscribe one
      eventBus.unsubscribe(id2);
      
      // Get active IDs
      const activeIds = eventBus.getActiveSubscriptionIds();
      
      // Should include id1 and id3 but not id2
      expect(activeIds).toContain(id1);
      expect(activeIds).not.toContain(id2);
      expect(activeIds).toContain(id3);
    });
  });
});
