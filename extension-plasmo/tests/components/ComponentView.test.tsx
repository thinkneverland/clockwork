/**
 * ComponentView component tests
 * 
 * Tests for the component that displays detailed information about a selected
 * Livewire component, including state inspection and editing capabilities.
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import { ComponentView } from '../../components/ComponentView';
import * as MessageUtils from '../../lib/utils/MessageUtils';
import * as StateOptimizer from '../../lib/utils/StateOptimizer';

// Mock dependencies
jest.mock('../../lib/utils/MessageUtils', () => ({
  MessageActionType: {
    SET_COMPONENT_STATE: 'SET_COMPONENT_STATE',
    GET_COMPONENT_STATE: 'GET_COMPONENT_STATE'
  },
  createMessage: jest.fn((action, data) => ({ action, data })),
  sendThroughPort: jest.fn()
}));

jest.mock('../../lib/utils/StateOptimizer', () => ({
  getChangedPaths: jest.fn().mockReturnValue([]),
  shouldUpdate: jest.fn().mockReturnValue(true)
}));

describe('ComponentView', () => {
  // Sample component data for testing
  const mockComponent = {
    id: '1',
    name: 'TestComponent',
    fingerprint: 'abc123',
    state: {
      title: 'Test Title',
      count: 42,
      items: ['Item 1', 'Item 2', 'Item 3'],
      options: {
        color: 'blue',
        size: 'large'
      },
      isActive: true
    },
    computed: ['fullTitle', 'displayCount'],
    methods: [
      { name: 'increment', parameters: [] },
      { name: 'addItem', parameters: ['text'] },
      { name: 'changeOptions', parameters: ['color', 'size'] }
    ]
  };
  
  const mockPort = { name: 'tapped-devtools-panel' };
  
  beforeEach(() => {
    jest.clearAllMocks();
  });
  
  test('renders placeholder when no component is selected', () => {
    render(
      <ComponentView 
        component={null}
        port={mockPort as any}
      />
    );
    
    expect(screen.getByText(/no component selected/i)).toBeInTheDocument();
  });
  
  test('renders component details when component is provided', () => {
    render(
      <ComponentView 
        component={mockComponent}
        port={mockPort as any}
      />
    );
    
    // Should show component name
    expect(screen.getByText('TestComponent')).toBeInTheDocument();
    
    // Should show component state
    expect(screen.getByText(/title/i)).toBeInTheDocument();
    expect(screen.getByText(/count/i)).toBeInTheDocument();
    expect(screen.getByText(/items/i)).toBeInTheDocument();
    expect(screen.getByText(/options/i)).toBeInTheDocument();
    expect(screen.getByText(/isActive/i)).toBeInTheDocument();
    
    // Should show state values
    expect(screen.getByText('"Test Title"')).toBeInTheDocument();
    expect(screen.getByText('42')).toBeInTheDocument();
    expect(screen.getByText(/Item 1/i)).toBeInTheDocument();
  });
  
  test('renders computed properties with indicator', () => {
    render(
      <ComponentView 
        component={mockComponent}
        port={mockPort as any}
      />
    );
    
    // Should show computed properties with indicator
    const computedElements = screen.getAllByText(/computed/i);
    expect(computedElements.length).toBeGreaterThan(0);
    
    // Should show computed property names
    expect(screen.getByText(/fullTitle/i)).toBeInTheDocument();
    expect(screen.getByText(/displayCount/i)).toBeInTheDocument();
  });
  
  test('renders methods with parameters', () => {
    render(
      <ComponentView 
        component={mockComponent}
        port={mockPort as any}
      />
    );
    
    // Switch to methods tab
    const methodsTab = screen.getByRole('tab', { name: /methods/i });
    fireEvent.click(methodsTab);
    
    // Should show method names
    expect(screen.getByText(/increment/i)).toBeInTheDocument();
    expect(screen.getByText(/addItem/i)).toBeInTheDocument();
    expect(screen.getByText(/changeOptions/i)).toBeInTheDocument();
    
    // Should show method parameters
    expect(screen.getByText(/text/i)).toBeInTheDocument();
    expect(screen.getByText(/color/i)).toBeInTheDocument();
    expect(screen.getByText(/size/i)).toBeInTheDocument();
  });
  
  test('allows editing of state values', async () => {
    render(
      <ComponentView 
        component={mockComponent}
        port={mockPort as any}
      />
    );
    
    // Find title property value
    const titleValue = screen.getByText('"Test Title"');
    
    // Click to edit
    fireEvent.click(titleValue);
    
    // Input field should appear
    const inputField = screen.getByDisplayValue('Test Title');
    
    // Change the value
    fireEvent.change(inputField, { target: { value: 'Updated Title' } });
    
    // Submit the edit
    fireEvent.keyDown(inputField, { key: 'Enter', code: 'Enter' });
    
    // Should send update message to background
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.SET_COMPONENT_STATE,
          data: expect.objectContaining({
            componentId: '1',
            path: 'title',
            value: 'Updated Title'
          })
        })
      );
    });
  });
  
  test('allows editing of nested state values', async () => {
    render(
      <ComponentView 
        component={mockComponent}
        port={mockPort as any}
      />
    );
    
    // Expand options object
    const optionsProperty = screen.getByText(/options/i);
    fireEvent.click(optionsProperty);
    
    // Find color property
    const colorValue = screen.getByText('"blue"');
    
    // Click to edit
    fireEvent.click(colorValue);
    
    // Input field should appear
    const inputField = screen.getByDisplayValue('blue');
    
    // Change the value
    fireEvent.change(inputField, { target: { value: 'red' } });
    
    // Submit the edit
    fireEvent.keyDown(inputField, { key: 'Enter', code: 'Enter' });
    
    // Should send update message with nested path
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.SET_COMPONENT_STATE,
          data: expect.objectContaining({
            componentId: '1',
            path: 'options.color',
            value: 'red'
          })
        })
      );
    });
  });
  
  test('prevents editing of computed properties', () => {
    // Add some computed properties to state
    const componentWithComputed = {
      ...mockComponent,
      state: {
        ...mockComponent.state,
        fullTitle: 'Complete Title',
        displayCount: '42 items'
      }
    };
    
    render(
      <ComponentView 
        component={componentWithComputed}
        port={mockPort as any}
      />
    );
    
    // Find computed property
    const fullTitleElement = screen.getByText(/fullTitle/i).closest('.property-row');
    
    // Should have computed or read-only class
    expect(fullTitleElement).toHaveClass('computed');
    
    // Click the value - would normally start editing
    const fullTitleValue = screen.getByText('"Complete Title"');
    fireEvent.click(fullTitleValue);
    
    // Input field should NOT appear
    expect(screen.queryByDisplayValue('Complete Title')).not.toBeInTheDocument();
  });
  
  test('refreshes component state on refresh button click', async () => {
    render(
      <ComponentView 
        component={mockComponent}
        port={mockPort as any}
      />
    );
    
    // Find and click refresh button
    const refreshButton = screen.getByRole('button', { name: /refresh/i });
    fireEvent.click(refreshButton);
    
    // Should request fresh component state
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.GET_COMPONENT_STATE,
          data: expect.objectContaining({
            componentId: '1',
            fresh: true
          })
        })
      );
    });
  });
  
  test('supports array item editing', async () => {
    render(
      <ComponentView 
        component={mockComponent}
        port={mockPort as any}
      />
    );
    
    // Expand items array
    const itemsProperty = screen.getByText(/items/i);
    fireEvent.click(itemsProperty);
    
    // Find first array item
    const firstItemValue = screen.getByText('"Item 1"');
    
    // Click to edit
    fireEvent.click(firstItemValue);
    
    // Input field should appear
    const inputField = screen.getByDisplayValue('Item 1');
    
    // Change the value
    fireEvent.change(inputField, { target: { value: 'Updated Item' } });
    
    // Submit the edit
    fireEvent.keyDown(inputField, { key: 'Enter', code: 'Enter' });
    
    // Should send update message with array index
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.SET_COMPONENT_STATE,
          data: expect.objectContaining({
            componentId: '1',
            path: 'items.0',
            value: 'Updated Item'
          })
        })
      );
    });
  });
  
  test('supports boolean value toggling', async () => {
    render(
      <ComponentView 
        component={mockComponent}
        port={mockPort as any}
      />
    );
    
    // Find boolean property value
    const booleanValue = screen.getByText('true');
    
    // Click to toggle
    fireEvent.click(booleanValue);
    
    // Should send update message with toggled value
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.SET_COMPONENT_STATE,
          data: expect.objectContaining({
            componentId: '1',
            path: 'isActive',
            value: false
          })
        })
      );
    });
  });
  
  test('handles JSON object editing', async () => {
    render(
      <ComponentView 
        component={mockComponent}
        port={mockPort as any}
      />
    );
    
    // Switch to JSON view
    const jsonTab = screen.getByRole('tab', { name: /json/i });
    fireEvent.click(jsonTab);
    
    // Should show JSON editor
    const jsonEditor = screen.getByRole('textbox');
    expect(jsonEditor).toBeInTheDocument();
    
    // Current state should be there
    expect(jsonEditor).toHaveValue(JSON.stringify(mockComponent.state, null, 2));
    
    // Change the JSON
    const updatedState = {
      ...mockComponent.state,
      title: 'Updated via JSON',
      newProp: 'New Value'
    };
    
    fireEvent.change(jsonEditor, { target: { value: JSON.stringify(updatedState, null, 2) } });
    
    // Apply the changes
    const applyButton = screen.getByRole('button', { name: /apply/i });
    fireEvent.click(applyButton);
    
    // Should send update message with complete state
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.SET_COMPONENT_STATE,
          data: expect.objectContaining({
            componentId: '1',
            fullState: updatedState
          })
        })
      );
    });
  });
  
  test('applies dark theme correctly', () => {
    // Mock body with dark theme class
    document.body.classList.add('theme-dark');
    
    render(
      <ComponentView 
        component={mockComponent}
        port={mockPort as any}
      />
    );
    
    // The component view should have dark theme class
    const componentView = screen.getByTestId('component-view');
    expect(componentView).toHaveClass('dark-theme');
    
    // Clean up
    document.body.classList.remove('theme-dark');
  });
});
