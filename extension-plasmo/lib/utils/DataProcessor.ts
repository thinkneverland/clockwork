/**
 * DataProcessor - Efficient data processing utilities
 * 
 * Provides optimized data processing with:
 * - Worker thread utilization when available
 * - Smart caching to avoid redundant processing
 * - Optimized JSON handling for large objects/arrays
 * - Batch processing capabilities
 * - Performance metrics tracking
 */

interface ProcessingMetrics {
  operationId: string;
  operationType: string;
  inputSize: number;
  outputSize: number;
  processingTime: number;
  timestamp: number;
}

interface CacheEntry<T> {
  value: T;
  timestamp: number;
  hits: number;
}

type ProcessorFunction<T, R> = (data: T) => R;

/**
 * Cache for optimizing repeated operations
 */
class ProcessorCache<T, R> {
  private cache: Map<string, CacheEntry<R>> = new Map();
  private maxSize: number;
  private serializer: (data: T) => string;
  private maxAge: number;
  
  constructor(options: {
    maxSize?: number;
    serializer?: (data: T) => string;
    maxAge?: number;
  } = {}) {
    this.maxSize = options.maxSize || 100;
    this.serializer = options.serializer || ((data) => JSON.stringify(data));
    this.maxAge = options.maxAge || 60000; // 1 minute default
  }
  
  /**
   * Get a value from the cache
   */
  public get(data: T): R | null {
    const key = this.serializer(data);
    const entry = this.cache.get(key);
    
    if (!entry) {
      return null;
    }
    
    // Check if the entry is still valid
    if (Date.now() - entry.timestamp > this.maxAge) {
      this.cache.delete(key);
      return null;
    }
    
    // Increment hit count
    entry.hits++;
    return entry.value;
  }
  
  /**
   * Store a value in the cache
   */
  public set(data: T, result: R): void {
    // Ensure we don't exceed max size by removing least used entries
    if (this.cache.size >= this.maxSize) {
      this.pruneCache();
    }
    
    const key = this.serializer(data);
    this.cache.set(key, {
      value: result,
      timestamp: Date.now(),
      hits: 1
    });
  }
  
  /**
   * Remove older or less frequently used entries
   */
  private pruneCache(): void {
    // Sort entries by hit count (least used first)
    const entries = Array.from(this.cache.entries());
    entries.sort((a, b) => a[1].hits - b[1].hits);
    
    // Remove 25% of least used entries
    const removeCount = Math.max(1, Math.floor(entries.length * 0.25));
    for (let i = 0; i < removeCount; i++) {
      this.cache.delete(entries[i][0]);
    }
  }
  
  /**
   * Clear the cache
   */
  public clear(): void {
    this.cache.clear();
  }
  
  /**
   * Get cache statistics
   */
  public getStats(): {
    size: number;
    maxSize: number;
    hitRate: number;
    averageAge: number;
  } {
    const entries = Array.from(this.cache.values());
    const totalHits = entries.reduce((sum, entry) => sum + entry.hits, 0);
    const now = Date.now();
    const totalAge = entries.reduce((sum, entry) => sum + (now - entry.timestamp), 0);
    
    return {
      size: this.cache.size,
      maxSize: this.maxSize,
      hitRate: totalHits / Math.max(1, this.cache.size),
      averageAge: totalAge / Math.max(1, entries.length)
    };
  }
}

/**
 * Main data processor class
 */
export class DataProcessor {
  private worker: Worker | null = null;
  private processors: Map<string, ProcessorFunction<any, any>> = new Map();
  private cache: ProcessorCache<any, any>;
  private metrics: ProcessingMetrics[] = [];
  private maxMetrics: number;
  private useWorker: boolean;
  private pendingTasks: Map<string, {
    resolve: (result: any) => void;
    reject: (error: any) => void;
  }> = new Map();
  
  constructor(options: {
    useWorker?: boolean;
    workerUrl?: string;
    maxCacheSize?: number;
    maxCacheAge?: number;
    maxMetrics?: number;
  } = {}) {
    this.useWorker = options.useWorker !== false && this.isWorkerSupported();
    this.maxMetrics = options.maxMetrics || 100;
    
    // Initialize cache
    this.cache = new ProcessorCache({
      maxSize: options.maxCacheSize || 100,
      maxAge: options.maxCacheAge || 60000
    });
    
    // Initialize worker if enabled
    if (this.useWorker && options.workerUrl) {
      this.initWorker(options.workerUrl);
    }
  }
  
  /**
   * Register a processor function
   */
  public registerProcessor<T, R>(
    name: string,
    processor: ProcessorFunction<T, R>
  ): void {
    this.processors.set(name, processor);
    
    // If we have a worker, send the processor function to it
    if (this.worker && this.isSerializableFunction(processor)) {
      this.worker.postMessage({
        type: 'register',
        name,
        processor: processor.toString()
      });
    }
  }
  
  /**
   * Process data with a registered processor
   */
  public async process<T, R>(
    processorName: string,
    data: T,
    options: {
      useCache?: boolean;
      forceMainThread?: boolean;
    } = {}
  ): Promise<R> {
    const useCache = options.useCache !== false;
    const startTime = performance.now();
    const operationId = `${processorName}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    
    // Check cache first if enabled
    if (useCache) {
      const cachedResult = this.cache.get(data);
      if (cachedResult !== null) {
        return cachedResult as R;
      }
    }
    
    // Get the processor function
    const processor = this.processors.get(processorName);
    if (!processor) {
      throw new Error(`Processor "${processorName}" not found`);
    }
    
    let result: R;
    
    // Determine input size for metrics
    const inputSize = this.getDataSize(data);
    
    // Use worker if available and allowed
    if (this.worker && this.useWorker && !options.forceMainThread && this.isTransferable(data)) {
      result = await this.processInWorker(processorName, data, operationId);
    } else {
      // Process in main thread
      result = processor(data) as R;
    }
    
    // Store in cache if enabled
    if (useCache) {
      this.cache.set(data, result);
    }
    
    // Record metrics
    const endTime = performance.now();
    this.recordMetrics({
      operationId,
      operationType: processorName,
      inputSize,
      outputSize: this.getDataSize(result),
      processingTime: endTime - startTime,
      timestamp: Date.now()
    });
    
    return result;
  }
  
  /**
   * Process multiple items in batch
   */
  public async processBatch<T, R>(
    processorName: string,
    items: T[],
    options: {
      useCache?: boolean;
      forceMainThread?: boolean;
      concurrency?: number;
    } = {}
  ): Promise<R[]> {
    const concurrency = options.concurrency || Infinity;
    
    // If unlimited concurrency or small batch, process all at once
    if (concurrency === Infinity || items.length <= concurrency) {
      return Promise.all(
        items.map(item => this.process(processorName, item, options))
      );
    }
    
    // Otherwise, process in batches with limited concurrency
    const results: R[] = [];
    
    for (let i = 0; i < items.length; i += concurrency) {
      const batch = items.slice(i, i + concurrency);
      const batchResults = await Promise.all(
        batch.map(item => this.process(processorName, item, options))
      );
      results.push(...batchResults);
    }
    
    return results;
  }
  
  /**
   * Process data in the web worker
   */
  private processInWorker<T, R>(
    processorName: string,
    data: T,
    operationId: string
  ): Promise<R> {
    return new Promise((resolve, reject) => {
      if (!this.worker) {
        reject(new Error('Worker not available'));
        return;
      }
      
      // Store the callbacks for when the worker responds
      this.pendingTasks.set(operationId, { resolve, reject });
      
      // Send the task to the worker
      this.worker.postMessage({
        type: 'process',
        processorName,
        data,
        operationId
      });
    });
  }
  
  /**
   * Initialize the web worker
   */
  private initWorker(workerUrl: string): void {
    try {
      this.worker = new Worker(workerUrl);
      
      // Set up message handling
      this.worker.onmessage = (event) => {
        const { type, operationId, result, error } = event.data;
        
        if (type === 'result') {
          // Resolve the pending task
          const task = this.pendingTasks.get(operationId);
          if (task) {
            task.resolve(result);
            this.pendingTasks.delete(operationId);
          }
        } else if (type === 'error') {
          // Reject the pending task
          const task = this.pendingTasks.get(operationId);
          if (task) {
            task.reject(new Error(error));
            this.pendingTasks.delete(operationId);
          }
        }
      };
      
      // Handle worker errors
      this.worker.onerror = (error) => {
        console.error('Worker error:', error);
        
        // Reject all pending tasks
        for (const [operationId, task] of this.pendingTasks.entries()) {
          task.reject(new Error('Worker error'));
          this.pendingTasks.delete(operationId);
        }
        
        // Disable worker mode
        this.useWorker = false;
        this.worker = null;
      };
      
      // Register all existing processors with the worker
      for (const [name, processor] of this.processors.entries()) {
        if (this.isSerializableFunction(processor)) {
          this.worker.postMessage({
            type: 'register',
            name,
            processor: processor.toString()
          });
        }
      }
    } catch (error) {
      console.error('Failed to initialize worker:', error);
      this.useWorker = false;
      this.worker = null;
    }
  }
  
  /**
   * Record processing metrics
   */
  private recordMetrics(metrics: ProcessingMetrics): void {
    this.metrics.push(metrics);
    
    // Limit the number of metrics
    if (this.metrics.length > this.maxMetrics) {
      this.metrics.shift();
    }
  }
  
  /**
   * Get all recorded metrics
   */
  public getMetrics(): ProcessingMetrics[] {
    return [...this.metrics];
  }
  
  /**
   * Clear metrics
   */
  public clearMetrics(): void {
    this.metrics = [];
  }
  
  /**
   * Get cache statistics
   */
  public getCacheStats() {
    return this.cache.getStats();
  }
  
  /**
   * Clear the cache
   */
  public clearCache(): void {
    this.cache.clear();
  }
  
  /**
   * Check if web workers are supported
   */
  private isWorkerSupported(): boolean {
    return typeof Worker !== 'undefined';
  }
  
  /**
   * Check if a function can be serialized for the worker
   */
  private isSerializableFunction(fn: Function): boolean {
    // Regular functions and arrow functions should be serializable
    return typeof fn === 'function' && !fn.toString().includes('[native code]');
  }
  
  /**
   * Check if data can be transferred to a worker
   */
  private isTransferable(data: any): boolean {
    // Primitive types and simple objects should be transferable
    const type = typeof data;
    return type === 'string' || 
           type === 'number' || 
           type === 'boolean' || 
           data === null ||
           data === undefined ||
           Array.isArray(data) ||
           (type === 'object' && !(data instanceof File) && !(data instanceof Blob));
  }
  
  /**
   * Get the approximate size of data in bytes
   */
  private getDataSize(data: any): number {
    if (data === null || data === undefined) {
      return 0;
    }
    
    const type = typeof data;
    
    if (type === 'number') {
      return 8;
    }
    
    if (type === 'boolean') {
      return 4;
    }
    
    if (type === 'string') {
      return data.length * 2; // Approximation for UTF-16
    }
    
    if (Array.isArray(data)) {
      return data.reduce((size, item) => size + this.getDataSize(item), 0);
    }
    
    if (type === 'object') {
      if (data instanceof Date) {
        return 8;
      }
      
      if (data instanceof Map || data instanceof Set) {
        let size = 0;
        data.forEach((value) => {
          size += this.getDataSize(value);
        });
        return size;
      }
      
      return Object.keys(data).reduce((size, key) => {
        return size + key.length * 2 + this.getDataSize(data[key]);
      }, 0);
    }
    
    return 0;
  }
  
  /**
   * Cleanup resources
   */
  public cleanup(): void {
    if (this.worker) {
      this.worker.terminate();
      this.worker = null;
    }
    
    this.pendingTasks.clear();
    this.cache.clear();
    this.metrics = [];
  }
}

/**
 * Optimized JSON processor for large objects
 */
export class JsonProcessor {
  /**
   * Stringify with optimizations for large objects
   */
  public static stringify(data: any, options: {
    pretty?: boolean;
    circularReplacer?: boolean;
  } = {}): string {
    const { pretty = false, circularReplacer = true } = options;
    
    // Handle circular references
    if (circularReplacer) {
      const cache = new Set();
      const replacer = (key: string, value: any) => {
        if (typeof value === 'object' && value !== null) {
          if (cache.has(value)) {
            return '[Circular]';
          }
          cache.add(value);
        }
        return value;
      };
      
      return pretty 
        ? JSON.stringify(data, replacer, 2)
        : JSON.stringify(data, replacer);
    }
    
    return pretty 
      ? JSON.stringify(data, null, 2)
      : JSON.stringify(data);
  }
  
  /**
   * Optimized JSON parsing with error handling
   */
  public static parse(text: string): any {
    try {
      return JSON.parse(text);
    } catch (error) {
      console.error('Error parsing JSON:', error);
      return null;
    }
  }
  
  /**
   * Deep clone an object using JSON
   */
  public static clone<T>(data: T): T {
    return this.parse(this.stringify(data));
  }
  
  /**
   * Check if two objects are deeply equal
   */
  public static deepEqual(a: any, b: any): boolean {
    if (a === b) {
      return true;
    }
    
    if (typeof a !== 'object' || a === null || typeof b !== 'object' || b === null) {
      return false;
    }
    
    const keysA = Object.keys(a);
    const keysB = Object.keys(b);
    
    if (keysA.length !== keysB.length) {
      return false;
    }
    
    for (const key of keysA) {
      if (!keysB.includes(key)) {
        return false;
      }
      
      if (!this.deepEqual(a[key], b[key])) {
        return false;
      }
    }
    
    return true;
  }
  
  /**
   * Get a diff between two objects
   */
  public static diff(original: any, updated: any): Record<string, any> {
    const result: Record<string, any> = {};
    
    // Handle null/undefined cases
    if (original === updated) {
      return result;
    }
    
    if (original === null || updated === null || 
        original === undefined || updated === undefined ||
        typeof original !== 'object' || typeof updated !== 'object') {
      return { _value: updated };
    }
    
    // Find added or changed properties
    for (const key in updated) {
      if (Object.prototype.hasOwnProperty.call(updated, key)) {
        if (!(key in original)) {
          // Property added
          result[key] = updated[key];
        } else if (typeof updated[key] === 'object' && updated[key] !== null &&
                   typeof original[key] === 'object' && original[key] !== null) {
          // Recursive diff for objects
          const nestedDiff = this.diff(original[key], updated[key]);
          if (Object.keys(nestedDiff).length > 0) {
            result[key] = nestedDiff;
          }
        } else if (!this.deepEqual(original[key], updated[key])) {
          // Property changed
          result[key] = updated[key];
        }
      }
    }
    
    // Find removed properties
    for (const key in original) {
      if (Object.prototype.hasOwnProperty.call(original, key) && !(key in updated)) {
        // Property removed
        result[key] = null;
      }
    }
    
    return result;
  }
}

export default {
  DataProcessor,
  JsonProcessor
};
