import React, { useState, useEffect } from "react";
import { JsonProcessor } from "../lib/utils/DataProcessor";
import { throttle } from "../lib/utils/StateOptimizer";

interface Request {
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

interface RequestListProps {
  requests: Request[];
  onClear?: () => void;
  onFilterByComponent?: (componentId: string) => void;
}

/**
 * Component for displaying HTTP requests with special handling for Livewire requests
 */
export const RequestList: React.FC<RequestListProps> = ({
  requests,
  onClear,
  onFilterByComponent
}) => {
  const [searchQuery, setSearchQuery] = useState("");
  const [filteredRequests, setFilteredRequests] = useState<Request[]>(requests);
  const [expandedRequests, setExpandedRequests] = useState<Record<string, string>>({});
  const [isRegexSearch, setIsRegexSearch] = useState(false);
  const [showLivewireOnly, setShowLivewireOnly] = useState(false);

  // Filter requests when search query or requests change
  useEffect(() => {
    let filtered = [...requests];
    
    // Apply Livewire filter
    if (showLivewireOnly) {
      filtered = filtered.filter(request => request.isLivewire);
    }
    
    // Apply search filter
    if (searchQuery) {
      try {
        if (isRegexSearch) {
          // Use regex for filtering
          const regex = new RegExp(searchQuery, "i");
          filtered = filtered.filter(request => 
            regex.test(request.url) || 
            regex.test(request.method) ||
            (request.livewireComponent && regex.test(request.livewireComponent)) ||
            (request.livewireMethod && regex.test(request.livewireMethod))
          );
        } else {
          // Use simple string includes for filtering
          const query = searchQuery.toLowerCase();
          filtered = filtered.filter(request => 
            request.url.toLowerCase().includes(query) ||
            request.method.toLowerCase().includes(query) ||
            (request.livewireComponent && request.livewireComponent.toLowerCase().includes(query)) ||
            (request.livewireMethod && request.livewireMethod.toLowerCase().includes(query))
          );
        }
      } catch (error) {
        console.error("Search filter error:", error);
      }
    }
    
    setFilteredRequests(filtered);
  }, [searchQuery, requests, isRegexSearch, showLivewireOnly]);

  // Throttled search handler to prevent excessive filtering on rapid typing
  const handleSearchChange = throttle((e: React.ChangeEvent<HTMLInputElement>) => {
    setSearchQuery(e.target.value);
  }, 300);

  // Toggle request expanded state with tab selection
  const toggleRequestExpanded = (requestId: string, tab: string = "payload") => {
    setExpandedRequests(prev => {
      const newExpanded = { ...prev };
      
      if (prev[requestId] === tab) {
        // If clicking the same tab, collapse
        delete newExpanded[requestId];
      } else {
        // Otherwise, expand with the selected tab
        newExpanded[requestId] = tab;
      }
      
      return newExpanded;
    });
  };

  // Handle filter by component
  const handleFilterByComponent = (componentId: string) => {
    if (onFilterByComponent) {
      onFilterByComponent(componentId);
    }
  };

  // Format timestamp
  const formatTimestamp = (timestamp: number) => {
    const date = new Date(timestamp);
    return date.toLocaleTimeString() + "." + date.getMilliseconds().toString().padStart(3, '0');
  };

  // Get method class for styling
  const getMethodClass = (method: string) => {
    const methodLower = method.toLowerCase();
    
    if (methodLower === "get") return "method-get";
    if (methodLower === "post") return "method-post";
    if (methodLower === "put") return "method-put";
    if (methodLower === "delete") return "method-delete";
    
    return "";
  };

  // Get status class for styling
  const getStatusClass = (status: number) => {
    if (status >= 200 && status < 300) return "status-2xx";
    if (status >= 300 && status < 400) return "status-3xx";
    if (status >= 400 && status < 500) return "status-4xx";
    if (status >= 500) return "status-5xx";
    
    return "";
  };

  // Format request time
  const formatRequestTime = (time: number) => {
    if (time < 1000) {
      return `${time.toFixed(0)}ms`;
    } else {
      return `${(time / 1000).toFixed(2)}s`;
    }
  };

  return (
    <div className="requests-container">
      <div className="requests-header">
        <div className="requests-title">
          <h3>Requests</h3>
          <div className="requests-stats">
            <span className="total-requests">{requests.length} total</span>
            {requests.filter(r => r.isLivewire).length > 0 && (
              <span className="livewire-requests" title="Livewire AJAX requests">
                {requests.filter(r => r.isLivewire).length} Livewire
              </span>
            )}
          </div>
        </div>
        <div className="requests-actions">
          {onClear && (
            <button 
              className="clear-button" 
              onClick={onClear}
              title="Clear all requests"
            >
              Clear
            </button>
          )}
        </div>
      </div>

      <div className="requests-filters">
        <div className="search-container">
          <input
            type="text"
            placeholder={isRegexSearch ? "Search with regex..." : "Search requests..."}
            onChange={handleSearchChange}
            className="search-input"
          />
          <div className="filter-options">
            <label className="regex-toggle">
              <input 
                type="checkbox" 
                checked={isRegexSearch} 
                onChange={() => setIsRegexSearch(!isRegexSearch)} 
              />
              Regex
            </label>
            <label className="livewire-toggle">
              <input 
                type="checkbox" 
                checked={showLivewireOnly} 
                onChange={() => setShowLivewireOnly(!showLivewireOnly)} 
              />
              Livewire only
            </label>
          </div>
        </div>
      </div>

      {filteredRequests.length === 0 ? (
        <div className="no-requests">
          {requests.length === 0 
            ? "No HTTP requests have been captured yet" 
            : "No requests match your filters"}
        </div>
      ) : (
        <div className="request-list">
          {filteredRequests.map(request => (
            <div 
              key={request.id} 
              className={`request-item ${request.isLivewire ? 'livewire-request' : ''}`}
            >
              <div className="request-header" onClick={() => toggleRequestExpanded(request.id)}>
                <div className="request-info">
                  <span className={`request-method ${getMethodClass(request.method)}`}>
                    {request.method}
                  </span>
                  <span className="request-url" title={request.url}>
                    {request.url}
                  </span>
                </div>
                <div className="request-meta">
                  <span className={`request-status ${getStatusClass(request.status)}`}>
                    {request.status}
                  </span>
                  <span className="request-time" title={`Request time: ${request.time}ms`}>
                    {formatRequestTime(request.time)}
                  </span>
                  <span className="request-timestamp">{formatTimestamp(request.timestamp)}</span>
                </div>
              </div>
              
              {request.isLivewire && (
                <div className="request-livewire-info">
                  <strong>Livewire:</strong> {request.livewireComponent} → {request.livewireMethod}
                  {request.componentId && onFilterByComponent && (
                    <button 
                      className="filter-component-button"
                      onClick={() => handleFilterByComponent(request.componentId)}
                      title="Filter by this component"
                    >
                      Filter
                    </button>
                  )}
                </div>
              )}
              
              {expandedRequests[request.id] && (
                <>
                  <div className="request-tabs">
                    <button 
                      className={expandedRequests[request.id] === "payload" ? "active" : ""}
                      onClick={(e) => {
                        e.stopPropagation();
                        toggleRequestExpanded(request.id, "payload");
                      }}
                    >
                      Payload
                    </button>
                    <button 
                      className={expandedRequests[request.id] === "response" ? "active" : ""}
                      onClick={(e) => {
                        e.stopPropagation();
                        toggleRequestExpanded(request.id, "response");
                      }}
                    >
                      Response
                    </button>
                    <button 
                      className={expandedRequests[request.id] === "headers" ? "active" : ""}
                      onClick={(e) => {
                        e.stopPropagation();
                        toggleRequestExpanded(request.id, "headers");
                      }}
                    >
                      Headers
                    </button>
                  </div>
                  
                  <div className="request-details">
                    {expandedRequests[request.id] === "payload" && (
                      <>
                        <strong>Request Payload:</strong>
                        <pre>{JsonProcessor.stringify(request.payload, { pretty: true })}</pre>
                      </>
                    )}
                    
                    {expandedRequests[request.id] === "response" && (
                      <>
                        <strong>Response:</strong>
                        <pre>{JsonProcessor.stringify(request.response, { pretty: true })}</pre>
                      </>
                    )}
                    
                    {expandedRequests[request.id] === "headers" && (
                      <>
                        <strong>Headers:</strong>
                        <pre>{JsonProcessor.stringify(request.headers, { pretty: true })}</pre>
                      </>
                    )}
                  </div>
                </>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default RequestList;
