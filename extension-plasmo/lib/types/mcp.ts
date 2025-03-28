/**
 * MCP Protocol type definitions
 */

export interface McpMessage {
  type: string;
  payload?: any;
  meta?: {
    timestamp?: number;
    tabId?: number;
    requestId?: string;
    [key: string]: any;
  };
}

export interface McpConnectionOptions {
  url: string;
  autoReconnect?: boolean;
  reconnectInterval?: number;
  maxReconnectAttempts?: number;
  healthCheckInterval?: number;
  connectionTimeout?: number;
  debug?: boolean;
}

export interface McpComponent {
  id: string;
  name: string;
  properties: Record<string, any>;
  children?: McpComponent[];
  events?: McpEvent[];
  methods?: McpMethod[];
  meta?: Record<string, any>;
}

export interface McpEvent {
  id: string;
  name: string;
  params?: any[];
  timestamp: number;
  source?: string;
  meta?: Record<string, any>;
}

export interface McpMethod {
  name: string;
  params?: {
    name: string;
    type: string;
    required?: boolean;
    default?: any;
  }[];
}

export interface McpQuery {
  id: string;
  sql: string;
  bindings?: any[];
  time: number;
  connection?: string;
  isSlowQuery?: boolean;
  hasN1Problem?: boolean;
  backtrace?: string[];
  timestamp: number;
  meta?: Record<string, any>;
}

export interface McpHttpRequest {
  id: string;
  url: string;
  method: string;
  statusCode?: number;
  duration?: number;
  headers?: Record<string, string>;
  requestData?: any;
  responseData?: any;
  isLivewireRequest?: boolean;
  livewireComponent?: string;
  livewireEvent?: string;
  timestamp: number;
  meta?: Record<string, any>;
}

export interface McpSnapshot {
  id: string;
  timestamp: number;
  components: McpComponent[];
  events: McpEvent[];
  queries: McpQuery[];
  requests: McpHttpRequest[];
  meta?: Record<string, any>;
}

export interface ConnectionStatus {
  connected: boolean;
  connecting: boolean;
  lastConnected?: number;
  reconnectAttempts: number;
  latency?: number;
  error?: string;
  healthStatus: 'healthy' | 'degraded' | 'unhealthy';
}

export type McpEventListener = (data: any) => void;

export interface McpClientInterface {
  connect(): Promise<void>;
  disconnect(): Promise<void>;
  send(message: McpMessage): Promise<void>;
  on(event: string, callback: McpEventListener): void;
  off(event: string, callback: McpEventListener): void;
  getStatus(): ConnectionStatus;
}
