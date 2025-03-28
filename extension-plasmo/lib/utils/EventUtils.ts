/**
 * EventUtils - Cross-browser compatible event handling utilities
 * 
 * Provides standardized event handling across different browsers
 * to ensure consistent behavior and proper event listener cleanup.
 */

export interface EventOptions {
  capture?: boolean;
  passive?: boolean;
  once?: boolean;
}

// Track event listeners for proper cleanup
interface TrackedListener {
  target: EventTarget;
  event: string;
  handler: EventListener;
  options: EventOptions;
}

const trackedListeners: TrackedListener[] = [];

/**
 * Add an event listener with proper tracking for cleanup
 */
export function addEventListener(
  target: EventTarget,
  event: string,
  handler: EventListener,
  options: EventOptions = {}
): void {
  // Normalize options for cross-browser compatibility
  const normalizedOptions = normalizeOptions(options);
  
  // Add the event listener
  target.addEventListener(event, handler, normalizedOptions);
  
  // Track for later cleanup
  trackedListeners.push({
    target,
    event,
    handler,
    options: normalizedOptions
  });
}

/**
 * Remove a previously added event listener
 */
export function removeEventListener(
  target: EventTarget,
  event: string,
  handler: EventListener,
  options: EventOptions = {}
): void {
  // Normalize options for cross-browser compatibility
  const normalizedOptions = normalizeOptions(options);
  
  // Remove the event listener
  target.removeEventListener(event, handler, normalizedOptions);
  
  // Remove from tracked listeners
  const index = trackedListeners.findIndex(listener => 
    listener.target === target && 
    listener.event === event && 
    listener.handler === handler
  );
  
  if (index !== -1) {
    trackedListeners.splice(index, 1);
  }
}

/**
 * Clean up all event listeners for a specific target
 */
export function cleanupEventListeners(target?: EventTarget): void {
  const listenersToRemove = target 
    ? trackedListeners.filter(listener => listener.target === target)
    : [...trackedListeners];
  
  listenersToRemove.forEach(listener => {
    listener.target.removeEventListener(
      listener.event, 
      listener.handler, 
      listener.options
    );
    
    const index = trackedListeners.indexOf(listener);
    if (index !== -1) {
      trackedListeners.splice(index, 1);
    }
  });
}

/**
 * Create a one-time event listener that cleans itself up
 */
export function addOneTimeEventListener(
  target: EventTarget,
  event: string,
  handler: EventListener,
  options: EventOptions = {}
): void {
  const wrappedHandler: EventListener = (e: Event) => {
    // Remove this listener first
    removeEventListener(target, event, wrappedHandler, options);
    
    // Then call the original handler
    handler(e);
  };
  
  addEventListener(target, event, wrappedHandler, options);
}

/**
 * Get browser-specific event properties in a standardized way
 */
export function getEventDetails(event: Event): Record<string, any> {
  const details: Record<string, any> = {};
  
  // Standard properties available in all browsers
  details.type = event.type;
  details.bubbles = event.bubbles;
  details.cancelable = event.cancelable;
  details.composed = event.composed;
  details.timeStamp = event.timeStamp;
  details.defaultPrevented = event.defaultPrevented;
  
  return details;
}

/**
 * Normalize options object for cross-browser compatibility
 */
function normalizeOptions(options: EventOptions): EventOptions | boolean {
  // For older browsers that don't support options object, 
  // return just the capture value
  if (!supportsPassive()) {
    return Boolean(options.capture);
  }
  
  return options;
}

/**
 * Check if the browser supports passive event listeners
 */
function supportsPassive(): boolean {
  let supportsPassive = false;
  try {
    // Test for passive option support
    const options = {
      get passive() {
        supportsPassive = true;
        return false;
      }
    };
    
    window.addEventListener('test', null as any, options);
    window.removeEventListener('test', null as any, options);
  } catch (e) {
    // Ignore errors
  }
  
  return supportsPassive;
}

/**
 * Detect the current browser environment
 */
export function detectBrowser(): {
  name: string;
  version: string;
  isChrome: boolean;
  isFirefox: boolean;
  isEdge: boolean;
  isSafari: boolean;
  isOpera: boolean;
} {
  const ua = navigator.userAgent;
  let browserName = '';
  let browserVersion = '';
  
  // Detect Chrome
  if (/Chrome/.test(ua) && !/Chromium|Edge|Edg|OPR|Opera|Brave/.test(ua)) {
    browserName = 'Chrome';
    browserVersion = ua.match(/Chrome\/(\d+\.\d+)/)![1];
  } 
  // Detect Firefox
  else if (/Firefox/.test(ua)) {
    browserName = 'Firefox';
    browserVersion = ua.match(/Firefox\/(\d+\.\d+)/)![1];
  } 
  // Detect Edge (Chromium-based)
  else if (/Edg\//.test(ua)) {
    browserName = 'Edge';
    browserVersion = ua.match(/Edg\/(\d+\.\d+)/)![1];
  }
  // Detect Safari
  else if (/Safari/.test(ua) && !/Chrome/.test(ua)) {
    browserName = 'Safari';
    browserVersion = ua.match(/Version\/(\d+\.\d+)/)![1];
  }
  // Detect Opera
  else if (/OPR|Opera/.test(ua)) {
    browserName = 'Opera';
    browserVersion = ua.match(/(?:OPR|Opera)\/(\d+\.\d+)/)![1];
  }
  
  return {
    name: browserName,
    version: browserVersion,
    isChrome: browserName === 'Chrome',
    isFirefox: browserName === 'Firefox',
    isEdge: browserName === 'Edge',
    isSafari: browserName === 'Safari',
    isOpera: browserName === 'Opera'
  };
}

export default {
  addEventListener,
  removeEventListener,
  cleanupEventListeners,
  addOneTimeEventListener,
  getEventDetails,
  detectBrowser
};
