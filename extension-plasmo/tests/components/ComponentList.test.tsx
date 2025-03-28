/**
 * ComponentList component tests
 * 
 * Tests for the component that renders the list of detected Livewire components
 * in the DevTools panel.
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import { ComponentList } from '../../components/ComponentList';
import * as MessageUtils from '../../lib/utils/MessageUtils';

// Mock MessageUtils
jest.mock('../../lib/utils/MessageUtils', () => ({
  MessageActionType: {
    GET_COMPONENT_STATE: 'GET_COMPONENT_STATE',
    REFRESH_COMPONENTS: 'REFRESH_COMPONENTS'
  },
  createMessage: jest.fn((action, data) => ({ action, data })),
  sendThroughPort: jest.fn()
}));

describe('ComponentList', () => {
  // Sample component data for testing
  const mockComponents = [
    { id: '1', name: 'TestComponent1', fingerprint: 'abc123', nested: false },
    { id: '2', name: 'TestComponent2', fingerprint: 'def456', nested: true },
    { id: '3', name: 'TestComponent3', fingerprint: 'ghi789', nested: false }
  ];
  
  const mockPort = { name: 'tapped-devtools-panel' };
  
  beforeEach(() => {
    jest.clearAllMocks();
  });
  
  test('renders loading state when components are loading', () => {
    render(
      <ComponentList 
        components={[]} 
        loading={true}
        selectedComponent={null}
        onSelectComponent={jest.fn()}
        port={mockPort as any}
      />
    );
    
    expect(screen.getByText(/loading components/i)).toBeInTheDocument();
  });
  
  test('renders empty state when no components are detected', () => {
    render(
      <ComponentList 
        components={[]} 
        loading={false}
        selectedComponent={null}
        onSelectComponent={jest.fn()}
        port={mockPort as any}
      />
    );
    
    expect(screen.getByText(/no components detected/i)).toBeInTheDocument();
  });
  
  test('renders component list when components are available', () => {
    render(
      <ComponentList 
        components={mockComponents} 
        loading={false}
        selectedComponent={null}
        onSelectComponent={jest.fn()}
        port={mockPort as any}
      />
    );
    
    // Should show all component names
    expect(screen.getByText('TestComponent1')).toBeInTheDocument();
    expect(screen.getByText('TestComponent2')).toBeInTheDocument();
    expect(screen.getByText('TestComponent3')).toBeInTheDocument();
  });
  
  test('highlights selected component', () => {
    render(
      <ComponentList 
        components={mockComponents} 
        loading={false}
        selectedComponent={mockComponents[1]}
        onSelectComponent={jest.fn()}
        port={mockPort as any}
      />
    );
    
    // Find all component items
    const componentItems = screen.getAllByRole('listitem');
    
    // The second item should have the selected class
    expect(componentItems[1]).toHaveClass('selected');
    
    // Others should not have the selected class
    expect(componentItems[0]).not.toHaveClass('selected');
    expect(componentItems[2]).not.toHaveClass('selected');
  });
  
  test('calls onSelectComponent when component is clicked', () => {
    const mockSelectFn = jest.fn();
    
    render(
      <ComponentList 
        components={mockComponents} 
        loading={false}
        selectedComponent={null}
        onSelectComponent={mockSelectFn}
        port={mockPort as any}
      />
    );
    
    // Click on the first component
    fireEvent.click(screen.getByText('TestComponent1'));
    
    // Selection handler should be called with the component
    expect(mockSelectFn).toHaveBeenCalledWith(mockComponents[0]);
  });
  
  test('shows nested components with proper indentation', () => {
    const nestedComponents = [
      { id: '1', name: 'ParentComponent', fingerprint: 'abc123', nested: false },
      { id: '2', name: 'ChildComponent1', fingerprint: 'def456', nested: true },
      { id: '3', name: 'GrandchildComponent', fingerprint: 'ghi789', nested: true, nesting_level: 2 },
      { id: '4', name: 'ChildComponent2', fingerprint: 'jkl012', nested: true }
    ];
    
    render(
      <ComponentList 
        components={nestedComponents} 
        loading={false}
        selectedComponent={null}
        onSelectComponent={jest.fn()}
        port={mockPort as any}
      />
    );
    
    // All components should be rendered
    expect(screen.getByText('ParentComponent')).toBeInTheDocument();
    expect(screen.getByText('ChildComponent1')).toBeInTheDocument();
    expect(screen.getByText('GrandchildComponent')).toBeInTheDocument();
    expect(screen.getByText('ChildComponent2')).toBeInTheDocument();
    
    // Nested components should have indent class
    const componentItems = screen.getAllByRole('listitem');
    expect(componentItems[1]).toHaveClass('nested');
    expect(componentItems[2]).toHaveClass('nested-2');
  });
  
  test('refresh button sends refresh request', async () => {
    render(
      <ComponentList 
        components={mockComponents} 
        loading={false}
        selectedComponent={null}
        onSelectComponent={jest.fn()}
        port={mockPort as any}
      />
    );
    
    // Find and click refresh button
    const refreshButton = screen.getByRole('button', { name: /refresh/i });
    fireEvent.click(refreshButton);
    
    // Should send refresh message
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.REFRESH_COMPONENTS
        })
      );
    });
  });
  
  test('filter input filters component list', () => {
    render(
      <ComponentList 
        components={mockComponents} 
        loading={false}
        selectedComponent={null}
        onSelectComponent={jest.fn()}
        port={mockPort as any}
      />
    );
    
    // Find filter input
    const filterInput = screen.getByPlaceholderText(/filter components/i);
    
    // Type in filter
    fireEvent.change(filterInput, { target: { value: 'Component1' } });
    
    // Should only show matching component
    expect(screen.getByText('TestComponent1')).toBeInTheDocument();
    expect(screen.queryByText('TestComponent2')).not.toBeInTheDocument();
    expect(screen.queryByText('TestComponent3')).not.toBeInTheDocument();
    
    // Clear filter
    fireEvent.change(filterInput, { target: { value: '' } });
    
    // Should show all components again
    expect(screen.getByText('TestComponent1')).toBeInTheDocument();
    expect(screen.getByText('TestComponent2')).toBeInTheDocument();
    expect(screen.getByText('TestComponent3')).toBeInTheDocument();
  });
  
  test('handles component selection for state inspection', async () => {
    const mockSelectFn = jest.fn();
    
    render(
      <ComponentList 
        components={mockComponents} 
        loading={false}
        selectedComponent={null}
        onSelectComponent={mockSelectFn}
        port={mockPort as any}
      />
    );
    
    // Click on a component
    fireEvent.click(screen.getByText('TestComponent2'));
    
    // Selection handler should be called
    expect(mockSelectFn).toHaveBeenCalledWith(mockComponents[1]);
    
    // Should request component state
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.GET_COMPONENT_STATE,
          data: expect.objectContaining({
            componentId: '2'
          })
        })
      );
    });
  });
  
  test('applies dark theme correctly', () => {
    // Mock body with dark theme class
    document.body.classList.add('theme-dark');
    
    render(
      <ComponentList 
        components={mockComponents} 
        loading={false}
        selectedComponent={null}
        onSelectComponent={jest.fn()}
        port={mockPort as any}
      />
    );
    
    // The component list should have dark theme class
    const componentList = screen.getByRole('list');
    expect(componentList).toHaveClass('dark-theme');
    
    // Clean up
    document.body.classList.remove('theme-dark');
  });
  
  test('shows component count in header', () => {
    render(
      <ComponentList 
        components={mockComponents} 
        loading={false}
        selectedComponent={null}
        onSelectComponent={jest.fn()}
        port={mockPort as any}
      />
    );
    
    // Should show component count
    expect(screen.getByText(/3 components/i)).toBeInTheDocument();
  });
});
