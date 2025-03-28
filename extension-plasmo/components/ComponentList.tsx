import React, { useState, useEffect } from "react";
import { throttle } from "../lib/utils/StateOptimizer";

interface ComponentListProps {
  components: any[];
  onSelect: (component: any) => void;
  selectedComponentId: string | null;
  onRefresh?: () => void;
  onSnapshot?: (componentId: string) => void;
}

/**
 * Component that renders the list of detected Livewire components
 */
export const ComponentList: React.FC<ComponentListProps> = ({
  components,
  onSelect,
  selectedComponentId,
  onRefresh,
  onSnapshot
}) => {
  const [searchQuery, setSearchQuery] = useState("");
  const [filteredComponents, setFilteredComponents] = useState(components);

  // Filter components when search query or components change
  useEffect(() => {
    if (!searchQuery) {
      setFilteredComponents(components);
      return;
    }
    
    const query = searchQuery.toLowerCase();
    const filtered = components.filter(component => 
      component.name.toLowerCase().includes(query) ||
      component.id.toLowerCase().includes(query)
    );
    
    setFilteredComponents(filtered);
  }, [searchQuery, components]);

  // Throttled search handler to prevent excessive filtering on rapid typing
  const handleSearchChange = throttle((e: React.ChangeEvent<HTMLInputElement>) => {
    setSearchQuery(e.target.value);
  }, 300);

  // Handle component selection
  const handleSelect = (component: any) => {
    onSelect(component);
  };

  // Handle component snapshot
  const handleSnapshot = (e: React.MouseEvent, componentId: string) => {
    e.stopPropagation(); // Prevent triggering selection
    if (onSnapshot) {
      onSnapshot(componentId);
    }
  };

  return (
    <div className="component-list-container">
      <div className="component-list-header">
        <h3>Components</h3>
        <div className="component-list-actions">
          {onRefresh && (
            <button 
              className="refresh-button" 
              onClick={onRefresh}
              title="Refresh components"
            >
              ↻
            </button>
          )}
        </div>
      </div>

      <div className="component-search">
        <input
          type="text"
          placeholder="Search components..."
          onChange={handleSearchChange}
          className="search-input"
        />
      </div>

      {filteredComponents.length === 0 ? (
        <div className="no-components">
          {components.length === 0 
            ? "No Livewire components detected" 
            : "No components match your search"}
        </div>
      ) : (
        <ul className="component-list">
          {filteredComponents.map(component => (
            <li
              key={component.id}
              className={selectedComponentId === component.id ? "selected" : ""}
              onClick={() => handleSelect(component)}
            >
              <div className="component-info">
                <span className="component-name">{component.name}</span>
                <span className="component-id-small">{component.id.split('-').pop()}</span>
              </div>
              {onSnapshot && (
                <div className="component-actions">
                  <button 
                    className="snapshot-button"
                    onClick={(e) => handleSnapshot(e, component.id)}
                    title="Take component snapshot"
                  >
                    📷
                  </button>
                </div>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
};

export default ComponentList;
