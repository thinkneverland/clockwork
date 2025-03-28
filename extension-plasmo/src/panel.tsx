import React, { useEffect, useState, useCallback, ChangeEvent } from "react";
import { createRoot } from "react-dom/client";

// Main Styles
import "./panel.css";

// Components
import ComponentList from "../components/ComponentList";
import ComponentView from "../components/ComponentView";
import EventList from "../components/EventList";
import QueryList from "../components/QueryList";
import RequestList from "../components/RequestList";
import ConnectionSettings from "../components/ConnectionSettings";

// Types
import {
  TabInfo,
  LivewireStatus,
  ServerConnection,
  ConnectionHealthMetrics,
  Event,
  Query,
  Request,
  Component,
  MessageHandlerMap,
  Message
} from "../lib/types/tappedTypes";

// Extend Window interface to include Tapped properties
declare global {
  interface Window {
    tappedDevToolsPort?: chrome.runtime.Port;
    tappedTabId?: number;
    setPort?: (port: chrome.runtime.Port, tabId: number) => void;
  }
}

// Define log entry interface
interface LogEntry {
  type: string;
  componentId?: string;
  method?: string;
  property?: string;
  oldValue?: any;
  newValue?: any;
  result?: any;
  error?: string;
  message?: string;
  timestamp: number;
}

// Main Panel Component
function Panel(): React.ReactElement {
  // State
  const [port, setPort] = useState<chrome.runtime.Port | null>(null);
  const [tabId, setTabId] = useState<number | null>(null);
  const [tabInfo, setTabInfo] = useState<TabInfo | null>(null);
  const [livewireStatus, setLivewireStatus] = useState<LivewireStatus>({
    hasLivewire: false
  });
  const [serverConnection, setServerConnection] = useState<ServerConnection>({
    connected: false
  });
  const [activeTab, setActiveTab] = useState<string>("components");
  const [components, setComponents] = useState<Component[]>([]);
  const [selectedComponent, setSelectedComponent] = useState<Component | null>(null);
  const [events, setEvents] = useState<Event[]>([]);
  const [queries, setQueries] = useState<Query[]>([]);
  const [requests, setRequests] = useState<Request[]>([]);
  const [logs, setLogs] = useState<LogEntry[]>([]);
  const [isConnecting, setIsConnecting] = useState<boolean>(false);
  const [serverUrl, setServerUrl] = useState<string>("ws://localhost:8080");
  const [darkMode, setDarkMode] = useState<boolean>(false);

  // Add a log entry
  const addLog = useCallback((log: LogEntry): void => {
    setLogs((prevLogs: LogEntry[]) => [log, ...prevLogs].slice(0, 100));
  }, []);

  // Handle tab changes
  const handleTabChange = useCallback((tab: string): void => {
    setActiveTab(tab);
  }, []);

  // Connect to the Tapped server
  const connectToServer = useCallback((): void => {
    if (!port || !tabId) return;
    
    setIsConnecting(true);
    port.postMessage({
      action: "connect-server",
      url: serverUrl
    });
  }, [port, tabId, serverUrl]);

  // Disconnect from the Tapped server
  const disconnectFromServer = useCallback((): void => {
    if (!port || !tabId) return;
    
    port.postMessage({
      action: "disconnect-server"
    });
  }, [port, tabId]);

  // Get a component's details
  const getComponent = useCallback((componentId: string): void => {
    if (!port || !tabId) return;
    
    port.postMessage({
      action: "get-component",
      componentId
    });
  }, [port, tabId]);

  // Update component data
  const updateComponent = useCallback((componentId: string, path: string, value: any): void => {
    if (!port || !tabId) return;
    
    port.postMessage({
      action: "update-component",
      componentId,
      path,
      value
    });
  }, [port, tabId]);

  // Refresh component list
  const refreshComponents = useCallback((): void => {
    if (!port || !tabId) return;
    
    port.postMessage({
      action: "refresh-components"
    });
  }, [port, tabId]);

  // Handle component selection
  const handleComponentSelect = useCallback((component: Component): void => {
    setSelectedComponent(component);
    getComponent(component.id);
  }, [getComponent]);

  // Handle server URL changes
  const handleServerUrlChange = useCallback((e: React.ChangeEvent<HTMLInputElement>): void => {
    setServerUrl(e.target.value);
  }, []);

  // Handle theme changes
  const handleThemeChange = useCallback((): void => {
    const newDarkMode = !darkMode;
    setDarkMode(newDarkMode);
    document.body.classList.toggle('dark-mode', newDarkMode);
    localStorage.setItem('tapped-dark-mode', newDarkMode ? 'true' : 'false');
  }, [darkMode]);

  // Message handler map
  const messageHandlers = useCallback((): MessageHandlerMap => ({
    "livewire-status": (msg: Message) => {
      setLivewireStatus(msg.data as LivewireStatus);
    },
    "server-connection": (msg: Message) => {
      setServerConnection(msg.data as ServerConnection);
      setIsConnecting(false);
    },
    "tab-info": (msg: Message) => {
      setTabInfo(msg.data as TabInfo);
    },
    "components": (msg: Message) => {
      setComponents(msg.data as Component[]);
    },
    "component": (msg: Message) => {
      const component = msg.data as Component;
      setSelectedComponent(component);
      
      // Update in the components list
      setComponents((prevComponents: Component[]) => {
        return prevComponents.map((c: Component) => (c.id === component.id ? component : c));
      });
    },
    "events": (msg: Message) => {
      setEvents(msg.data as Event[]);
    },
    "queries": (msg: Message) => {
      setQueries(msg.data as Query[]);
    },
    "requests": (msg: Message) => {
      setRequests(msg.data as Request[]);
    },
    "log": (msg: Message) => {
      const logEntry = msg.data as LogEntry;
      addLog(logEntry);
    },
    "component-updated": (msg: Message) => {
      const { componentId, property, value } = msg.data as {
        componentId: string;
        property: string;
        value: any;
      };
      
      // Add log entry
      addLog({
        type: "update",
        componentId,
        property,
        newValue: value,
        timestamp: Date.now()
      });
      
      // Request updated component
      getComponent(componentId);
    },
    "error": (msg: Message) => {
      addLog({
        type: "error",
        error: msg.data as string,
        timestamp: Date.now()
      });
    }
  }), [addLog, getComponent]);

  // Handle message from background script
  const handleMessage = useCallback((message: Message): void => {
    const handlers = messageHandlers();
    const handler = handlers[message.type];
    if (handler) {
      handler(message);
    } else {
      console.warn(`Unknown message type: ${message.type}`, message);
    }
  }, [messageHandlers]);

  // Initialize port and message handling
  const initializePort = useCallback((newPort: chrome.runtime.Port, newTabId: number): void => {
    setPort(newPort);
    setTabId(newTabId);

    // Setup message listener
    newPort.onMessage.addListener(handleMessage);
    newPort.onDisconnect.addListener(() => {
      setPort(null);
      setTabId(null);
      setLivewireStatus({ hasLivewire: false });
      setServerConnection({ connected: false });
    });

    // Request initial data
    newPort.postMessage({
      action: "get-livewire-status"
    });
  }, [handleMessage]);

  // Set up initialization when extension is loaded
  useEffect(() => {
    // Check for existing port (in case panel is reopened)
    if (window.tappedDevToolsPort && window.tappedTabId) {
      initializePort(window.tappedDevToolsPort, window.tappedTabId);
    }

    // Set global port setter function for Chrome extension API
    window.setPort = (newPort: chrome.runtime.Port, newTabId: number) => {
      initializePort(newPort, newTabId);
      // Store for future reference
      window.tappedDevToolsPort = newPort;
      window.tappedTabId = newTabId;
    };

    // Check theme preference
    const savedDarkMode = localStorage.getItem('tapped-dark-mode') === 'true';
    if (savedDarkMode) {
      setDarkMode(true);
      document.body.classList.add('dark-mode');
    }

    // Cleanup
    return () => {
      if (port) {
        port.onMessage.removeListener(handleMessage);
      }
      delete window.setPort;
    };
  }, [initializePort, handleMessage, port]);

  // Render the UI
  return (
    <div className={`panel ${darkMode ? 'dark' : 'light'}`}>
      <div className="header">
        <div className="tabs">
          <button 
            className={activeTab === "components" ? "active" : ""}
            onClick={() => handleTabChange("components")}
          >
            Components
          </button>
          <button 
            className={activeTab === "events" ? "active" : ""}
            onClick={() => handleTabChange("events")}
          >
            Events ({events.length})
          </button>
          <button 
            className={activeTab === "queries" ? "active" : ""}
            onClick={() => handleTabChange("queries")}
          >
            Queries ({queries.length})
          </button>
          <button 
            className={activeTab === "requests" ? "active" : ""}
            onClick={() => handleTabChange("requests")}
          >
            Requests ({requests.length})
          </button>
          <button 
            className={activeTab === "logs" ? "active" : ""}
            onClick={() => handleTabChange("logs")}
          >
            Logs ({logs.length})
          </button>
          <button 
            className={activeTab === "settings" ? "active" : ""}
            onClick={() => handleTabChange("settings")}
          >
            Settings
          </button>
        </div>
        <div className="actions">
          <button onClick={refreshComponents} title="Refresh Components">
            <span className="icon refresh">↻</span>
          </button>
          <button onClick={handleThemeChange} title="Toggle Dark Mode">
            <span className="icon theme">{darkMode ? "☀️" : "🌙"}</span>
          </button>
        </div>
      </div>

      <div className="content">
        {!livewireStatus.hasLivewire ? (
          <div className="no-livewire">
            <h2>Livewire Not Detected</h2>
            <p>Livewire was not detected on this page.</p>
          </div>
        ) : (
          <>
            {activeTab === "components" && (
              <div className="component-panel">
                <div className="component-list-container">
                  <ComponentList 
                    components={components} 
                    onSelectComponent={handleComponentSelect} 
                    selectedComponent={selectedComponent} 
                  />
                </div>
                <div className="component-detail-container">
                  {selectedComponent ? (
                    <ComponentView 
                      component={selectedComponent} 
                      onUpdateComponent={updateComponent} 
                    />
                  ) : (
                    <div className="no-selection">
                      <p>Select a component to view details</p>
                    </div>
                  )}
                </div>
              </div>
            )}

            {activeTab === "events" && (
              <EventList events={events} />
            )}

            {activeTab === "queries" && (
              <QueryList queries={queries} />
            )}

            {activeTab === "requests" && (
              <RequestList requests={requests} />
            )}

            {activeTab === "logs" && (
              <div className="logs-container">
                <table className="logs-table">
                  <thead>
                    <tr>
                      <th>Type</th>
                      <th>Component</th>
                      <th>Message</th>
                      <th>Time</th>
                    </tr>
                  </thead>
                  <tbody>
                    {logs.map((log, index) => (
                      <tr key={index} className={`log-entry ${log.type}`}>
                        <td>{log.type}</td>
                        <td>{log.componentId || '-'}</td>
                        <td>
                          {log.type === 'update' ? (
                            <>Updated {log.property}: {JSON.stringify(log.newValue)}</>
                          ) : log.type === 'error' ? (
                            <span className="error">{log.error}</span>
                          ) : (
                            log.message || '-'
                          )}
                        </td>
                        <td>{new Date(log.timestamp).toLocaleTimeString()}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {activeTab === "settings" && (
              <ConnectionSettings
                serverUrl={serverUrl}
                isConnected={serverConnection.connected}
                isConnecting={isConnecting}
                onServerUrlChange={handleServerUrlChange}
                onConnect={connectToServer}
                onDisconnect={disconnectFromServer}
              />
            )}
          </>
        )}
      </div>

      <div className="footer">
        {tabInfo && (
          <div className="tab-info">
            Tab: {tabInfo.tabId} - {tabInfo.title}
          </div>
        )}
        <div className="server-status">
          Server: {serverConnection.connected ? (
            <span className="connected">Connected</span>
          ) : (
            <span className="disconnected">Disconnected</span>
          )}
        </div>
      </div>
    </div>
  );
}

// Initialize panel
window.addEventListener("DOMContentLoaded", () => {
  const root = document.getElementById("root");
  if (root) {
    createRoot(root).render(<Panel />);
  }
});

export default Panel;
