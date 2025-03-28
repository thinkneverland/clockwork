/**
 * StateOptimizer - Performance optimization utilities
 * 
 * Provides utilities for:
 * - Memoization to prevent redundant calculations
 * - Selective state updates to avoid unnecessary renders
 * - Batched updates for better performance
 * - Memory monitoring to detect memory leaks
 * - Debounce and throttle utilities for UI interactions
 */

/**
 * Create a memoized version of a function
 * @param fn Function to memoize
 * @param getKey Optional function to generate cache key
 */
export function memoize<T extends (...args: any[]) => any>(
  fn: T,
  getKey: (...args: Parameters<T>) => string = (...args) => JSON.stringify(args)
): T {
  const cache = new Map<string, ReturnType<T>>();
  
  return ((...args: Parameters<T>): ReturnType<T> => {
    const key = getKey(...args);
    
    if (cache.has(key)) {
      return cache.get(key) as ReturnType<T>;
    }
    
    const result = fn(...args);
    cache.set(key, result);
    return result;
  }) as T;
}

/**
 * Create a function that only updates its result when inputs change
 * @param fn Function to optimize
 * @param equalityFn Function to check if inputs are equal
 */
export function createSelector<T extends (...args: any[]) => any>(
  fn: T,
  equalityFn: (prev: Parameters<T>, next: Parameters<T>) => boolean = 
    (prev, next) => JSON.stringify(prev) === JSON.stringify(next)
): T {
  let lastArgs: Parameters<T> | null = null;
  let lastResult: ReturnType<T> | null = null;
  
  return ((...args: Parameters<T>): ReturnType<T> => {
    if (lastArgs && equalityFn(lastArgs, args)) {
      return lastResult as ReturnType<T>;
    }
    
    lastArgs = args;
    lastResult = fn(...args);
    return lastResult;
  }) as T;
}

/**
 * Batch multiple updates together to avoid redundant processing
 */
export class BatchProcessor {
  private queue: Array<() => void> = [];
  private timeout: number | null = null;
  private batchTime: number;
  
  constructor(batchTime: number = 50) {
    this.batchTime = batchTime;
  }
  
  /**
   * Add a task to the batch queue
   */
  public add(task: () => void): void {
    this.queue.push(task);
    this.scheduleProcessing();
  }
  
  /**
   * Process all queued tasks
   */
  public flush(): void {
    this.cancelScheduledProcessing();
    this.processQueue();
  }
  
  /**
   * Schedule processing of the queue
   */
  private scheduleProcessing(): void {
    if (this.timeout === null) {
      this.timeout = window.setTimeout(() => {
        this.processQueue();
      }, this.batchTime);
    }
  }
  
  /**
   * Cancel scheduled processing
   */
  private cancelScheduledProcessing(): void {
    if (this.timeout !== null) {
      clearTimeout(this.timeout);
      this.timeout = null;
    }
  }
  
  /**
   * Process all tasks in the queue
   */
  private processQueue(): void {
    this.timeout = null;
    
    const tasks = [...this.queue];
    this.queue = [];
    
    for (const task of tasks) {
      try {
        task();
      } catch (error) {
        console.error('Error in batch processing task:', error);
      }
    }
  }
}

/**
 * Create a debounced version of a function
 * @param fn Function to debounce
 * @param delay Delay in milliseconds
 */
export function debounce<T extends (...args: any[]) => any>(
  fn: T,
  delay: number
): (...args: Parameters<T>) => void {
  let timeout: number | null = null;
  
  return (...args: Parameters<T>): void => {
    if (timeout !== null) {
      clearTimeout(timeout);
    }
    
    timeout = window.setTimeout(() => {
      fn(...args);
      timeout = null;
    }, delay);
  };
}

/**
 * Create a throttled version of a function
 * @param fn Function to throttle
 * @param limit Minimum time between executions in milliseconds
 */
export function throttle<T extends (...args: any[]) => any>(
  fn: T,
  limit: number
): (...args: Parameters<T>) => void {
  let lastCall = 0;
  let timeout: number | null = null;
  let lastArgs: Parameters<T> | null = null;
  
  return (...args: Parameters<T>): void => {
    const now = Date.now();
    
    // Store the latest arguments
    lastArgs = args;
    
    if (now - lastCall >= limit) {
      // If enough time has passed, execute immediately
      lastCall = now;
      fn(...args);
    } else if (timeout === null) {
      // Schedule for later execution with latest args
      timeout = window.setTimeout(() => {
        lastCall = Date.now();
        timeout = null;
        if (lastArgs) {
          fn(...lastArgs);
        }
      }, limit - (now - lastCall));
    }
  };
}

/**
 * Memory usage tracking for detecting memory leaks
 */
export class MemoryMonitor {
  private snapshots: Array<{
    timestamp: number;
    usage: MemoryUsage;
  }> = [];
  private maxSnapshots: number;
  private intervalId: number | null = null;
  private thresholds: {
    growth: number;
    total: number;
  };
  
  constructor(options: {
    maxSnapshots?: number;
    growthThreshold?: number;
    totalThreshold?: number;
  } = {}) {
    this.maxSnapshots = options.maxSnapshots || 20;
    this.thresholds = {
      growth: options.growthThreshold || 20, // 20% growth rate threshold
      total: options.totalThreshold || 100 * 1024 * 1024 // 100MB default threshold
    };
  }
  
  /**
   * Start monitoring memory usage
   * @param interval Time between snapshots in milliseconds
   */
  public start(interval: number = 30000): void {
    this.stop();
    
    // Take initial snapshot
    this.takeSnapshot();
    
    // Set up interval
    this.intervalId = window.setInterval(() => {
      this.takeSnapshot();
      this.analyze();
    }, interval);
  }
  
  /**
   * Stop monitoring memory usage
   */
  public stop(): void {
    if (this.intervalId !== null) {
      clearInterval(this.intervalId);
      this.intervalId = null;
    }
  }
  
  /**
   * Take a snapshot of current memory usage
   */
  public takeSnapshot(): void {
    const usage = this.getMemoryUsage();
    
    this.snapshots.push({
      timestamp: Date.now(),
      usage
    });
    
    // Limit the number of snapshots
    if (this.snapshots.length > this.maxSnapshots) {
      this.snapshots.shift();
    }
  }
  
  /**
   * Analyze memory usage for potential leaks
   */
  public analyze(): {
    hasLeak: boolean;
    averageGrowth: number;
    totalMemory: number;
  } {
    if (this.snapshots.length < 2) {
      return {
        hasLeak: false,
        averageGrowth: 0,
        totalMemory: this.snapshots[0]?.usage.totalJSHeapSize || 0
      };
    }
    
    // Calculate memory growth rate
    const growthRates: number[] = [];
    
    for (let i = 1; i < this.snapshots.length; i++) {
      const prev = this.snapshots[i - 1].usage.usedJSHeapSize;
      const current = this.snapshots[i].usage.usedJSHeapSize;
      
      if (prev > 0) {
        const growthRate = ((current - prev) / prev) * 100;
        growthRates.push(growthRate);
      }
    }
    
    // Calculate average growth rate
    const averageGrowth = growthRates.reduce((sum, rate) => sum + rate, 0) / growthRates.length;
    
    // Get current memory usage
    const latestSnapshot = this.snapshots[this.snapshots.length - 1];
    const totalMemory = latestSnapshot.usage.totalJSHeapSize;
    
    // Check if growth rate or total memory exceeds thresholds
    const hasLeak = averageGrowth > this.thresholds.growth || 
                    totalMemory > this.thresholds.total;
    
    if (hasLeak) {
      console.warn('Potential memory leak detected:', {
        averageGrowth,
        totalMemory,
        snapshots: this.snapshots
      });
    }
    
    return {
      hasLeak,
      averageGrowth,
      totalMemory
    };
  }
  
  /**
   * Get memory usage statistics
   */
  private getMemoryUsage(): MemoryUsage {
    // Check if performance.memory is available (Chrome only)
    if (window.performance && 'memory' in window.performance) {
      const memory = (window.performance as any).memory;
      return {
        totalJSHeapSize: memory.totalJSHeapSize,
        usedJSHeapSize: memory.usedJSHeapSize,
        jsHeapSizeLimit: memory.jsHeapSizeLimit
      };
    }
    
    // Fallback if not available
    return {
      totalJSHeapSize: 0,
      usedJSHeapSize: 0,
      jsHeapSizeLimit: 0
    };
  }
  
  /**
   * Clear all snapshots
   */
  public clearSnapshots(): void {
    this.snapshots = [];
  }
  
  /**
   * Get all snapshots
   */
  public getSnapshots(): Array<{
    timestamp: number;
    usage: MemoryUsage;
  }> {
    return [...this.snapshots];
  }
}

interface MemoryUsage {
  totalJSHeapSize: number;
  usedJSHeapSize: number;
  jsHeapSizeLimit: number;
}

/**
 * Selectively update only specific objects in a nested data structure
 * @param obj Original object
 * @param path Path to the property to update
 * @param value New value
 */
export function updatePath<T extends Record<string, any>>(
  obj: T,
  path: string,
  value: any
): T {
  const parts = path.split('.');
  
  // For simple paths, update directly
  if (parts.length === 1) {
    return {
      ...obj,
      [path]: value
    };
  }
  
  // Handle nested paths
  const [first, ...rest] = parts;
  const restPath = rest.join('.');
  
  // Handle array paths
  if (first.includes('[')) {
    const arrayName = first.substring(0, first.indexOf('['));
    const index = parseInt(first.substring(first.indexOf('[') + 1, first.indexOf(']')), 10);
    
    if (!obj[arrayName] || !Array.isArray(obj[arrayName])) {
      throw new Error(`Property ${arrayName} is not an array`);
    }
    
    // Create a new array with the updated item
    const updatedArray = [...obj[arrayName]];
    
    if (rest.length === 0) {
      // Update array item directly
      updatedArray[index] = value;
    } else {
      // Update nested property in array item
      updatedArray[index] = updatePath(
        updatedArray[index] || {},
        restPath,
        value
      );
    }
    
    return {
      ...obj,
      [arrayName]: updatedArray
    };
  }
  
  // Handle regular nested properties
  return {
    ...obj,
    [first]: updatePath(
      obj[first] || {},
      restPath,
      value
    )
  };
}

export default {
  memoize,
  createSelector,
  BatchProcessor,
  debounce,
  throttle,
  MemoryMonitor,
  updatePath
};
