// Types for Tapped extension

export interface TabInfo {
  tabId: number;
  url: string;
  title: string;
}

export interface LivewireStatus {
  hasLivewire: boolean;
  livewireVersion?: string;
  components?: Component[];
}

export interface ServerConnection {
  connected: boolean;
  url?: string;
  error?: string;
  metrics?: ConnectionHealthMetrics;
}

export interface ConnectionHealthMetrics {
  latency?: number;
  lastPingAt?: number;
  lastPongAt?: number;
  reconnectCount?: number;
  disconnects?: number;
  messagesReceived?: number;
  messagesSent?: number;
}

export interface Event {
  id: string;
  name: string;
  time: number;
  timestamp: string;
  payload: any;
  componentId?: string;
  componentName?: string;
}

export interface Query {
  id: string;
  sql: string;
  bindings: any[];
  time: number;
  timestamp: number;
  connection: string;
  isN1Problem: boolean;
  isSlowQuery: boolean;
  componentId?: string;
  componentName?: string;
}

export interface Request {
  id: string;
  method: string;
  url: string;
  status: number;
  time: number;
  timestamp: number;
  headers: Record<string, string>;
  payload: any;
  response: any;
  isLivewire: boolean;
  livewireComponent?: string;
  livewireMethod?: string;
  componentId?: string;
}

export interface Component {
  id: string;
  name: string;
  fingerprint?: string;
  data: Record<string, any>;
  events?: Event[];
  queries?: Query[];
  requests?: Request[];
  children?: Component[];
  parent?: string;
}

export type MessageHandlerMap = {
  [key: string]: (msg: any) => void;
};

export interface Message {
  action: string;
  [key: string]: any;
}

// Chrome namespace types (minimal required definitions)
declare global {
  interface Window {
    tappedDevToolsPort?: chrome.runtime.Port;
    tappedTabId?: number;
    setPort?: (port: chrome.runtime.Port, tabId: number) => void;
  }
  
  namespace chrome {
    namespace runtime {
      interface Port {
        name?: string;
        disconnect: () => void;
        postMessage: (message: any) => void;
        onMessage: {
          addListener: (callback: (message: any) => void) => void;
          removeListener: (callback: (message: any) => void) => void;
        };
        onDisconnect: {
          addListener: (callback: () => void) => void;
          removeListener: (callback: () => void) => void;
        };
      }
      
      function connect(connectInfo?: { name?: string }): Port;
    }

    namespace tabs {
      interface Tab {
        id: number;
        url?: string;
        title?: string;
      }
      
      function query(queryInfo: { active: boolean, currentWindow: boolean }, callback: (tabs: Tab[]) => void): void;
      function sendMessage(tabId: number, message: any, callback?: (response: any) => void): void;
    }
  }
}
