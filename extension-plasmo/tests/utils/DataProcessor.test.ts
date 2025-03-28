/**
 * DataProcessor tests
 * 
 * Tests for the data processing utility that handles efficient data processing,
 * caching, worker thread utilization, and batch processing.
 */

import { DataProcessor } from '../../lib/utils/DataProcessor';
import * as BrowserUtils from '../../lib/utils/BrowserUtils';

// Mock BrowserUtils
jest.mock('../../lib/utils/BrowserUtils', () => ({
  getBrowserInfo: jest.fn(),
  supportsWorkerThreads: jest.fn(),
  getBrowserAPI: jest.fn()
}));

describe('DataProcessor', () => {
  // Reset all mocks between tests
  beforeEach(() => {
    jest.clearAllMocks();
    
    // Default browser support for worker threads
    (BrowserUtils.supportsWorkerThreads as jest.Mock).mockReturnValue(true);
    (BrowserUtils.getBrowserInfo as jest.Mock).mockReturnValue({
      type: 'chrome',
      name: 'chrome',
      version: '100.0.0'
    });
  });
  
  describe('initialization', () => {
    test('creates processor with default options', () => {
      const processor = new DataProcessor();
      
      expect(processor).toBeDefined();
      expect((processor as any).options.useCache).toBe(true);
      expect((processor as any).options.useWorkers).toBe(true);
    });
    
    test('respects provided options', () => {
      const processor = new DataProcessor({
        useCache: false,
        useWorkers: false,
        maxCacheSize: 100,
        batchSize: 50
      });
      
      expect((processor as any).options.useCache).toBe(false);
      expect((processor as any).options.useWorkers).toBe(false);
      expect((processor as any).options.maxCacheSize).toBe(100);
      expect((processor as any).options.batchSize).toBe(50);
    });
    
    test('checks for worker thread support', () => {
      const processor = new DataProcessor();
      
      expect(BrowserUtils.supportsWorkerThreads).toHaveBeenCalled();
    });
    
    test('disables workers when not supported', () => {
      (BrowserUtils.supportsWorkerThreads as jest.Mock).mockReturnValue(false);
      
      const processor = new DataProcessor();
      
      expect((processor as any).options.useWorkers).toBe(false);
    });
  });
  
  describe('process', () => {
    test('processes data synchronously when workers disabled', async () => {
      const processor = new DataProcessor({ useWorkers: false });
      
      const data = { key: 'value', nested: { array: [1, 2, 3] } };
      const processFunction = jest.fn(input => ({ processed: input }));
      
      const result = await processor.process(data, processFunction);
      
      expect(processFunction).toHaveBeenCalledWith(data);
      expect(result).toEqual({ processed: data });
    });
    
    test('caches results for identical inputs', async () => {
      const processor = new DataProcessor({ useCache: true });
      
      const data = { id: 1, value: 'test' };
      const processFunction = jest.fn(input => ({ processed: input }));
      
      // First call should process the data
      await processor.process(data, processFunction);
      
      // Second call with same data should use cache
      const result = await processor.process(data, processFunction);
      
      expect(processFunction).toHaveBeenCalledTimes(1);
      expect(result).toEqual({ processed: data });
    });
    
    test('bypasses cache when disabled', async () => {
      const processor = new DataProcessor({ useCache: false });
      
      const data = { id: 1, value: 'test' };
      const processFunction = jest.fn(input => ({ processed: input }));
      
      // First call
      await processor.process(data, processFunction);
      
      // Second call should process again
      await processor.process(data, processFunction);
      
      expect(processFunction).toHaveBeenCalledTimes(2);
    });
    
    test('limits cache size to max entries', async () => {
      const processor = new DataProcessor({ 
        useCache: true,
        maxCacheSize: 2 
      });
      
      // Access private cache for testing
      const cache = (processor as any).cache;
      
      // First item
      await processor.process({ id: 1 }, data => data);
      
      // Second item
      await processor.process({ id: 2 }, data => data);
      
      // Third item should evict first item
      await processor.process({ id: 3 }, data => data);
      
      // Get the cache keys to check which items are cached
      const keys = Array.from(cache.keys()).map(key => JSON.parse(key).id);
      
      expect(keys).toContain(2);
      expect(keys).toContain(3);
      expect(keys).not.toContain(1);
      expect(cache.size).toBe(2);
    });
  });
  
  describe('batch processing', () => {
    test('processes items in batches', async () => {
      const processor = new DataProcessor({ 
        batchSize: 2,
        useWorkers: false 
      });
      
      const items = [
        { id: 1, value: 'a' },
        { id: 2, value: 'b' },
        { id: 3, value: 'c' },
        { id: 4, value: 'd' },
        { id: 5, value: 'e' }
      ];
      
      // Mock that counts the number of items being processed at once
      const batchSizesProcessed: number[] = [];
      const processFunction = jest.fn((batch) => {
        batchSizesProcessed.push(batch.length);
        return batch.map(item => ({ processed: item }));
      });
      
      const results = await processor.processBatch(items, processFunction);
      
      // Should have processed in 3 batches (2, 2, 1)
      expect(batchSizesProcessed).toEqual([2, 2, 1]);
      
      // Results should be in the original order
      expect(results).toEqual([
        { processed: { id: 1, value: 'a' } },
        { processed: { id: 2, value: 'b' } },
        { processed: { id: 3, value: 'c' } },
        { processed: { id: 4, value: 'd' } },
        { processed: { id: 5, value: 'e' } }
      ]);
    });
    
    test('handles empty array', async () => {
      const processor = new DataProcessor();
      const processFunction = jest.fn();
      
      const results = await processor.processBatch([], processFunction);
      
      expect(results).toEqual([]);
      expect(processFunction).not.toHaveBeenCalled();
    });
  });
  
  describe('worker thread processing', () => {
    let mockWorker: any;
    
    beforeEach(() => {
      // Mock Worker implementation
      mockWorker = {
        postMessage: jest.fn(),
        terminate: jest.fn(),
        addEventListener: jest.fn((event, handler) => {
          // Store the message handler to simulate responses
          if (event === 'message') {
            mockWorker.messageHandler = handler;
          }
        }),
        removeEventListener: jest.fn()
      };
      
      // Mock Worker constructor
      global.Worker = jest.fn(() => mockWorker) as any;
    });
    
    test('uses worker threads when available', async () => {
      const processor = new DataProcessor({ useWorkers: true });
      
      const data = { id: 1, value: 'test' };
      
      // Start processing (will create a promise we need to resolve)
      const processPromise = processor.process(data, input => input);
      
      // Verify worker was created and data was posted
      expect(global.Worker).toHaveBeenCalled();
      expect(mockWorker.postMessage).toHaveBeenCalledWith({
        data,
        functionCode: expect.any(String)
      });
      
      // Simulate worker response
      mockWorker.messageHandler({ data: { result: { processed: true } } });
      
      // Now the promise should resolve
      const result = await processPromise;
      
      // Verify worker was terminated after use
      expect(mockWorker.terminate).toHaveBeenCalled();
      expect(result).toEqual({ processed: true });
    });
    
    test('falls back to synchronous processing on worker error', async () => {
      const processor = new DataProcessor({ useWorkers: true });
      
      const data = { id: 1, value: 'test' };
      const processFunction = jest.fn(input => ({ processed: input }));
      
      // Start processing (will create a promise we need to reject)
      const processPromise = processor.process(data, processFunction);
      
      // Simulate worker error
      mockWorker.messageHandler({ data: { error: 'Worker error' } });
      
      // Now the promise should resolve with fallback processing
      const result = await processPromise;
      
      // Synchronous processing should have been used as fallback
      expect(processFunction).toHaveBeenCalledWith(data);
      expect(result).toEqual({ processed: data });
    });
  });
  
  describe('performance metrics', () => {
    test('tracks processing time', async () => {
      const processor = new DataProcessor({ useWorkers: false });
      
      // Mock Date.now to control timing
      const originalNow = Date.now;
      const mockTimes = [100, 150]; // 50ms processing time
      Date.now = jest.fn(() => mockTimes.shift()) as any;
      
      const data = { id: 1, value: 'test' };
      await processor.process(data, input => input);
      
      const metrics = processor.getPerformanceMetrics();
      
      // Restore original Date.now
      Date.now = originalNow;
      
      expect(metrics.totalProcessingTime).toBe(50);
      expect(metrics.processedItemCount).toBe(1);
      expect(metrics.averageProcessingTime).toBe(50);
    });
    
    test('tracks cache hits and misses', async () => {
      const processor = new DataProcessor({ useCache: true, useWorkers: false });
      
      const data = { id: 1, value: 'test' };
      const processFunction = jest.fn(input => input);
      
      // First call - cache miss
      await processor.process(data, processFunction);
      
      // Second call - cache hit
      await processor.process(data, processFunction);
      
      const metrics = processor.getPerformanceMetrics();
      
      expect(metrics.cacheHits).toBe(1);
      expect(metrics.cacheMisses).toBe(1);
      expect(metrics.cacheHitRatio).toBe(0.5);
    });
    
    test('tracks batch processing metrics', async () => {
      const processor = new DataProcessor({ 
        batchSize: 3,
        useWorkers: false 
      });
      
      const items = [
        { id: 1 }, { id: 2 }, { id: 3 }, 
        { id: 4 }, { id: 5 }, { id: 6 }, 
        { id: 7 }
      ];
      
      await processor.processBatch(items, batch => batch);
      
      const metrics = processor.getPerformanceMetrics();
      
      expect(metrics.batchesProcessed).toBe(3); // 3 batches (3, 3, 1)
      expect(metrics.totalBatchItems).toBe(7);
      expect(metrics.averageBatchSize).toBeCloseTo(2.33, 1); // ~2.33 items per batch
    });
  });
  
  describe('browser-specific optimizations', () => {
    test('applies Chrome-specific optimizations', () => {
      (BrowserUtils.getBrowserInfo as jest.Mock).mockReturnValue({
        type: 'chrome',
        name: 'chrome',
        version: '100.0.0'
      });
      
      const processor = new DataProcessor();
      
      // Chrome should use workers by default
      expect((processor as any).options.useWorkers).toBe(true);
    });
    
    test('applies Firefox-specific optimizations', () => {
      (BrowserUtils.getBrowserInfo as jest.Mock).mockReturnValue({
        type: 'firefox',
        name: 'firefox',
        version: '95.0.0'
      });
      
      // Mock that Firefox supports workers
      (BrowserUtils.supportsWorkerThreads as jest.Mock).mockReturnValue(true);
      
      const processor = new DataProcessor();
      
      // Firefox should use workers if supported
      expect((processor as any).options.useWorkers).toBe(true);
    });
    
    test('applies optimizations for older browsers', () => {
      (BrowserUtils.getBrowserInfo as jest.Mock).mockReturnValue({
        type: 'firefox',
        name: 'firefox',
        version: '60.0.0' // Older version
      });
      
      // Mock that older Firefox doesn't support workers well
      (BrowserUtils.supportsWorkerThreads as jest.Mock).mockReturnValue(false);
      
      const processor = new DataProcessor();
      
      // Should disable workers for older Firefox
      expect((processor as any).options.useWorkers).toBe(false);
      
      // Should likely use smaller batch sizes/cache sizes for older browsers
      // This is implementation dependent
    });
  });
  
  describe('memory management', () => {
    test('clearCache removes all cached results', async () => {
      const processor = new DataProcessor({ useCache: true });
      
      // Process some data to populate cache
      await processor.process({ id: 1 }, data => data);
      await processor.process({ id: 2 }, data => data);
      
      // Clear the cache
      processor.clearCache();
      
      // Access private cache for testing
      const cache = (processor as any).cache;
      
      expect(cache.size).toBe(0);
    });
    
    test('reset clears all state including metrics', async () => {
      const processor = new DataProcessor();
      
      // Process some data to populate metrics
      await processor.process({ id: 1 }, data => data);
      
      // Reset everything
      processor.reset();
      
      const metrics = processor.getPerformanceMetrics();
      
      expect(metrics.processedItemCount).toBe(0);
      expect(metrics.totalProcessingTime).toBe(0);
      expect(metrics.cacheHits).toBe(0);
    });
  });
});
