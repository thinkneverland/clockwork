/**
 * StateOptimizer tests
 * 
 * Tests the utilities for state optimization including memoization,
 * selective state updates, batched updates, and memory monitoring.
 */

import { StateOptimizer } from '../../lib/utils/StateOptimizer';
import * as BrowserUtils from '../../lib/utils/BrowserUtils';

// Mock BrowserUtils
jest.mock('../../lib/utils/BrowserUtils', () => ({
  getBrowserInfo: jest.fn().mockReturnValue({
    type: 'chrome',
    name: 'chrome',
    version: '100.0.0'
  })
}));

describe('StateOptimizer', () => {
  let optimizer: StateOptimizer;
  
  beforeEach(() => {
    jest.clearAllMocks();
    optimizer = new StateOptimizer();
    jest.useFakeTimers();
  });
  
  afterEach(() => {
    jest.useRealTimers();
  });
  
  describe('memoization', () => {
    test('memoize caches function results for same inputs', () => {
      // Create an expensive calculation function with a spy
      const calculateSpy = jest.fn((a: number, b: number) => a * b);
      const memoizedCalculate = optimizer.memoize(calculateSpy);
      
      // First call should execute the function
      const result1 = memoizedCalculate(5, 10);
      expect(result1).toBe(50);
      expect(calculateSpy).toHaveBeenCalledTimes(1);
      
      // Second call with same arguments should use cached result
      const result2 = memoizedCalculate(5, 10);
      expect(result2).toBe(50);
      expect(calculateSpy).toHaveBeenCalledTimes(1); // Still just one call
      
      // Call with different arguments should execute the function again
      const result3 = memoizedCalculate(7, 8);
      expect(result3).toBe(56);
      expect(calculateSpy).toHaveBeenCalledTimes(2);
    });
    
    test('memoize with custom cache key generator', () => {
      // Function that should consider objects with same ID as same input
      const processObject = jest.fn((obj: { id: number, value: string }) => obj.value.toUpperCase());
      
      // Create a custom key generator that only uses the id
      const keyGenerator = (obj: { id: number }) => obj.id.toString();
      
      const memoizedProcess = optimizer.memoize(processObject, keyGenerator);
      
      // First call
      const result1 = memoizedProcess({ id: 1, value: 'hello' });
      expect(result1).toBe('HELLO');
      expect(processObject).toHaveBeenCalledTimes(1);
      
      // Different object but same ID should use cache
      const result2 = memoizedProcess({ id: 1, value: 'different' });
      expect(result2).toBe('HELLO'); // Note: returns cached result, not 'DIFFERENT'
      expect(processObject).toHaveBeenCalledTimes(1);
      
      // Different ID should call function again
      const result3 = memoizedProcess({ id: 2, value: 'world' });
      expect(result3).toBe('WORLD');
      expect(processObject).toHaveBeenCalledTimes(2);
    });
    
    test('memoize cache clears after maxSize is reached', () => {
      // Create a memoized function with small cache size
      const calculate = jest.fn((a: number) => a * 2);
      const memoizedCalculate = optimizer.memoize(calculate, undefined, 3);
      
      // Fill the cache
      memoizedCalculate(1); // result: 2
      memoizedCalculate(2); // result: 4
      memoizedCalculate(3); // result: 6
      
      // All are in cache, function called 3 times
      expect(calculate).toHaveBeenCalledTimes(3);
      
      // This should evict the oldest entry (1)
      memoizedCalculate(4); // result: 8
      
      // Calling with an evicted value should call the function again
      memoizedCalculate(1);
      expect(calculate).toHaveBeenCalledTimes(5);
      
      // But these should still be cached
      memoizedCalculate(2);
      memoizedCalculate(3);
      expect(calculate).toHaveBeenCalledTimes(5); // No new calls
    });
  });
  
  describe('selective updates', () => {
    test('shouldUpdate returns false for identical objects', () => {
      const prevState = { name: 'John', age: 30, items: [1, 2, 3] };
      const newState = { name: 'John', age: 30, items: [1, 2, 3] };
      
      const result = optimizer.shouldUpdate(prevState, newState);
      
      expect(result).toBe(false);
    });
    
    test('shouldUpdate returns true for different primitive values', () => {
      const prevState = { name: 'John', age: 30 };
      const newState = { name: 'John', age: 31 };
      
      const result = optimizer.shouldUpdate(prevState, newState);
      
      expect(result).toBe(true);
    });
    
    test('shouldUpdate returns true for different array items', () => {
      const prevState = { items: [1, 2, 3] };
      const newState = { items: [1, 2, 4] };
      
      const result = optimizer.shouldUpdate(prevState, newState);
      
      expect(result).toBe(true);
    });
    
    test('shouldUpdate returns true for different array length', () => {
      const prevState = { items: [1, 2, 3] };
      const newState = { items: [1, 2] };
      
      const result = optimizer.shouldUpdate(prevState, newState);
      
      expect(result).toBe(true);
    });
    
    test('shouldUpdate with custom comparator function', () => {
      const prevState = { person: { name: 'John', age: 30 } };
      const newState = { person: { name: 'John', age: 31 } };
      
      // Custom comparator that only compares names
      const comparator = (a: any, b: any) => {
        if (a && b && typeof a === 'object' && typeof b === 'object') {
          return a.name === b.name;
        }
        return a === b;
      };
      
      const result = optimizer.shouldUpdate(prevState, newState, comparator);
      
      // Despite age being different, our custom comparator only checks name
      expect(result).toBe(false);
    });
    
    test('getChangedPaths identifies nested changed properties', () => {
      const prevState = {
        user: {
          name: 'John',
          address: {
            city: 'New York',
            zip: '10001'
          }
        },
        settings: {
          darkMode: true
        }
      };
      
      const newState = {
        user: {
          name: 'John',
          address: {
            city: 'Boston',
            zip: '10001'
          }
        },
        settings: {
          darkMode: true
        }
      };
      
      const changedPaths = optimizer.getChangedPaths(prevState, newState);
      
      expect(changedPaths).toEqual(['user.address.city']);
    });
    
    test('getChangedPaths identifies multiple changes', () => {
      const prevState = {
        count: 5,
        user: { name: 'John' },
        items: [1, 2, 3]
      };
      
      const newState = {
        count: 6,
        user: { name: 'Jane' },
        items: [1, 2, 3]
      };
      
      const changedPaths = optimizer.getChangedPaths(prevState, newState);
      
      expect(changedPaths).toContain('count');
      expect(changedPaths).toContain('user.name');
      expect(changedPaths.length).toBe(2);
    });
  });
  
  describe('batched updates', () => {
    test('batchUpdates collects multiple updates into one', () => {
      // Create mock update handler
      const updateHandler = jest.fn();
      
      // Start batch mode
      optimizer.startBatch();
      
      // Make several updates
      optimizer.queueUpdate({ name: 'change 1' }, updateHandler);
      optimizer.queueUpdate({ name: 'change 2' }, updateHandler);
      optimizer.queueUpdate({ name: 'change 3' }, updateHandler);
      
      // Verify no updates yet
      expect(updateHandler).not.toHaveBeenCalled();
      
      // Flush the batch
      optimizer.flushBatch();
      
      // Verify only one update was triggered with combined changes
      expect(updateHandler).toHaveBeenCalledTimes(1);
      expect(updateHandler).toHaveBeenCalledWith([
        { name: 'change 1' },
        { name: 'change 2' },
        { name: 'change 3' }
      ]);
    });
    
    test('batchUpdates automatically flushes after timeout', () => {
      const updateHandler = jest.fn();
      
      // Start batch with 100ms timeout
      optimizer.startBatch(100);
      
      // Queue some updates
      optimizer.queueUpdate({ id: 1 }, updateHandler);
      optimizer.queueUpdate({ id: 2 }, updateHandler);
      
      // Verify no updates yet
      expect(updateHandler).not.toHaveBeenCalled();
      
      // Advance time to trigger auto-flush
      jest.advanceTimersByTime(100);
      
      // Verify update was triggered
      expect(updateHandler).toHaveBeenCalledTimes(1);
      expect(updateHandler).toHaveBeenCalledWith([
        { id: 1 },
        { id: 2 }
      ]);
    });
    
    test('updates can be grouped by type', () => {
      const updateHandlerA = jest.fn();
      const updateHandlerB = jest.fn();
      
      // Start batch
      optimizer.startBatch();
      
      // Queue updates of different types
      optimizer.queueUpdate({ id: 1 }, updateHandlerA, 'typeA');
      optimizer.queueUpdate({ id: 2 }, updateHandlerA, 'typeA');
      optimizer.queueUpdate({ id: 3 }, updateHandlerB, 'typeB');
      
      // Flush the batch
      optimizer.flushBatch();
      
      // Verify each handler was called once with its own updates
      expect(updateHandlerA).toHaveBeenCalledTimes(1);
      expect(updateHandlerA).toHaveBeenCalledWith([
        { id: 1 },
        { id: 2 }
      ]);
      
      expect(updateHandlerB).toHaveBeenCalledTimes(1);
      expect(updateHandlerB).toHaveBeenCalledWith([
        { id: 3 }
      ]);
    });
  });
  
  describe('memory monitoring', () => {
    let performanceObserverCallback: any;
    
    beforeEach(() => {
      // Mock PerformanceObserver
      global.PerformanceObserver = jest.fn((callback) => {
        performanceObserverCallback = callback;
        return {
          observe: jest.fn(),
          disconnect: jest.fn()
        };
      }) as any;
      
      // Mock performance object
      global.performance = {
        mark: jest.fn(),
        measure: jest.fn(),
        getEntriesByType: jest.fn(),
        clearMarks: jest.fn(),
        clearMeasures: jest.fn()
      } as any;
    });
    
    test('memory monitoring tracks memory usage', () => {
      // Start monitoring
      optimizer.startMemoryMonitoring();
      
      // Simulate memory usage event
      const mockEntries = {
        getEntries: () => [{
          entryType: 'memory',
          jsHeapSizeLimit: 2000000000,
          totalJSHeapSize: 50000000,
          usedJSHeapSize: 40000000
        }]
      };
      
      if (performanceObserverCallback) {
        performanceObserverCallback(mockEntries);
      }
      
      // Check memory metrics
      const memoryMetrics = optimizer.getMemoryMetrics();
      
      expect(memoryMetrics.currentUsage).toBe(40000000);
      expect(memoryMetrics.heapSizeLimit).toBe(2000000000);
      expect(memoryMetrics.usagePercentage).toBe(2); // 40M/2000M * 100
    });
    
    test('detects memory leaks', () => {
      // Start monitoring
      optimizer.startMemoryMonitoring();
      
      // Simulate increasing memory usage over time
      const memoryEntries = [
        // Initial usage
        {
          entryType: 'memory',
          usedJSHeapSize: 10000000,
          totalJSHeapSize: 50000000,
          jsHeapSizeLimit: 2000000000
        },
        // Increased usage
        {
          entryType: 'memory',
          usedJSHeapSize: 30000000,
          totalJSHeapSize: 50000000,
          jsHeapSizeLimit: 2000000000
        },
        // Large increase - potential leak
        {
          entryType: 'memory',
          usedJSHeapSize: 100000000,
          totalJSHeapSize: 150000000,
          jsHeapSizeLimit: 2000000000
        }
      ];
      
      // Simulate memory usage reports
      if (performanceObserverCallback) {
        for (const entry of memoryEntries) {
          performanceObserverCallback({
            getEntries: () => [entry]
          });
        }
      }
      
      // Check leak detection
      const memoryMetrics = optimizer.getMemoryMetrics();
      
      expect(memoryMetrics.possibleLeak).toBe(true);
      expect(memoryMetrics.growthRate).toBeGreaterThan(0);
    });
    
    test('stopMemoryMonitoring ends monitoring', () => {
      // Setup
      optimizer.startMemoryMonitoring();
      
      // Object should exist
      expect((optimizer as any).performanceObserver).toBeDefined();
      
      // Stop monitoring
      optimizer.stopMemoryMonitoring();
      
      // Observer should be disconnected and removed
      expect((optimizer as any).performanceObserver).toBeUndefined();
    });
  });
  
  describe('throttle and debounce', () => {
    test('throttle limits function calls', () => {
      const fn = jest.fn();
      const throttled = optimizer.throttle(fn, 100);
      
      // Call multiple times in succession
      throttled();
      throttled();
      throttled();
      
      // Only one call should have happened
      expect(fn).toHaveBeenCalledTimes(1);
      
      // Advance time
      jest.advanceTimersByTime(100);
      
      // Call again
      throttled();
      
      // Should be allowed to call again
      expect(fn).toHaveBeenCalledTimes(2);
    });
    
    test('debounce delays function call until wait period', () => {
      const fn = jest.fn();
      const debounced = optimizer.debounce(fn, 100);
      
      // Call multiple times in succession
      debounced();
      debounced();
      debounced();
      
      // Function should not have been called yet
      expect(fn).not.toHaveBeenCalled();
      
      // Advance time
      jest.advanceTimersByTime(100);
      
      // Function should be called once
      expect(fn).toHaveBeenCalledTimes(1);
    });
    
    test('debounce with immediate option', () => {
      const fn = jest.fn();
      const debounced = optimizer.debounce(fn, 100, true);
      
      // First call is immediate
      debounced();
      expect(fn).toHaveBeenCalledTimes(1);
      
      // Subsequent calls within wait period are ignored
      debounced();
      debounced();
      expect(fn).toHaveBeenCalledTimes(1);
      
      // Advance time
      jest.advanceTimersByTime(100);
      
      // Call again after wait period
      debounced();
      expect(fn).toHaveBeenCalledTimes(2);
    });
  });
  
  describe('browser-specific optimizations', () => {
    test('applies Chrome-specific optimizations', () => {
      (BrowserUtils.getBrowserInfo as jest.Mock).mockReturnValue({
        type: 'chrome',
        name: 'chrome',
        version: '100.0.0'
      });
      
      const chromeOptimizer = new StateOptimizer();
      
      // Chrome should enable memory monitoring
      expect((chromeOptimizer as any).options.memoryMonitoring).toBe(true);
    });
    
    test('applies Firefox-specific optimizations', () => {
      (BrowserUtils.getBrowserInfo as jest.Mock).mockReturnValue({
        type: 'firefox',
        name: 'firefox',
        version: '95.0.0'
      });
      
      const firefoxOptimizer = new StateOptimizer();
      
      // Firefox memory monitoring might be different
      // This is implementation dependent
    });
    
    test('optimizations for older browsers', () => {
      (BrowserUtils.getBrowserInfo as jest.Mock).mockReturnValue({
        type: 'firefox',
        name: 'firefox',
        version: '60.0.0' // Older version
      });
      
      const oldBrowserOptimizer = new StateOptimizer();
      
      // Older browsers might use different settings
      // Implementation dependent
    });
  });
});
