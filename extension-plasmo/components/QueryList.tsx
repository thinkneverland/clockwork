import React, { useState, useEffect } from "react";
import { throttle } from "../lib/utils/StateOptimizer";

interface Query {
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

interface QueryListProps {
  queries: Query[];
  onClear?: () => void;
  onFilterByComponent?: (componentId: string) => void;
  slowQueryThreshold?: number; // in milliseconds
}

/**
 * Component for displaying database queries with N+1 detection
 */
export const QueryList: React.FC<QueryListProps> = ({
  queries,
  onClear,
  onFilterByComponent,
  slowQueryThreshold = 100 // Default threshold: 100ms
}) => {
  const [searchQuery, setSearchQuery] = useState("");
  const [filteredQueries, setFilteredQueries] = useState<Query[]>(queries);
  const [expandedQueries, setExpandedQueries] = useState<Set<string>>(new Set());
  const [isRegexSearch, setIsRegexSearch] = useState(false);
  const [showN1Only, setShowN1Only] = useState(false);
  const [showSlowOnly, setShowSlowOnly] = useState(false);

  // Filter queries when search query or queries change
  useEffect(() => {
    let filtered = [...queries];
    
    // Apply N+1 filter
    if (showN1Only) {
      filtered = filtered.filter(query => query.isN1Problem);
    }
    
    // Apply slow query filter
    if (showSlowOnly) {
      filtered = filtered.filter(query => query.isSlowQuery || query.time > slowQueryThreshold);
    }
    
    // Apply search filter
    if (searchQuery) {
      try {
        if (isRegexSearch) {
          // Use regex for filtering
          const regex = new RegExp(searchQuery, "i");
          filtered = filtered.filter(query => 
            regex.test(query.sql) || 
            regex.test(query.connection) ||
            (query.componentName && regex.test(query.componentName))
          );
        } else {
          // Use simple string includes for filtering
          const query = searchQuery.toLowerCase();
          filtered = filtered.filter(q => 
            q.sql.toLowerCase().includes(query) ||
            q.connection.toLowerCase().includes(query) ||
            (q.componentName && q.componentName.toLowerCase().includes(query))
          );
        }
      } catch (error) {
        console.error("Search filter error:", error);
      }
    }
    
    setFilteredQueries(filtered);
  }, [searchQuery, queries, isRegexSearch, showN1Only, showSlowOnly, slowQueryThreshold]);

  // Throttled search handler to prevent excessive filtering on rapid typing
  const handleSearchChange = throttle((e: React.ChangeEvent<HTMLInputElement>) => {
    setSearchQuery(e.target.value);
  }, 300);

  // Toggle query expanded state
  const toggleQueryExpanded = (queryId: string) => {
    const newExpandedQueries = new Set(expandedQueries);
    
    if (newExpandedQueries.has(queryId)) {
      newExpandedQueries.delete(queryId);
    } else {
      newExpandedQueries.add(queryId);
    }
    
    setExpandedQueries(newExpandedQueries);
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

  // Format query time
  const formatQueryTime = (time: number) => {
    if (time < 1) {
      return `${(time * 1000).toFixed(2)}μs`;
    } else if (time < 1000) {
      return `${time.toFixed(2)}ms`;
    } else {
      return `${(time / 1000).toFixed(2)}s`;
    }
  };

  // Get N+1 and slow query statistics
  const n1Queries = queries.filter(q => q.isN1Problem).length;
  const slowQueries = queries.filter(q => q.isSlowQuery || q.time > slowQueryThreshold).length;

  return (
    <div className="queries-container">
      <div className="queries-header">
        <div className="queries-title">
          <h3>Queries</h3>
          <div className="queries-stats">
            <span className="total-queries">{queries.length} total</span>
            {n1Queries > 0 && (
              <span className="n1-queries" title="Potential N+1 problems">
                {n1Queries} N+1
              </span>
            )}
            {slowQueries > 0 && (
              <span className="slow-queries" title={`Queries slower than ${slowQueryThreshold}ms`}>
                {slowQueries} slow
              </span>
            )}
          </div>
        </div>
        <div className="queries-actions">
          {onClear && (
            <button 
              className="clear-button" 
              onClick={onClear}
              title="Clear all queries"
            >
              Clear
            </button>
          )}
        </div>
      </div>

      <div className="queries-filters">
        <div className="search-container">
          <input
            type="text"
            placeholder={isRegexSearch ? "Search with regex..." : "Search queries..."}
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
            <label className="n1-toggle">
              <input 
                type="checkbox" 
                checked={showN1Only} 
                onChange={() => setShowN1Only(!showN1Only)} 
              />
              N+1 only
            </label>
            <label className="slow-toggle">
              <input 
                type="checkbox" 
                checked={showSlowOnly} 
                onChange={() => setShowSlowOnly(!showSlowOnly)} 
              />
              Slow only
            </label>
          </div>
        </div>
      </div>

      {filteredQueries.length === 0 ? (
        <div className="no-queries">
          {queries.length === 0 
            ? "No database queries have been captured yet" 
            : "No queries match your filters"}
        </div>
      ) : (
        <div className="query-list">
          {filteredQueries.map(query => (
            <div 
              key={query.id} 
              className={`query-item ${query.isN1Problem ? 'n1-problem' : ''} ${query.isSlowQuery || query.time > slowQueryThreshold ? 'slow-query' : ''}`}
            >
              <div 
                className="query-header" 
                onClick={() => toggleQueryExpanded(query.id)}
              >
                <div className="query-info">
                  <span className="query-time" title={`Query execution time: ${query.time}ms`}>
                    {formatQueryTime(query.time)}
                  </span>
                  {query.componentName && (
                    <span 
                      className="query-component" 
                      onClick={(e) => {
                        e.stopPropagation();
                        handleFilterByComponent(query.componentId);
                      }}
                      title="Filter by this component"
                    >
                      {query.componentName}
                    </span>
                  )}
                  <span className="query-connection">{query.connection}</span>
                </div>
                <div className="query-meta">
                  {query.isN1Problem && (
                    <span className="query-n1-badge" title="Potential N+1 query problem">N+1</span>
                  )}
                  {(query.isSlowQuery || query.time > slowQueryThreshold) && (
                    <span className="query-slow-badge" title="Slow query">Slow</span>
                  )}
                  <span className="query-timestamp">{formatTimestamp(query.timestamp)}</span>
                </div>
              </div>
              
              {expandedQueries.has(query.id) && (
                <div className="query-details">
                  <div className="query-sql">
                    <strong>SQL:</strong>
                    <pre>{query.sql}</pre>
                  </div>
                  
                  {query.bindings && query.bindings.length > 0 && (
                    <div className="query-bindings">
                      <strong>Bindings:</strong>
                      <pre>{JSON.stringify(query.bindings, null, 2)}</pre>
                    </div>
                  )}
                  
                  {query.isN1Problem && (
                    <div className="query-n1-explanation">
                      <strong>N+1 Problem:</strong>
                      <p>
                        This query appears to be executed multiple times in a loop. 
                        Consider using eager loading or joining related tables to optimize performance.
                      </p>
                    </div>
                  )}
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default QueryList;
