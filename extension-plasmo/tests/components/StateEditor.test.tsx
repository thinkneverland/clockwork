/**
 * StateEditor component tests
 * 
 * Tests for the component that allows editing Livewire component properties
 * with validation, undo/redo functionality and batch editing.
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import { StateEditor } from '../../components/StateEditor';
import * as MessageUtils from '../../lib/utils/MessageUtils';

// Mock dependencies
jest.mock('../../lib/utils/MessageUtils', () => ({
  MessageActionType: {
    UPDATE_COMPONENT: 'UPDATE_COMPONENT',
    EXECUTE_METHOD: 'EXECUTE_METHOD'
  },
  createMessage: jest.fn((action, data) => ({ action, data })),
  sendThroughPort: jest.fn()
}));

describe('StateEditor', () => {
  // Sample component data for testing
  const mockComponent = {
    id: 'comp-1',
    name: 'Counter',
    fingerprint: 'abc123',
    properties: {
      count: {
        value: 5,
        type: 'number',
        visibility: 'public'
      },
      title: {
        value: 'Counter Component',
        type: 'string',
        visibility: 'public'
      },
      enabled: {
        value: true,
        type: 'boolean',
        visibility: 'public'
      },
      items: {
        value: ['Item 1', 'Item 2'],
        type: 'array',
        visibility: 'public'
      },
      config: {
        value: {
          theme: 'light',
          showControls: true,
          maxValue: 10
        },
        type: 'object',
        visibility: 'public'
      },
      computedTotal: {
        value: 50,
        type: 'number',
        visibility: 'public',
        computed: true
      },
      _privateVar: {
        value: 'private',
        type: 'string',
        visibility: 'protected'
      }
    },
    methods: [
      {
        name: 'increment',
        params: []
      },
      {
        name: 'decrement',
        params: []
      },
      {
        name: 'setValue',
        params: ['value']
      }
    ]
  };
  
  const mockPort = { name: 'tapped-devtools-panel' };
  
  // Mock validation function
  const mockValidate = jest.fn().mockImplementation((componentId, property, value) => {
    // Simple validation rules
    if (property === 'count' && (typeof value !== 'number' || value < 0 || value > 100)) {
      return { valid: false, reason: 'Count must be a number between 0 and 100' };
    }
    
    if (property === 'computedTotal') {
      return { valid: false, reason: 'Cannot edit computed properties' };
    }
    
    if (property === '_privateVar') {
      return { valid: false, reason: 'Cannot edit protected properties' };
    }
    
    return { valid: true };
  });
  
  beforeEach(() => {
    jest.clearAllMocks();
  });
  
  test('renders component properties correctly', () => {
    render(
      <StateEditor 
        component={mockComponent}
        port={mockPort as any}
        validate={mockValidate}
      />
    );
    
    // Should show component name
    expect(screen.getByText(/Counter/i)).toBeInTheDocument();
    
    // Should show properties
    expect(screen.getByText(/count/i)).toBeInTheDocument();
    expect(screen.getByText(/title/i)).toBeInTheDocument();
    expect(screen.getByText(/enabled/i)).toBeInTheDocument();
    expect(screen.getByText(/items/i)).toBeInTheDocument();
    expect(screen.getByText(/config/i)).toBeInTheDocument();
    expect(screen.getByText(/computedTotal/i)).toBeInTheDocument();
    expect(screen.getByText(/_privateVar/i)).toBeInTheDocument();
    
    // Should show property values
    expect(screen.getByDisplayValue('5')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Counter Component')).toBeInTheDocument();
    
    // Boolean should be shown as checkbox
    const checkbox = screen.getByRole('checkbox');
    expect(checkbox).toBeInTheDocument();
    expect(checkbox).toBeChecked();
  });
  
  test('allows editing simple property values', async () => {
    render(
      <StateEditor 
        component={mockComponent}
        port={mockPort as any}
        validate={mockValidate}
      />
    );
    
    // Find count input
    const countInput = screen.getByDisplayValue('5');
    
    // Edit count
    fireEvent.change(countInput, { target: { value: '10' } });
    fireEvent.blur(countInput);
    
    // Should send update message
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.UPDATE_COMPONENT,
          data: expect.objectContaining({
            componentId: 'comp-1',
            property: 'count',
            value: 10
          })
        })
      );
    });
  });
  
  test('validates property edits', async () => {
    render(
      <StateEditor 
        component={mockComponent}
        port={mockPort as any}
        validate={mockValidate}
      />
    );
    
    // Find count input
    const countInput = screen.getByDisplayValue('5');
    
    // Try to set an invalid value
    fireEvent.change(countInput, { target: { value: '200' } }); // Greater than max
    fireEvent.blur(countInput);
    
    // Should show validation error
    await waitFor(() => {
      expect(screen.getByText(/count must be a number between 0 and 100/i)).toBeInTheDocument();
    });
    
    // Should not send update
    expect(MessageUtils.sendThroughPort).not.toHaveBeenCalled();
    
    // Correct to valid value
    fireEvent.change(countInput, { target: { value: '50' } });
    fireEvent.blur(countInput);
    
    // Should send update message
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.UPDATE_COMPONENT,
          data: expect.objectContaining({
            componentId: 'comp-1',
            property: 'count',
            value: 50
          })
        })
      );
    });
  });
  
  test('prevents editing computed properties', async () => {
    render(
      <StateEditor 
        component={mockComponent}
        port={mockPort as any}
        validate={mockValidate}
      />
    );
    
    // Computed property should be disabled
    const computedInput = screen.getByDisplayValue('50');
    expect(computedInput).toBeDisabled();
    
    // Trying to edit should show computed property message
    fireEvent.focus(computedInput);
    
    await waitFor(() => {
      expect(screen.getByText(/cannot edit computed properties/i)).toBeInTheDocument();
    });
  });
  
  test('prevents editing protected properties', async () => {
    render(
      <StateEditor 
        component={mockComponent}
        port={mockPort as any}
        validate={mockValidate}
      />
    );
    
    // Protected property should be disabled
    const privateInput = screen.getByDisplayValue('private');
    expect(privateInput).toBeDisabled();
    
    // Trying to edit should show protected property message
    fireEvent.focus(privateInput);
    
    await waitFor(() => {
      expect(screen.getByText(/cannot edit protected properties/i)).toBeInTheDocument();
    });
  });
  
  test('allows editing boolean values', async () => {
    render(
      <StateEditor 
        component={mockComponent}
        port={mockPort as any}
        validate={mockValidate}
      />
    );
    
    // Find enabled checkbox
    const enabledCheckbox = screen.getByRole('checkbox');
    
    // Toggle checkbox
    fireEvent.click(enabledCheckbox);
    
    // Should send update message
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.UPDATE_COMPONENT,
          data: expect.objectContaining({
            componentId: 'comp-1',
            property: 'enabled',
            value: false
          })
        })
      );
    });
  });
  
  test('handles array editing', async () => {
    render(
      <StateEditor 
        component={mockComponent}
        port={mockPort as any}
        validate={mockValidate}
      />
    );
    
    // Find items array
    const itemsExpander = screen.getByText(/items/i);
    
    // Expand array
    fireEvent.click(itemsExpander);
    
    // Should show array items
    expect(screen.getByText(/\[0\]/i)).toBeInTheDocument();
    expect(screen.getByText(/\[1\]/i)).toBeInTheDocument();
    
    // Find first item input
    const firstItemInput = screen.getByDisplayValue('Item 1');
    
    // Edit first item
    fireEvent.change(firstItemInput, { target: { value: 'Updated Item 1' } });
    fireEvent.blur(firstItemInput);
    
    // Should send update message
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.UPDATE_COMPONENT,
          data: expect.objectContaining({
            componentId: 'comp-1',
            property: 'items[0]',
            value: 'Updated Item 1'
          })
        })
      );
    });
    
    // Add new item
    const addButton = screen.getByText(/add item/i);
    fireEvent.click(addButton);
    
    // New item input should appear
    const newItemInput = screen.getByDisplayValue('');
    
    // Edit new item
    fireEvent.change(newItemInput, { target: { value: 'New Item' } });
    fireEvent.blur(newItemInput);
    
    // Should send update message
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.UPDATE_COMPONENT,
          data: expect.objectContaining({
            componentId: 'comp-1',
            property: 'items',
            value: ['Updated Item 1', 'Item 2', 'New Item']
          })
        })
      );
    });
    
    // Remove an item
    const removeButtons = screen.getAllByText(/remove/i);
    fireEvent.click(removeButtons[1]); // Remove second item
    
    // Should send update message
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.UPDATE_COMPONENT,
          data: expect.objectContaining({
            componentId: 'comp-1',
            property: 'items',
            value: ['Updated Item 1', 'New Item']
          })
        })
      );
    });
  });
  
  test('handles nested object editing', async () => {
    render(
      <StateEditor 
        component={mockComponent}
        port={mockPort as any}
        validate={mockValidate}
      />
    );
    
    // Find config object
    const configExpander = screen.getByText(/config/i);
    
    // Expand object
    fireEvent.click(configExpander);
    
    // Should show object properties
    expect(screen.getByText(/theme/i)).toBeInTheDocument();
    expect(screen.getByText(/showControls/i)).toBeInTheDocument();
    expect(screen.getByText(/maxValue/i)).toBeInTheDocument();
    
    // Find theme input
    const themeInput = screen.getByDisplayValue('light');
    
    // Edit theme
    fireEvent.change(themeInput, { target: { value: 'dark' } });
    fireEvent.blur(themeInput);
    
    // Should send update message
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.UPDATE_COMPONENT,
          data: expect.objectContaining({
            componentId: 'comp-1',
            property: 'config.theme',
            value: 'dark'
          })
        })
      );
    });
    
    // Edit maxValue
    const maxValueInput = screen.getByDisplayValue('10');
    fireEvent.change(maxValueInput, { target: { value: '20' } });
    fireEvent.blur(maxValueInput);
    
    // Should send update message
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.UPDATE_COMPONENT,
          data: expect.objectContaining({
            componentId: 'comp-1',
            property: 'config.maxValue',
            value: 20
          })
        })
      );
    });
  });
  
  test('supports batch editing of properties', async () => {
    render(
      <StateEditor 
        component={mockComponent}
        port={mockPort as any}
        validate={mockValidate}
      />
    );
    
    // Enter batch edit mode
    const batchEditButton = screen.getByText(/batch edit/i);
    fireEvent.click(batchEditButton);
    
    // Should show batch edit UI
    expect(screen.getByText(/apply batch/i)).toBeInTheDocument();
    expect(screen.getByText(/cancel/i)).toBeInTheDocument();
    
    // Edit multiple properties
    const countInput = screen.getByDisplayValue('5');
    fireEvent.change(countInput, { target: { value: '15' } });
    
    const titleInput = screen.getByDisplayValue('Counter Component');
    fireEvent.change(titleInput, { target: { value: 'Updated Counter' } });
    
    // Apply batch updates
    const applyButton = screen.getByText(/apply batch/i);
    fireEvent.click(applyButton);
    
    // Should send batch update message
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.UPDATE_COMPONENT,
          data: expect.objectContaining({
            componentId: 'comp-1',
            batchUpdate: [
              { property: 'count', value: 15 },
              { property: 'title', value: 'Updated Counter' }
            ]
          })
        })
      );
    });
  });
  
  test('provides undo/redo functionality', async () => {
    render(
      <StateEditor 
        component={mockComponent}
        port={mockPort as any}
        validate={mockValidate}
      />
    );
    
    // Edit count
    const countInput = screen.getByDisplayValue('5');
    fireEvent.change(countInput, { target: { value: '10' } });
    fireEvent.blur(countInput);
    
    // Wait for update to be sent
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.UPDATE_COMPONENT,
          data: expect.objectContaining({
            property: 'count',
            value: 10
          })
        })
      );
    });
    
    // Edit title
    const titleInput = screen.getByDisplayValue('Counter Component');
    fireEvent.change(titleInput, { target: { value: 'Updated Counter' } });
    fireEvent.blur(titleInput);
    
    // Wait for update to be sent
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.UPDATE_COMPONENT,
          data: expect.objectContaining({
            property: 'title',
            value: 'Updated Counter'
          })
        })
      );
    });
    
    // Click undo button
    const undoButton = screen.getByLabelText(/undo/i);
    fireEvent.click(undoButton);
    
    // Should send update to revert title
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.UPDATE_COMPONENT,
          data: expect.objectContaining({
            property: 'title',
            value: 'Counter Component'
          })
        })
      );
    });
    
    // Click undo again
    fireEvent.click(undoButton);
    
    // Should send update to revert count
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.UPDATE_COMPONENT,
          data: expect.objectContaining({
            property: 'count',
            value: 5
          })
        })
      );
    });
    
    // Click redo button
    const redoButton = screen.getByLabelText(/redo/i);
    fireEvent.click(redoButton);
    
    // Should send update to reapply count
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.UPDATE_COMPONENT,
          data: expect.objectContaining({
            property: 'count',
            value: 10
          })
        })
      );
    });
  });
  
  test('allows execution of component methods', async () => {
    render(
      <StateEditor 
        component={mockComponent}
        port={mockPort as any}
        validate={mockValidate}
      />
    );
    
    // Open methods section
    const methodsTab = screen.getByText(/methods/i);
    fireEvent.click(methodsTab);
    
    // Should list component methods
    expect(screen.getByText(/increment/i)).toBeInTheDocument();
    expect(screen.getByText(/decrement/i)).toBeInTheDocument();
    expect(screen.getByText(/setValue/i)).toBeInTheDocument();
    
    // Execute increment method
    const incrementButton = screen.getByText(/execute/i, { selector: 'button' });
    fireEvent.click(incrementButton);
    
    // Should send method execution message
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.EXECUTE_METHOD,
          data: expect.objectContaining({
            componentId: 'comp-1',
            method: 'increment',
            params: []
          })
        })
      );
    });
    
    // Expand setValue method with parameters
    const setValueButton = screen.getAllByText(/execute/i, { selector: 'button' })[2];
    const setValueMethodRow = setValueButton.closest('tr');
    const expandButton = setValueMethodRow?.querySelector('button[aria-label="Expand"]');
    fireEvent.click(expandButton!);
    
    // Should show parameter form
    expect(screen.getByText(/params/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/value/i)).toBeInTheDocument();
    
    // Enter parameter value
    const valueInput = screen.getByLabelText(/value/i);
    fireEvent.change(valueInput, { target: { value: '25' } });
    
    // Execute setValue method with parameter
    const executeWithParamsButton = screen.getByText(/execute with params/i);
    fireEvent.click(executeWithParamsButton);
    
    // Should send method execution message with parameters
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.EXECUTE_METHOD,
          data: expect.objectContaining({
            componentId: 'comp-1',
            method: 'setValue',
            params: [25]
          })
        })
      );
    });
  });
  
  test('shows visual feedback when property is updated', async () => {
    // Mock component with property history
    const componentWithHistory = {
      ...mockComponent,
      propertyHistory: {
        count: [
          { value: 0, timestamp: Date.now() - 5000 },
          { value: 3, timestamp: Date.now() - 3000 },
          { value: 5, timestamp: Date.now() - 1000 }
        ]
      }
    };
    
    render(
      <StateEditor 
        component={componentWithHistory}
        port={mockPort as any}
        validate={mockValidate}
      />
    );
    
    // Open history section
    const historyTab = screen.getByText(/history/i);
    fireEvent.click(historyTab);
    
    // Should show property history
    const historyItems = screen.getAllByRole('listitem');
    expect(historyItems.length).toBe(3);
    
    // Should show values and timestamps
    expect(screen.getByText(/count: 0/i)).toBeInTheDocument();
    expect(screen.getByText(/count: 3/i)).toBeInTheDocument();
    expect(screen.getByText(/count: 5/i)).toBeInTheDocument();
    
    // Select a history item to revert to
    fireEvent.click(historyItems[0]);
    
    // Should show restore button
    const restoreButton = screen.getByText(/restore/i);
    fireEvent.click(restoreButton);
    
    // Should send update to restore value
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.UPDATE_COMPONENT,
          data: expect.objectContaining({
            componentId: 'comp-1',
            property: 'count',
            value: 0
          })
        })
      );
    });
  });
  
  test('handles dark theme correctly', () => {
    // Mock body with dark theme class
    document.body.classList.add('theme-dark');
    
    render(
      <StateEditor 
        component={mockComponent}
        port={mockPort as any}
        validate={mockValidate}
      />
    );
    
    // The editor should have dark theme class
    const editor = screen.getByTestId('state-editor');
    expect(editor).toHaveClass('dark-theme');
    
    // Clean up
    document.body.classList.remove('theme-dark');
  });
});
