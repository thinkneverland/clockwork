import React, { useState, useEffect } from "react";
import { JsonProcessor } from "../lib/utils/DataProcessor";
import { throttle } from "../lib/utils/StateOptimizer";

interface Event {
  id: string;
  name: string;
  time: number;
  timestamp: string;
  payload: any;
  componentId?: string;
  componentName?: string;
}

interface EventListProps {
  events: Event[];
  onClear?: () => void;
  onFilterByComponent?: (componentId: string) => void;
}

/**
 * Component that displays Livewire events
 */
export const EventList: React.FC<EventListProps> = ({ 
  events, 
  onClear,
  onFilterByComponent
}) => {
  const [searchQuery, setSearchQuery] = useState("");
  const [filteredEvents, setFilteredEvents] = useState<Event[]>(events);
  const [expandedEvents, setExpandedEvents] = useState<Set<string>>(new Set());
  const [isRegexSearch, setIsRegexSearch] = useState(false);

  // Filter events when search query or events change
  useEffect(() => {
    if (!searchQuery) {
      setFilteredEvents(events);
      return;
    }
    
    try {
      let filtered: Event[];
      
      if (isRegexSearch) {
        // Use regex for filtering
        const regex = new RegExp(searchQuery, "i");
        filtered = events.filter(event => 
          regex.test(event.name) || 
          regex.test(event.componentName || "") ||
          regex.test(JsonProcessor.stringify(event.payload))
        );
      } else {
        // Use simple string includes for filtering
        const query = searchQuery.toLowerCase();
        filtered = events.filter(event => 
          event.name.toLowerCase().includes(query) ||
          (event.componentName && event.componentName.toLowerCase().includes(query)) ||
          JsonProcessor.stringify(event.payload).toLowerCase().includes(query)
        );
      }
      
      setFilteredEvents(filtered);
    } catch (error) {
      // If regex is invalid, fall back to showing all events
      console.error("Search filter error:", error);
      setFilteredEvents(events);
    }
  }, [searchQuery, events, isRegexSearch]);

  // Throttled search handler to prevent excessive filtering on rapid typing
  const handleSearchChange = throttle((e: React.ChangeEvent<HTMLInputElement>) => {
    setSearchQuery(e.target.value);
  }, 300);

  // Toggle event expanded state
  const toggleEventExpanded = (eventId: string) => {
    const newExpandedEvents = new Set(expandedEvents);
    
    if (newExpandedEvents.has(eventId)) {
      newExpandedEvents.delete(eventId);
    } else {
      newExpandedEvents.add(eventId);
    }
    
    setExpandedEvents(newExpandedEvents);
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

  return (
    <div className="events-container">
      <div className="events-header">
        <h3>Events</h3>
        <div className="events-actions">
          {onClear && (
            <button 
              className="clear-button" 
              onClick={onClear}
              title="Clear all events"
            >
              Clear
            </button>
          )}
        </div>
      </div>

      <div className="events-filters">
        <div className="search-container">
          <input
            type="text"
            placeholder={isRegexSearch ? "Search with regex..." : "Search events..."}
            onChange={handleSearchChange}
            className="search-input"
          />
          <label className="regex-toggle">
            <input 
              type="checkbox" 
              checked={isRegexSearch} 
              onChange={() => setIsRegexSearch(!isRegexSearch)} 
            />
            Regex
          </label>
        </div>
      </div>

      {filteredEvents.length === 0 ? (
        <div className="no-events">
          {events.length === 0 
            ? "No events have been captured yet" 
            : "No events match your search"}
        </div>
      ) : (
        <div className="event-list">
          {filteredEvents.map(event => (
            <div key={event.id} className="event-item">
              <div 
                className="event-header" 
                onClick={() => toggleEventExpanded(event.id)}
              >
                <div className="event-info">
                  <span className="event-name">{event.name}</span>
                  {event.componentName && (
                    <span 
                      className="event-component" 
                      onClick={(e) => {
                        e.stopPropagation();
                        handleFilterByComponent(event.componentId);
                      }}
                      title="Filter by this component"
                    >
                      {event.componentName}
                    </span>
                  )}
                </div>
                <span className="event-time">{formatTimestamp(event.time)}</span>
              </div>
              
              {expandedEvents.has(event.id) && (
                <div className="event-details">
                  <div className="event-payload">
                    <strong>Payload:</strong>
                    <pre>{JsonProcessor.stringify(event.payload, { pretty: true })}</pre>
                  </div>
                  
                  {event.componentId && (
                    <div className="event-component-id">
                      <strong>Component ID:</strong> {event.componentId}
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

export default EventList;
