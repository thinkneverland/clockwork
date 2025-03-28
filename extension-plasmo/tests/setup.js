/**
 * Jest test setup file
 * 
 * Sets up the testing environment and mocks browser APIs
 */

// Mock browser extension APIs
global.chrome = {
  runtime: {
    connect: jest.fn(),
    sendMessage: jest.fn(),
    onMessage: {
      addListener: jest.fn(),
      removeListener: jest.fn()
    },
    onConnect: {
      addListener: jest.fn(),
      removeListener: jest.fn()
    },
    lastError: null
  },
  tabs: {
    query: jest.fn(),
    sendMessage: jest.fn(),
    connect: jest.fn(),
    get: jest.fn(),
    executeScript: jest.fn()
  },
  storage: {
    local: {
      get: jest.fn(),
      set: jest.fn(),
      remove: jest.fn()
    },
    sync: {
      get: jest.fn(),
      set: jest.fn(),
      remove: jest.fn()
    }
  },
  devtools: {
    panels: {
      create: jest.fn()
    }
  },
  scripting: {
    executeScript: jest.fn()
  },
  permissions: {
    contains: jest.fn()
  },
  action: {}
};

// Mock WebSocket
global.WebSocket = class MockWebSocket {
  constructor(url) {
    this.url = url;
    this.readyState = 0; // CONNECTING
    this.CONNECTING = 0;
    this.OPEN = 1;
    this.CLOSING = 2;
    this.CLOSED = 3;
    
    // Automatically "connect" after a delay
    setTimeout(() => {
      this.readyState = 1; // OPEN
      if (this.onopen) {
        this.onopen({});
      }
    }, 10);
  }
  
  send(data) {
    // Mock implementation
  }
  
  close() {
    this.readyState = 3; // CLOSED
    if (this.onclose) {
      this.onclose({});
    }
  }
};

// Mock browser object for Firefox
global.browser = { ...global.chrome };

// Add testing library matchers
expect.extend({
  toBeConnected(socket) {
    const pass = socket.readyState === 1; // WebSocket.OPEN
    return {
      pass,
      message: () => `expected WebSocket ${pass ? 'not ' : ''}to be connected`
    };
  }
});
