/**
 * EventTimeline component tests
 * 
 * Tests for the component that displays a chronological timeline of Livewire events,
 * component lifecycle events, and other application events in the DevTools panel.
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import { EventTimeline } from '../../components/EventTimeline';
import * as MessageUtils from '../../lib/utils/MessageUtils';
import { EventBus } from '../../lib/utils/EventBus';

// Mock dependencies
jest.mock('../../lib/utils/MessageUtils', () => ({
  MessageActionType: {
    GET_EVENTS: 'GET_EVENTS',
    CLEAR_EVENTS: 'CLEAR_EVENTS'
  },
  createMessage: jest.fn((action, data) => ({ action, data })),
  sendThroughPort: jest.fn()
}));

jest.mock('../../lib/utils/EventBus', () => {
  return {
    EventBus: jest.fn().mockImplementation(() => ({
      subscribe: jest.fn(),
      unsubscribe: jest.fn(),
      getEventHistory: jest.fn(),
      getFilteredEvents: jest.fn(),
      getEventCountByType: jest.fn(),
      clearEventHistory: jest.fn(),
      enableHistoryTracking: jest.fn(),
      disableHistoryTracking: jest.fn()
    }))
  };
});

describe('EventTimeline', () => {
  // Sample event data for testing
  const mockEvents = [
    {
      id: '1',
      type: 'component:updated',
      timestamp: Date.now() - 5000,
      data: { 
        componentId: 'comp-1', 
        componentName: 'Counter',
        changes: [{ property: 'count', from: 1, to: 2 }] 
      }
    },
    {
      id: '2',
      type: 'livewire:event',
      timestamp: Date.now() - 3000,
      data: { 
        name: 'count-changed', 
        payload: { value: 2 }, 
        componentId: 'comp-1' 
      }
    },
    {
      id: '3',
      type: 'component:method-called',
      timestamp: Date.now() - 1000,
      data: { 
        componentId: 'comp-1', 
        componentName: 'Counter',
        method: 'increment',
        parameters: [] 
      }
    }
  ];
  
  const mockPort = { name: 'tapped-devtools-panel' };
  let mockEventBus;
  
  beforeEach(() => {
    jest.clearAllMocks();
    
    // Set up EventBus mock
    mockEventBus = new EventBus();
    mockEventBus.getEventHistory.mockReturnValue(mockEvents);
    mockEventBus.getFilteredEvents.mockImplementation((filter) => {
      if (!filter) return mockEvents;
      return mockEvents.filter(filter);
    });
    mockEventBus.getEventCountByType.mockReturnValue({
      'component:updated': 1,
      'livewire:event': 1,
      'component:method-called': 1
    });
  });
  
  test('renders loading state when events are loading', () => {
    render(
      <EventTimeline 
        loading={true}
        eventBus={mockEventBus}
        port={mockPort as any}
      />
    );
    
    expect(screen.getByText(/loading events/i)).toBeInTheDocument();
  });
  
  test('renders empty state when no events are available', () => {
    // Mock empty event history
    mockEventBus.getEventHistory.mockReturnValue([]);
    
    render(
      <EventTimeline 
        loading={false}
        eventBus={mockEventBus}
        port={mockPort as any}
      />
    );
    
    expect(screen.getByText(/no events recorded/i)).toBeInTheDocument();
  });
  
  test('renders events in chronological order', () => {
    render(
      <EventTimeline 
        loading={false}
        eventBus={mockEventBus}
        port={mockPort as any}
      />
    );
    
    // Check for all event types
    expect(screen.getByText(/component:updated/i)).toBeInTheDocument();
    expect(screen.getByText(/livewire:event/i)).toBeInTheDocument();
    expect(screen.getByText(/component:method-called/i)).toBeInTheDocument();
    
    // Check for component and event names
    expect(screen.getByText(/Counter/i)).toBeInTheDocument();
    expect(screen.getByText(/count-changed/i)).toBeInTheDocument();
    expect(screen.getByText(/increment/i)).toBeInTheDocument();
    
    // Events should be in chronological order (oldest first by default)
    const eventItems = screen.getAllByRole('listitem');
    expect(eventItems.length).toBe(3);
    
    // First event should be component:updated
    expect(eventItems[0]).toHaveTextContent(/component:updated/i);
    // Second event should be livewire:event
    expect(eventItems[1]).toHaveTextContent(/livewire:event/i);
    // Third event should be component:method-called
    expect(eventItems[2]).toHaveTextContent(/component:method-called/i);
  });
  
  test('allows sorting by newest first', () => {
    render(
      <EventTimeline 
        loading={false}
        eventBus={mockEventBus}
        port={mockPort as any}
      />
    );
    
    // Find and click sort button
    const sortButton = screen.getByRole('button', { name: /sort/i });
    fireEvent.click(sortButton);
    
    // Events should now be in reverse chronological order
    const eventItems = screen.getAllByRole('listitem');
    
    // First event should now be component:method-called (newest)
    expect(eventItems[0]).toHaveTextContent(/component:method-called/i);
    // Second event should be livewire:event
    expect(eventItems[1]).toHaveTextContent(/livewire:event/i);
    // Third event should be component:updated (oldest)
    expect(eventItems[2]).toHaveTextContent(/component:updated/i);
  });
  
  test('filters events by type', () => {
    mockEventBus.getFilteredEvents.mockImplementation((filter) => {
      if (!filter) return mockEvents;
      
      // Call the filter function with each event to simulate filtering
      return mockEvents.filter(event => {
        try {
          return filter(event);
        } catch (error) {
          return false;
        }
      });
    });
    
    render(
      <EventTimeline 
        loading={false}
        eventBus={mockEventBus}
        port={mockPort as any}
      />
    );
    
    // Find and click filter dropdown
    const filterDropdown = screen.getByRole('combobox', { name: /filter/i });
    fireEvent.change(filterDropdown, { target: { value: 'component:updated' } });
    
    // Should call getFilteredEvents with a filter function
    expect(mockEventBus.getFilteredEvents).toHaveBeenCalled();
    
    // Mock the filtered results
    mockEventBus.getFilteredEvents.mockReturnValueOnce([mockEvents[0]]);
    
    // Verify that only component:updated events are shown
    const filteredEvents = screen.getAllByRole('listitem');
    expect(filteredEvents.length).toBe(1);
    expect(filteredEvents[0]).toHaveTextContent(/component:updated/i);
  });
  
  test('expands event to show details when clicked', () => {
    render(
      <EventTimeline 
        loading={false}
        eventBus={mockEventBus}
        port={mockPort as any}
      />
    );
    
    // Find and click the first event
    const firstEvent = screen.getAllByRole('listitem')[0];
    fireEvent.click(firstEvent);
    
    // Should show expanded details
    expect(screen.getByText(/changes/i)).toBeInTheDocument();
    expect(screen.getByText(/count/i)).toBeInTheDocument();
    expect(screen.getByText(/1.*2/i)).toBeInTheDocument(); // From 1 to 2
  });
  
  test('clears events when clear button is clicked', async () => {
    render(
      <EventTimeline 
        loading={false}
        eventBus={mockEventBus}
        port={mockPort as any}
      />
    );
    
    // Find and click clear button
    const clearButton = screen.getByRole('button', { name: /clear/i });
    fireEvent.click(clearButton);
    
    // Should call clearEventHistory
    expect(mockEventBus.clearEventHistory).toHaveBeenCalled();
    
    // Should send clear events message
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.CLEAR_EVENTS
        })
      );
    });
  });
  
  test('refreshes events when refresh button is clicked', async () => {
    render(
      <EventTimeline 
        loading={false}
        eventBus={mockEventBus}
        port={mockPort as any}
      />
    );
    
    // Find and click refresh button
    const refreshButton = screen.getByRole('button', { name: /refresh/i });
    fireEvent.click(refreshButton);
    
    // Should send get events message
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.GET_EVENTS
        })
      );
    });
  });
  
  test('shows event counts in filter dropdown', () => {
    render(
      <EventTimeline 
        loading={false}
        eventBus={mockEventBus}
        port={mockPort as any}
      />
    );
    
    // Find filter dropdown
    const filterDropdown = screen.getByRole('combobox', { name: /filter/i });
    
    // Should show counts for each event type
    expect(filterDropdown).toHaveTextContent(/component:updated \(1\)/i);
    expect(filterDropdown).toHaveTextContent(/livewire:event \(1\)/i);
    expect(filterDropdown).toHaveTextContent(/component:method-called \(1\)/i);
  });
  
  test('performs event search with provided text', () => {
    mockEventBus.getFilteredEvents.mockImplementation((filter) => {
      if (!filter) return mockEvents;
      
      // Call the filter function with each event to simulate filtering
      return mockEvents.filter(event => {
        try {
          return filter(event);
        } catch (error) {
          return false;
        }
      });
    });
    
    render(
      <EventTimeline 
        loading={false}
        eventBus={mockEventBus}
        port={mockPort as any}
      />
    );
    
    // Find search input
    const searchInput = screen.getByPlaceholderText(/search events/i);
    
    // Search for "count"
    fireEvent.change(searchInput, { target: { value: 'count' } });
    
    // Should call getFilteredEvents with a filter function
    expect(mockEventBus.getFilteredEvents).toHaveBeenCalled();
    
    // Mock the search results
    mockEventBus.getFilteredEvents.mockReturnValueOnce([mockEvents[0], mockEvents[1]]);
    
    // Should show matching events
    const filteredEvents = screen.getAllByRole('listitem');
    expect(filteredEvents.length).toBe(2);
  });
  
  test('subscribes to event bus on mount and unsubscribes on unmount', () => {
    const { unmount } = render(
      <EventTimeline 
        loading={false}
        eventBus={mockEventBus}
        port={mockPort as any}
      />
    );
    
    // Should subscribe to event bus
    expect(mockEventBus.subscribe).toHaveBeenCalled();
    
    // Unmount component
    unmount();
    
    // Should unsubscribe from event bus
    expect(mockEventBus.unsubscribe).toHaveBeenCalled();
  });
  
  test('applies dark theme correctly', () => {
    // Mock body with dark theme class
    document.body.classList.add('theme-dark');
    
    render(
      <EventTimeline 
        loading={false}
        eventBus={mockEventBus}
        port={mockPort as any}
      />
    );
    
    // The timeline should have dark theme class
    const timeline = screen.getByTestId('event-timeline');
    expect(timeline).toHaveClass('dark-theme');
    
    // Clean up
    document.body.classList.remove('theme-dark');
  });
  
  test('renders different event types with appropriate icons and colors', () => {
    render(
      <EventTimeline 
        loading={false}
        eventBus={mockEventBus}
        port={mockPort as any}
      />
    );
    
    // Get all event items
    const eventItems = screen.getAllByRole('listitem');
    
    // Component update event should have update icon/class
    expect(eventItems[0]).toHaveClass('event-component-update');
    
    // Livewire event should have event icon/class
    expect(eventItems[1]).toHaveClass('event-livewire');
    
    // Method call event should have method icon/class
    expect(eventItems[2]).toHaveClass('event-method-call');
  });
});
