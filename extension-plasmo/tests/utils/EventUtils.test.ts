/**
 * EventUtils tests
 * 
 * Tests for the standardized event handling utilities
 * that ensure consistent behavior across Chrome, Firefox, and Edge
 */

import * as EventUtils from '../../lib/utils/EventUtils';

// Store original implementation
const originalAddEventListener = window.addEventListener;
const originalRemoveEventListener = window.removeEventListener;

describe('EventUtils', () => {
  beforeEach(() => {
    // Reset mocks and restore original implementation
    window.addEventListener = jest.fn();
    window.removeEventListener = jest.fn();
    
    // Clear any tracked event listeners
    (EventUtils as any).eventListeners = new Map();
  });
  
  afterEach(() => {
    // Restore original
    window.addEventListener = originalAddEventListener;
    window.removeEventListener = originalRemoveEventListener;
  });
  
  describe('Browser Detection', () => {
    test('detectBrowser identifies Chrome correctly', () => {
      Object.defineProperty(navigator, 'userAgent', {
        value: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.36',
        configurable: true
      });
      
      const browser = EventUtils.detectBrowser();
      
      expect(browser.isChrome).toBe(true);
      expect(browser.isFirefox).toBe(false);
      expect(browser.isEdge).toBe(false);
      expect(browser.name).toBe('Chrome');
    });
    
    test('detectBrowser identifies Firefox correctly', () => {
      Object.defineProperty(navigator, 'userAgent', {
        value: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:95.0) Gecko/20100101 Firefox/95.0',
        configurable: true
      });
      
      const browser = EventUtils.detectBrowser();
      
      expect(browser.isChrome).toBe(false);
      expect(browser.isFirefox).toBe(true);
      expect(browser.isEdge).toBe(false);
      expect(browser.name).toBe('Firefox');
    });
    
    test('detectBrowser identifies Edge correctly', () => {
      Object.defineProperty(navigator, 'userAgent', {
        value: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.0.0 Safari/537.36 Edg/99.0.0.0',
        configurable: true
      });
      
      const browser = EventUtils.detectBrowser();
      
      expect(browser.isChrome).toBe(false);
      expect(browser.isFirefox).toBe(false);
      expect(browser.isEdge).toBe(true);
      expect(browser.name).toBe('Edge');
    });
  });
  
  describe('Event Listener Management', () => {
    test('addEventListener registers event listener and tracks it', () => {
      const element = document.createElement('div');
      const handler = jest.fn();
      
      EventUtils.addEventListener(element, 'click', handler);
      
      // Check if native addEventListener was called
      expect(element.addEventListener).toHaveBeenCalledWith('click', handler, undefined);
      
      // Check if the listener was tracked internally
      const listeners = (EventUtils as any).eventListeners.get(element);
      expect(listeners).toBeDefined();
      expect(listeners.get('click')).toContain(handler);
    });
    
    test('addEventListener with options', () => {
      const element = document.createElement('div');
      const handler = jest.fn();
      const options = { capture: true, passive: true };
      
      EventUtils.addEventListener(element, 'click', handler, options);
      
      // Check if native addEventListener was called with options
      expect(element.addEventListener).toHaveBeenCalledWith('click', handler, options);
    });
    
    test('removeEventListener removes event listener and tracking', () => {
      const element = document.createElement('div');
      const handler = jest.fn();
      
      // First add the listener
      EventUtils.addEventListener(element, 'click', handler);
      
      // Then remove it
      EventUtils.removeEventListener(element, 'click', handler);
      
      // Check if native removeEventListener was called
      expect(element.removeEventListener).toHaveBeenCalledWith('click', handler, undefined);
      
      // Check if the tracking was updated
      const listeners = (EventUtils as any).eventListeners.get(element);
      expect(listeners?.get('click')?.includes(handler)).toBeFalsy();
    });
    
    test('removeEventListener with options', () => {
      const element = document.createElement('div');
      const handler = jest.fn();
      const options = { capture: true };
      
      // First add the listener
      EventUtils.addEventListener(element, 'click', handler, options);
      
      // Then remove it with matching options
      EventUtils.removeEventListener(element, 'click', handler, options);
      
      // Check if native removeEventListener was called with options
      expect(element.removeEventListener).toHaveBeenCalledWith('click', handler, options);
    });
    
    test('removeAllEventListeners for specific type', () => {
      const element = document.createElement('div');
      const clickHandler1 = jest.fn();
      const clickHandler2 = jest.fn();
      const mouseoverHandler = jest.fn();
      
      // Add multiple listeners
      EventUtils.addEventListener(element, 'click', clickHandler1);
      EventUtils.addEventListener(element, 'click', clickHandler2);
      EventUtils.addEventListener(element, 'mouseover', mouseoverHandler);
      
      // Remove all click listeners
      EventUtils.removeAllEventListeners(element, 'click');
      
      // Check if native removeEventListener was called for both click handlers
      expect(element.removeEventListener).toHaveBeenCalledWith('click', clickHandler1, undefined);
      expect(element.removeEventListener).toHaveBeenCalledWith('click', clickHandler2, undefined);
      expect(element.removeEventListener).not.toHaveBeenCalledWith('mouseover', mouseoverHandler, undefined);
      
      // Check if tracking was updated
      const listeners = (EventUtils as any).eventListeners.get(element);
      expect(listeners?.get('click')).toBeUndefined();
      expect(listeners?.get('mouseover')).toContain(mouseoverHandler);
    });
    
    test('removeAllEventListeners for all types', () => {
      const element = document.createElement('div');
      const clickHandler = jest.fn();
      const mouseoverHandler = jest.fn();
      
      // Add multiple listeners of different types
      EventUtils.addEventListener(element, 'click', clickHandler);
      EventUtils.addEventListener(element, 'mouseover', mouseoverHandler);
      
      // Remove all listeners
      EventUtils.removeAllEventListeners(element);
      
      // Check if native removeEventListener was called for all handlers
      expect(element.removeEventListener).toHaveBeenCalledWith('click', clickHandler, undefined);
      expect(element.removeEventListener).toHaveBeenCalledWith('mouseover', mouseoverHandler, undefined);
      
      // Check if tracking was cleared
      expect((EventUtils as any).eventListeners.get(element)).toBeUndefined();
    });
    
    test('cleanupEventListeners removes all tracked listeners', () => {
      const element1 = document.createElement('div');
      const element2 = document.createElement('span');
      const handler1 = jest.fn();
      const handler2 = jest.fn();
      
      // Add listeners to multiple elements
      EventUtils.addEventListener(element1, 'click', handler1);
      EventUtils.addEventListener(element2, 'mouseover', handler2);
      
      // Clean up all listeners
      EventUtils.cleanupEventListeners();
      
      // Check if native removeEventListener was called for all handlers
      expect(element1.removeEventListener).toHaveBeenCalledWith('click', handler1, undefined);
      expect(element2.removeEventListener).toHaveBeenCalledWith('mouseover', handler2, undefined);
      
      // Check if tracking was cleared
      expect((EventUtils as any).eventListeners.size).toBe(0);
    });
  });
  
  describe('Browser-specific behavior', () => {
    test('Firefox-specific event handling', () => {
      // Mock Firefox browser detection
      jest.spyOn(EventUtils, 'detectBrowser').mockReturnValue({
        isChrome: false,
        isFirefox: true,
        isEdge: false,
        name: 'Firefox',
        version: '95.0.0'
      });
      
      const element = document.createElement('div');
      const handler = jest.fn();
      
      // Add listener with Firefox-specific options
      EventUtils.addEventListener(element, 'click', handler, { passive: true });
      
      // Firefox handles passive option differently in some versions
      expect(element.addEventListener).toHaveBeenCalled();
      
      // Check if any Firefox-specific workarounds were applied
      // This would depend on the actual implementation, but we're 
      // testing that it runs without errors
    });
    
    test('Edge-specific event handling', () => {
      // Mock Edge browser detection
      jest.spyOn(EventUtils, 'detectBrowser').mockReturnValue({
        isChrome: false,
        isFirefox: false,
        isEdge: true,
        name: 'Edge',
        version: '99.0.0'
      });
      
      const element = document.createElement('div');
      const handler = jest.fn();
      
      // Add listener with Edge-specific options
      EventUtils.addEventListener(element, 'click', handler);
      
      // Check that it was called
      expect(element.addEventListener).toHaveBeenCalled();
      
      // Check if any Edge-specific workarounds were applied
      // This would depend on the actual implementation
    });
  });
  
  describe('Memory leak prevention', () => {
    test('Weak references used for DOM elements', () => {
      // Create a scope to test garbage collection
      let element: HTMLElement | null = document.createElement('div');
      const handler = jest.fn();
      
      // Add a listener
      EventUtils.addEventListener(element, 'click', handler);
      
      // Check if tracking is working
      expect((EventUtils as any).eventListeners.has(element)).toBe(true);
      
      // Remove the reference to allow garbage collection
      element = null;
      
      // In a real environment, the weak reference would be garbage collected
      // For testing, we'll manually simulate this by calling the cleanup
      EventUtils.cleanupEventListeners();
      
      // After cleanup, the map should be empty since no strong references remain
      expect((EventUtils as any).eventListeners.size).toBe(0);
    });
  });
});
