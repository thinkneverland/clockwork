import React, { useState, useEffect } from "react";
import { JsonProcessor } from "../lib/utils/DataProcessor";
import { debounce } from "../lib/utils/StateOptimizer";

interface ComponentViewProps {
  component: any;
  onEdit?: (path: string, value: any) => void;
}

/**
 * Component for viewing and editing Livewire component data
 */
export const ComponentView: React.FC<ComponentViewProps> = ({ component, onEdit }) => {
  const [activeTab, setActiveTab] = useState("properties");
  const [selectedComponent, setSelectedComponent] = useState(null);

  if (!component) {
    return (
      <div className="no-selection">
        Select a component to view its details
      </div>
    );
  }

  /**
   * Render the component properties
   */
  const renderProperties = () => {
    if (!component || !component.data) {
      return <div className="empty-message">No properties available</div>;
    }

    const properties = Object.entries(component.data);

    if (properties.length === 0) {
      return <div className="empty-message">No properties available</div>;
    }

    return (
      <div className="component-properties">
        <h4>Component Properties</h4>
        <div className="property-list">
          {properties.map(([key, value]) => (
            <div key={key} className="property-item">
              <div className="property-name">{key}</div>
              <div className="property-value">
                <pre>{renderPropertyValue(value)}</pre>
                {onEdit && (
                  <button 
                    className="edit-property" 
                    onClick={() => handleEditClick(key, value)}
                  >
                    Edit
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  };

  /**
   * Render the property value based on its type
   */
  const renderPropertyValue = (value: any) => {
    if (value === null) {
      return "null";
    }

    if (value === undefined) {
      return "undefined";
    }

    if (typeof value === "object") {
      return JsonProcessor.stringify(value, { pretty: true });
    }

    return String(value);
  };

  /**
   * Handle edit button click
   */
  const handleEditClick = (key: string, value: any) => {
    if (!onEdit) return;

    // For simple implementation, we'll use prompt
    // In a real implementation, this would be a modal with proper editor
    const newValue = prompt(`Edit ${key}:`, typeof value === "object" 
      ? JsonProcessor.stringify(value, { pretty: true })
      : String(value)
    );

    if (newValue === null) return; // User canceled

    try {
      // Parse the value appropriately based on original type
      let parsedValue;
      
      if (typeof value === "number") {
        parsedValue = Number(newValue);
      } else if (typeof value === "boolean") {
        parsedValue = newValue.toLowerCase() === "true";
      } else if (typeof value === "object") {
        parsedValue = JsonProcessor.parse(newValue);
      } else {
        parsedValue = newValue;
      }

      onEdit(key, parsedValue);
    } catch (error) {
      console.error("Error parsing edited value:", error);
      alert("Invalid value. Please check the format and try again.");
    }
  };

  /**
   * Render the methods tab
   */
  const renderMethods = () => {
    if (!component || !component.methods || component.methods.length === 0) {
      return <div className="empty-message">No methods available</div>;
    }

    return (
      <div className="component-methods">
        <h4>Available Methods</h4>
        <div className="method-list">
          {component.methods.map((method: string) => (
            <div key={method} className="method-item">
              <div className="method-name">{method}</div>
              <button 
                className="execute-method"
                onClick={() => handleExecuteMethod(method)}
              >
                Execute
              </button>
            </div>
          ))}
        </div>
      </div>
    );
  };

  /**
   * Handle method execution
   */
  const handleExecuteMethod = (method: string) => {
    // This would be implemented to call the Livewire method
    console.log(`Executing method: ${method}`);
    // In a real implementation, this would call the backend to execute the method
  };

  /**
   * Render the events tab
   */
  const renderEvents = () => {
    if (!component || !component.events || component.events.length === 0) {
      return <div className="empty-message">No events recorded</div>;
    }

    return (
      <div className="component-events">
        <h4>Emitted Events</h4>
        <div className="event-list">
          {component.events.map((event: any, index: number) => (
            <div key={index} className="event-item">
              <div className="event-header">
                <span className="event-name">{event.name}</span>
                <span className="event-time">{new Date(event.time).toLocaleTimeString()}</span>
              </div>
              <div className="event-details">
                <div className="event-payload">
                  <strong>Payload:</strong>
                  <pre>{JsonProcessor.stringify(event.payload, { pretty: true })}</pre>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  };

  return (
    <div className="component-details">
      <div className="component-header">
        <h3>{component.name}</h3>
        <span className="component-id">{component.id}</span>
      </div>

      <div className="component-tabs">
        <button 
          className={activeTab === "properties" ? "active" : ""}
          onClick={() => setActiveTab("properties")}
        >
          Properties
        </button>
        <button 
          className={activeTab === "methods" ? "active" : ""}
          onClick={() => setActiveTab("methods")}
        >
          Methods
        </button>
        <button 
          className={activeTab === "events" ? "active" : ""}
          onClick={() => setActiveTab("events")}
        >
          Events
        </button>
      </div>

      <div className="component-content">
        {activeTab === "properties" && renderProperties()}
        {activeTab === "methods" && renderMethods()}
        {activeTab === "events" && renderEvents()}
      </div>
    </div>
  );
};

export default ComponentView;
