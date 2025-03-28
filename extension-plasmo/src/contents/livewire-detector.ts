/**
 * Livewire Detector Content Script
 * 
 * Detects Livewire components on the page and establishes communication
 * with the background script for real-time debugging.
 */

import EventUtils from "../../lib/utils/EventUtils"

interface LivewireComponent {
  id: string;
  name: string;
  fingerprint?: string;
  version?: string;
  properties?: Record<string, any>;
  events?: any[];
  children?: LivewireComponent[];
}

interface DetectionResult {
  hasLivewire: boolean;
  version?: string;
  components?: LivewireComponent[];
  timestamp: number;
}

class LivewireDetector {
  private initialized = false;
  private observer: MutationObserver | null = null;
  private components: Map<string, LivewireComponent> = new Map();
  private livewireVersion: string | undefined;
  private detectionInterval: number | null = null;
  private browserInfo = EventUtils.detectBrowser();
  
  constructor() {
    this.initialize();
  }
  
  /**
   * Initialize the detector
   */
  private initialize(): void {
    if (this.initialized) {
      return;
    }
    
    // Don't run in iframes unless explicitly configured to do so
    if (window !== window.top) {
      return;
    }
    
    console.log("[Tapped] Livewire detector initializing");
    
    // Setup mutation observer to watch for Livewire components being added
    this.setupMutationObserver();
    
    // Start periodic detection (for cases the mutation observer might miss)
    this.startPeriodicDetection();
    
    // Listen for messages from the extension
    this.setupMessageListeners();
    
    this.initialized = true;
    
    // Run initial detection
    this.detectLivewireComponents();
  }
  
  /**
   * Set up mutation observer to watch for DOM changes
   */
  private setupMutationObserver(): void {
    this.observer = new MutationObserver((mutations) => {
      let shouldDetect = false;
      
      for (const mutation of mutations) {
        // Check if any new elements were added
        if (mutation.type === "childList" && mutation.addedNodes.length > 0) {
          for (let i = 0; i < mutation.addedNodes.length; i++) {
            const node = mutation.addedNodes[i];
            if (node.nodeType === Node.ELEMENT_NODE) {
              const element = node as Element;
              
              // Look for Livewire attributes
              if (this.hasLivewireAttributes(element)) {
                shouldDetect = true;
                break;
              }
              
              // Check children for Livewire attributes
              if (element.querySelector('[wire\\:id], [wire\\:initial-data], [wire\\:model], [wire\\:poll]')) {
                shouldDetect = true;
                break;
              }
            }
          }
        }
        
        if (shouldDetect) {
          break;
        }
      }
      
      if (shouldDetect) {
        this.detectLivewireComponents();
      }
    });
    
    // Observe the entire document for changes
    this.observer.observe(document.documentElement, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['wire:id', 'wire:initial-data', 'wire:model', 'wire:key']
    });
  }
  
  /**
   * Start periodic detection of Livewire components
   */
  private startPeriodicDetection(): void {
    this.detectionInterval = window.setInterval(() => {
      this.detectLivewireComponents();
    }, 5000); // Check every 5 seconds
  }
  
  /**
   * Set up listeners for messages from the extension
   */
  private setupMessageListeners(): void {
    // Listen for messages from the extension
    EventUtils.addEventListener(window, 'message', (event) => {
      // Only accept messages from the same window
      if (event.source !== window) {
        return;
      }
      
      const message = event.data;
      
      // Check if this is a message for us
      if (message && message.source === 'tapped-extension') {
        this.handleMessage(message);
      }
    });
    
    // Notify the extension that we're ready
    window.postMessage({
      source: 'tapped-livewire-detector',
      type: 'init',
      payload: {
        url: window.location.href,
        timestamp: Date.now()
      }
    }, '*');
  }
  
  /**
   * Handle messages from the extension
   */
  private handleMessage(message: any): void {
    switch (message.type) {
      case 'detect-livewire':
        this.detectLivewireComponents();
        break;
        
      case 'get-component':
        if (message.payload && message.payload.id) {
          const component = this.getComponentById(message.payload.id);
          if (component) {
            this.sendToExtension('component-details', component);
          }
        }
        break;
        
      case 'highlight-component':
        if (message.payload && message.payload.id) {
          this.highlightComponent(message.payload.id);
        }
        break;
        
      case 'execute-method':
        if (message.payload && message.payload.id && message.payload.method) {
          this.executeComponentMethod(
            message.payload.id, 
            message.payload.method, 
            message.payload.params
          );
        }
        break;
        
      case 'update-property':
        if (message.payload && message.payload.id && message.payload.property) {
          this.updateComponentProperty(
            message.payload.id,
            message.payload.property,
            message.payload.value
          );
        }
        break;
    }
  }
  
  /**
   * Detect Livewire components on the page
   */
  private detectLivewireComponents(): void {
    // First check if Livewire is present
    const hasLivewire = this.isLivewirePresent();
    
    if (!hasLivewire) {
      this.sendDetectionResult({
        hasLivewire: false,
        timestamp: Date.now()
      });
      return;
    }
    
    // Detect the Livewire version
    this.detectLivewireVersion();
    
    // Scan the DOM for Livewire components
    this.scanForComponents();
    
    // Send the detection result
    this.sendDetectionResult({
      hasLivewire: true,
      version: this.livewireVersion,
      components: Array.from(this.components.values()),
      timestamp: Date.now()
    });
  }
  
  /**
   * Check if Livewire is present on the page
   */
  private isLivewirePresent(): boolean {
    // Check for Livewire global object (v2+)
    if (window.Livewire) {
      return true;
    }
    
    // Check for v1 style Livewire elements
    if (document.querySelector('[wire\\:id], [wire\\:initial-data], [wire\\:model], [wire\\:poll]')) {
      return true;
    }
    
    // Check for Livewire scripts
    const scripts = document.querySelectorAll('script');
    for (let i = 0; i < scripts.length; i++) {
      const src = scripts[i].getAttribute('src') || '';
      if (src.includes('livewire.js') || src.includes('livewire.min.js')) {
        return true;
      }
      
      // Check inline scripts for Livewire references
      const content = scripts[i].textContent || '';
      if (content.includes('Livewire') || content.includes('livewire')) {
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Detect Livewire version
   */
  private detectLivewireVersion(): void {
    // Try to detect based on global Livewire object
    if (window.Livewire) {
      if (window.Livewire.version) {
        this.livewireVersion = window.Livewire.version;
        return;
      }
      
      // Check for specific properties that indicate version
      if (window.Livewire.hooks) {
        this.livewireVersion = 'v2+';
        return;
      }
      
      if (window.Livewire.start) {
        this.livewireVersion = 'v3+';
        return;
      }
    }
    
    // Check for version in meta tags
    const metaTags = document.querySelectorAll('meta');
    for (let i = 0; i < metaTags.length; i++) {
      const name = metaTags[i].getAttribute('name');
      if (name === 'livewire:version') {
        this.livewireVersion = metaTags[i].getAttribute('content') || 'unknown';
        return;
      }
    }
    
    // Try to infer from DOM structure
    if (document.querySelector('[wire\\:id]')) {
      // If we have wire:id attributes, it's likely v2+
      this.livewireVersion = 'v2+';
    } else if (document.querySelector('[wire\\:initial-data]')) {
      // v1 used wire:initial-data
      this.livewireVersion = 'v1';
    } else {
      this.livewireVersion = 'unknown';
    }
  }
  
  /**
   * Scan the DOM for Livewire components
   */
  private scanForComponents(): void {
    const componentElements = document.querySelectorAll('[wire\\:id], [wire\\:initial-data]');
    
    componentElements.forEach(element => {
      const component = this.extractComponentData(element as HTMLElement);
      if (component && component.id) {
        this.components.set(component.id, component);
      }
    });
  }
  
  /**
   * Extract component data from a DOM element
   */
  private extractComponentData(element: HTMLElement): LivewireComponent | null {
    // For v2+
    const wireId = element.getAttribute('wire:id');
    if (wireId) {
      return this.extractV2ComponentData(element, wireId);
    }
    
    // For v1
    const initialData = element.getAttribute('wire:initial-data');
    if (initialData) {
      return this.extractV1ComponentData(element, initialData);
    }
    
    return null;
  }
  
  /**
   * Extract data for a Livewire v2+ component
   */
  private extractV2ComponentData(element: HTMLElement, wireId: string): LivewireComponent | null {
    try {
      // Try to extract from Livewire global object
      if (window.Livewire && window.Livewire.components) {
        const componentData = window.Livewire.components.componentsById[wireId];
        if (componentData) {
          return {
            id: wireId,
            name: componentData.name || this.getComponentName(element),
            fingerprint: componentData.fingerprint,
            version: 'v2+',
            properties: componentData.data || {},
            events: [],
            children: this.findChildComponents(element)
          };
        }
      }
      
      // Fallback to extracting from DOM
      const name = this.getComponentName(element);
      return {
        id: wireId,
        name: name,
        version: 'v2+',
        properties: {},
        children: this.findChildComponents(element)
      };
    } catch (error) {
      console.error('[Tapped] Error extracting v2 component data:', error);
      return null;
    }
  }
  
  /**
   * Extract data for a Livewire v1 component
   */
  private extractV1ComponentData(element: HTMLElement, initialDataStr: string): LivewireComponent | null {
    try {
      const initialData = JSON.parse(initialDataStr);
      return {
        id: initialData.id || `v1-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
        name: initialData.name || this.getComponentName(element),
        version: 'v1',
        properties: initialData.data || {},
        children: this.findChildComponents(element)
      };
    } catch (error) {
      console.error('[Tapped] Error extracting v1 component data:', error);
      return null;
    }
  }
  
  /**
   * Get component name from element
   */
  private getComponentName(element: HTMLElement): string {
    // Try to get component name from various attributes
    const componentName = element.getAttribute('wire:component');
    if (componentName) {
      return componentName;
    }
    
    // Look for a data-component attribute
    const dataComponent = element.getAttribute('data-component');
    if (dataComponent) {
      return dataComponent;
    }
    
    // Try to infer from class name (common naming convention)
    const className = element.className;
    if (className && className.includes('-component')) {
      const matches = className.match(/[a-zA-Z0-9]+-component/);
      if (matches && matches.length > 0) {
        return matches[0];
      }
    }
    
    // Last resort: use the tag name and an identifier
    return `${element.tagName.toLowerCase()}-${element.getAttribute('wire:id') || 'unknown'}`;
  }
  
  /**
   * Find child components inside a parent component
   */
  private findChildComponents(parentElement: HTMLElement): LivewireComponent[] {
    const children: LivewireComponent[] = [];
    
    // Find all child elements with wire:id but exclude the parent
    const childElements = parentElement.querySelectorAll('[wire\\:id], [wire\\:initial-data]');
    
    childElements.forEach(childElement => {
      // Skip if this is the parent element
      if (childElement === parentElement) {
        return;
      }
      
      // Check if this element is a true child (not inside another component)
      let isDirectChild = true;
      let parent = childElement.parentElement;
      
      while (parent && parent !== parentElement) {
        if (
          parent.hasAttribute('wire:id') || 
          parent.hasAttribute('wire:initial-data')
        ) {
          isDirectChild = false;
          break;
        }
        parent = parent.parentElement;
      }
      
      if (isDirectChild) {
        const component = this.extractComponentData(childElement as HTMLElement);
        if (component) {
          children.push(component);
        }
      }
    });
    
    return children;
  }
  
  /**
   * Check if an element has Livewire attributes
   */
  private hasLivewireAttributes(element: Element): boolean {
    return (
      element.hasAttribute('wire:id') ||
      element.hasAttribute('wire:initial-data') ||
      element.hasAttribute('wire:model') ||
      element.hasAttribute('wire:click') ||
      element.hasAttribute('wire:poll')
    );
  }
  
  /**
   * Get a component by ID
   */
  private getComponentById(id: string): LivewireComponent | null {
    return this.components.get(id) || null;
  }
  
  /**
   * Highlight a component in the page
   */
  private highlightComponent(id: string): void {
    // Remove any existing highlights
    const existingHighlights = document.querySelectorAll('.tapped-component-highlight');
    existingHighlights.forEach(highlight => {
      highlight.remove();
    });
    
    // Find the component element
    const componentElement = document.querySelector(`[wire\\:id="${id}"], [wire\\:initial-data*="${id}"]`);
    if (!componentElement) {
      console.warn(`[Tapped] Component with ID ${id} not found in the DOM`);
      return;
    }
    
    // Create highlight overlay
    const rect = componentElement.getBoundingClientRect();
    const highlight = document.createElement('div');
    highlight.className = 'tapped-component-highlight';
    highlight.style.position = 'absolute';
    highlight.style.top = `${window.scrollY + rect.top}px`;
    highlight.style.left = `${window.scrollX + rect.left}px`;
    highlight.style.width = `${rect.width}px`;
    highlight.style.height = `${rect.height}px`;
    highlight.style.border = '2px solid #00a8ff';
    highlight.style.backgroundColor = 'rgba(0, 168, 255, 0.1)';
    highlight.style.zIndex = '9999';
    highlight.style.pointerEvents = 'none';
    highlight.style.boxSizing = 'border-box';
    highlight.style.transition = 'opacity 0.3s ease-in-out';
    
    // Add label with component name
    const component = this.getComponentById(id);
    if (component) {
      const label = document.createElement('div');
      label.textContent = component.name;
      label.style.position = 'absolute';
      label.style.top = '-24px';
      label.style.left = '0';
      label.style.backgroundColor = '#00a8ff';
      label.style.color = 'white';
      label.style.padding = '2px 6px';
      label.style.borderRadius = '4px';
      label.style.fontSize = '12px';
      label.style.fontFamily = 'sans-serif';
      highlight.appendChild(label);
    }
    
    // Add to the page
    document.body.appendChild(highlight);
    
    // Remove after a delay
    setTimeout(() => {
      highlight.style.opacity = '0';
      setTimeout(() => {
        highlight.remove();
      }, 300);
    }, 3000);
  }
  
  /**
   * Execute a method on a Livewire component
   */
  private executeComponentMethod(id: string, method: string, params: any[] = []): void {
    try {
      // For v2+
      if (window.Livewire && window.Livewire.components) {
        const component = window.Livewire.components.componentsById[id];
        if (component) {
          component.call(method, ...params);
          
          // Refresh component data after execution
          setTimeout(() => {
            this.refreshComponentData(id);
          }, 500);
          
          this.sendToExtension('method-executed', {
            id,
            method,
            params,
            status: 'success'
          });
          return;
        }
      }
      
      // For v1
      if (window.Livewire && window.Livewire.find) {
        const component = window.Livewire.find(id);
        if (component) {
          component.call(method, ...params);
          
          // Refresh component data after execution
          setTimeout(() => {
            this.refreshComponentData(id);
          }, 500);
          
          this.sendToExtension('method-executed', {
            id,
            method,
            params,
            status: 'success'
          });
          return;
        }
      }
      
      this.sendToExtension('method-executed', {
        id,
        method,
        params,
        status: 'error',
        error: 'Component not found'
      });
    } catch (error) {
      console.error('[Tapped] Error executing component method:', error);
      this.sendToExtension('method-executed', {
        id,
        method,
        params,
        status: 'error',
        error: error.message
      });
    }
  }
  
  /**
   * Update a property on a Livewire component
   */
  private updateComponentProperty(id: string, property: string, value: any): void {
    try {
      // For v2+
      if (window.Livewire && window.Livewire.components) {
        const component = window.Livewire.components.componentsById[id];
        if (component) {
          // Use Livewire's data update mechanism if available
          if (component.set) {
            component.set(property, value);
          } else {
            // Fallback to direct property modification
            this.setNestedProperty(component.data, property, value);
          }
          
          // Refresh component data after update
          setTimeout(() => {
            this.refreshComponentData(id);
          }, 500);
          
          this.sendToExtension('property-updated', {
            id,
            property,
            value,
            status: 'success'
          });
          return;
        }
      }
      
      // For v1
      if (window.Livewire && window.Livewire.find) {
        const component = window.Livewire.find(id);
        if (component) {
          component.set(property, value);
          
          // Refresh component data after update
          setTimeout(() => {
            this.refreshComponentData(id);
          }, 500);
          
          this.sendToExtension('property-updated', {
            id,
            property,
            value,
            status: 'success'
          });
          return;
        }
      }
      
      this.sendToExtension('property-updated', {
        id,
        property,
        value,
        status: 'error',
        error: 'Component not found'
      });
    } catch (error) {
      console.error('[Tapped] Error updating component property:', error);
      this.sendToExtension('property-updated', {
        id,
        property,
        value,
        status: 'error',
        error: error.message
      });
    }
  }
  
  /**
   * Set a nested property value
   */
  private setNestedProperty(obj: Record<string, any>, path: string, value: any): void {
    const parts = path.split('.');
    let current = obj;
    
    for (let i = 0; i < parts.length - 1; i++) {
      const part = parts[i];
      
      // Handle array indexes
      if (part.includes('[')) {
        const arrayName = part.substring(0, part.indexOf('['));
        const index = parseInt(part.substring(part.indexOf('[') + 1, part.indexOf(']')));
        
        if (!current[arrayName]) {
          current[arrayName] = [];
        }
        
        if (!current[arrayName][index]) {
          current[arrayName][index] = {};
        }
        
        current = current[arrayName][index];
      } else {
        if (!current[part]) {
          current[part] = {};
        }
        current = current[part];
      }
    }
    
    const lastPart = parts[parts.length - 1];
    
    // Handle array indexes in the last part
    if (lastPart.includes('[')) {
      const arrayName = lastPart.substring(0, lastPart.indexOf('['));
      const index = parseInt(lastPart.substring(lastPart.indexOf('[') + 1, lastPart.indexOf(']')));
      
      if (!current[arrayName]) {
        current[arrayName] = [];
      }
      
      current[arrayName][index] = value;
    } else {
      current[lastPart] = value;
    }
  }
  
  /**
   * Refresh component data
   */
  private refreshComponentData(id: string): void {
    // For v2+
    if (window.Livewire && window.Livewire.components) {
      const componentData = window.Livewire.components.componentsById[id];
      if (componentData) {
        const component = this.components.get(id);
        if (component) {
          component.properties = componentData.data || {};
          this.components.set(id, component);
          this.sendToExtension('component-updated', component);
        }
      }
    }
    
    // For v1
    if (window.Livewire && window.Livewire.find) {
      const componentData = window.Livewire.find(id);
      if (componentData) {
        const component = this.components.get(id);
        if (component) {
          component.properties = componentData.data || {};
          this.components.set(id, component);
          this.sendToExtension('component-updated', component);
        }
      }
    }
  }
  
  /**
   * Send a detection result to the extension
   */
  private sendDetectionResult(result: DetectionResult): void {
    this.sendToExtension('detection-result', result);
  }
  
  /**
   * Send a message to the extension
   */
  private sendToExtension(type: string, payload: any): void {
    window.postMessage({
      source: 'tapped-livewire-detector',
      type,
      payload
    }, '*');
  }
  
  /**
   * Clean up resources
   */
  public cleanup(): void {
    // Stop the mutation observer
    if (this.observer) {
      this.observer.disconnect();
      this.observer = null;
    }
    
    // Clear detection interval
    if (this.detectionInterval !== null) {
      clearInterval(this.detectionInterval);
      this.detectionInterval = null;
    }
    
    // Remove all event listeners
    EventUtils.cleanupEventListeners(window);
    
    this.initialized = false;
  }
}

// Initialize on content script load
const detector = new LivewireDetector();

// Handle cleanup when the page is unloaded
EventUtils.addEventListener(window, 'unload', () => {
  detector.cleanup();
});

// Add Livewire types for type checking
declare global {
  interface Window {
    Livewire?: {
      version?: string;
      hooks?: any;
      start?: () => void;
      components?: {
        componentsById: Record<string, any>;
      };
      find?: (id: string) => any;
    };
  }
}
