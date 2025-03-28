import React, { useState, useEffect } from "react";
import { Storage } from "@plasmohq/storage";
import { ConnectionHealthMetrics } from "../lib/mcp/ConnectionHealthMonitor";

interface ConnectionSettingsProps {
  onConnect: (url: string) => void;
  onDisconnect: () => void;
  isConnected: boolean;
  connectionMetrics?: ConnectionHealthMetrics;
  connectionError?: string;
}

/**
 * Component for configuring WebSocket connection to the Tapped server
 */
export const ConnectionSettings: React.FC<ConnectionSettingsProps> = ({
  onConnect,
  onDisconnect,
  isConnected,
  connectionMetrics,
  connectionError
}) => {
  const [serverUrl, setServerUrl] = useState("ws://127.0.0.1:8080");
  const [savedConnections, setSavedConnections] = useState<string[]>([]);
  const [storage] = useState(new Storage());
  const [showAdvanced, setShowAdvanced] = useState(false);

  // Load saved connections from storage on component mount
  useEffect(() => {
    const loadSavedConnections = async () => {
      try {
        const saved = await storage.get("tapped-saved-connections");
        if (saved && Array.isArray(saved)) {
          setSavedConnections(saved);
          
          // If we have saved connections, use the first one as default
          if (saved.length > 0) {
            setServerUrl(saved[0]);
          }
        }
      } catch (error) {
        console.error("Error loading saved connections:", error);
      }
    };
    
    loadSavedConnections();
  }, []);

  // Handle connect button click
  const handleConnect = async () => {
    // Validate URL
    if (!serverUrl || !serverUrl.startsWith("ws")) {
      alert("Please enter a valid WebSocket URL (starting with ws:// or wss://)");
      return;
    }
    
    // Save to recent connections if not already saved
    if (!savedConnections.includes(serverUrl)) {
      const newSavedConnections = [serverUrl, ...savedConnections].slice(0, 5);
      setSavedConnections(newSavedConnections);
      
      try {
        await storage.set("tapped-saved-connections", newSavedConnections);
      } catch (error) {
        console.error("Error saving connections:", error);
      }
    }
    
    // Call the connect handler
    onConnect(serverUrl);
  };

  // Handle disconnect button click
  const handleDisconnect = () => {
    onDisconnect();
  };

  // Handle saved connection selection
  const handleSelectSavedConnection = (url: string) => {
    setServerUrl(url);
  };

  // Handle saved connection deletion
  const handleDeleteSavedConnection = async (e: React.MouseEvent, url: string) => {
    e.stopPropagation();
    
    const newSavedConnections = savedConnections.filter(conn => conn !== url);
    setSavedConnections(newSavedConnections);
    
    try {
      await storage.set("tapped-saved-connections", newSavedConnections);
    } catch (error) {
      console.error("Error saving connections:", error);
    }
  };

  // Format timestamp
  const formatTimestamp = (timestamp: number) => {
    return new Date(timestamp).toLocaleTimeString();
  };

  // Format connection uptime
  const formatUptime = (uptime: number) => {
    const seconds = Math.floor(uptime / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    
    if (hours > 0) {
      return `${hours}h ${minutes % 60}m ${seconds % 60}s`;
    } else if (minutes > 0) {
      return `${minutes}m ${seconds % 60}s`;
    } else {
      return `${seconds}s`;
    }
  };

  return (
    <div className="connection-settings">
      <h3>Server Connection</h3>
      
      <div className="connection-form">
        <input
          type="text"
          value={serverUrl}
          onChange={(e) => setServerUrl(e.target.value)}
          placeholder="WebSocket URL (ws://localhost:8080)"
          disabled={isConnected}
        />
        
        {isConnected ? (
          <button 
            onClick={handleDisconnect}
            className="disconnect-button"
          >
            Disconnect
          </button>
        ) : (
          <button 
            onClick={handleConnect}
            className="connect-button"
          >
            Connect
          </button>
        )}
      </div>
      
      {savedConnections.length > 0 && !isConnected && (
        <div className="saved-connections">
          <h4>Recent Connections</h4>
          <ul>
            {savedConnections.map((conn, index) => (
              <li key={index} onClick={() => handleSelectSavedConnection(conn)}>
                <span className="saved-connection-url">{conn}</span>
                <button 
                  className="delete-connection"
                  onClick={(e) => handleDeleteSavedConnection(e, conn)}
                  title="Remove this connection"
                >
                  ×
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}
      
      {connectionError && (
        <div className="connection-error">
          <strong>Error:</strong> {connectionError}
        </div>
      )}
      
      {isConnected && (
        <div className="connection-status connected">
          <strong>Connected to:</strong> {serverUrl}
          {connectionMetrics && (
            <button 
              className="toggle-advanced"
              onClick={() => setShowAdvanced(!showAdvanced)}
            >
              {showAdvanced ? "Hide Details" : "Show Details"}
            </button>
          )}
        </div>
      )}
      
      {isConnected && showAdvanced && connectionMetrics && (
        <div className="connection-metrics">
          <h4>Connection Metrics</h4>
          <div className="metrics-grid">
            <div className="metric">
              <span className="metric-label">Uptime:</span>
              <span className="metric-value">{formatUptime(connectionMetrics.uptime)}</span>
            </div>
            <div className="metric">
              <span className="metric-label">Connected since:</span>
              <span className="metric-value">{formatTimestamp(connectionMetrics.connectionStart)}</span>
            </div>
            <div className="metric">
              <span className="metric-label">Average Latency:</span>
              <span className="metric-value">{connectionMetrics.averageLatency.toFixed(2)}ms</span>
            </div>
            <div className="metric">
              <span className="metric-label">Packet Loss:</span>
              <span className="metric-value">{(connectionMetrics.packetLoss * 100).toFixed(1)}%</span>
            </div>
            <div className="metric">
              <span className="metric-label">Reconnects:</span>
              <span className="metric-value">{connectionMetrics.reconnects}</span>
            </div>
            <div className="metric">
              <span className="metric-label">Last Ping:</span>
              <span className="metric-value">{formatTimestamp(connectionMetrics.lastPingTime)}</span>
            </div>
          </div>
          
          {connectionMetrics.latency.length > 0 && (
            <div className="latency-history">
              <h4>Latency History (ms)</h4>
              <div className="latency-chart">
                {connectionMetrics.latency.map((latency, index) => (
                  <div 
                    key={index} 
                    className="latency-bar"
                    style={{ 
                      height: `${Math.min(100, latency / 5)}%`,
                      backgroundColor: latency > 500 ? '#e84118' : latency > 200 ? '#e1b12c' : '#4cd137'
                    }}
                    title={`${latency.toFixed(2)}ms`}
                  />
                ))}
              </div>
            </div>
          )}
        </div>
      )}
      
      <div className="connection-help">
        <h4>Help</h4>
        <p>
          To use Tapped, you need to connect to a Tapped WebSocket server.
          Make sure your Laravel application has the Tapped package installed and configured.
        </p>
        <p>
          <strong>Default URL:</strong> ws://127.0.0.1:8080
        </p>
        <p>
          <strong>Common issues:</strong>
        </p>
        <ul>
          <li>Ensure Tapped server is running in your Laravel app</li>
          <li>Check firewall settings if connecting to a remote server</li>
          <li>Use wss:// for secure connections (HTTPS sites)</li>
        </ul>
      </div>
    </div>
  );
};

export default ConnectionSettings;
