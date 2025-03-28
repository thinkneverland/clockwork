/**
 * RequestMonitor component tests
 * 
 * Tests for the component that displays HTTP requests, with special handling
 * for Livewire AJAX calls, headers inspection, and payload formatting.
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import { RequestMonitor } from '../../components/RequestMonitor';
import * as MessageUtils from '../../lib/utils/MessageUtils';

// Mock dependencies
jest.mock('../../lib/utils/MessageUtils', () => ({
  MessageActionType: {
    GET_REQUESTS: 'GET_REQUESTS',
    CLEAR_REQUESTS: 'CLEAR_REQUESTS'
  },
  createMessage: jest.fn((action, data) => ({ action, data })),
  sendThroughPort: jest.fn()
}));

describe('RequestMonitor', () => {
  // Sample request data for testing
  const mockRequests = [
    {
      id: '1',
      url: '/livewire/message/counter',
      method: 'POST',
      status: 200,
      statusText: 'OK',
      duration: 120,
      timestamp: Date.now() - 5000,
      isLivewire: true,
      livewireComponent: 'counter',
      livewireAction: 'callMethod',
      livewireData: {
        method: 'increment',
        params: []
      },
      requestHeaders: {
        'Content-Type': 'application/json',
        'X-Livewire': '1',
        'X-CSRF-TOKEN': 'token123'
      },
      requestBody: JSON.stringify({
        component: 'counter',
        action: 'callMethod',
        data: {
          method: 'increment',
          params: []
        }
      }),
      responseHeaders: {
        'Content-Type': 'application/json',
        'X-Inertia': 'false'
      },
      responseBody: JSON.stringify({
        effects: {
          html: '<div>Count: 1</div>',
          dirty: ['count']
        }
      })
    },
    {
      id: '2',
      url: '/api/users',
      method: 'GET',
      status: 200,
      statusText: 'OK',
      duration: 85,
      timestamp: Date.now() - 3000,
      isLivewire: false,
      requestHeaders: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer token123'
      },
      requestBody: '',
      responseHeaders: {
        'Content-Type': 'application/json'
      },
      responseBody: JSON.stringify([
        { id: 1, name: 'John Doe' },
        { id: 2, name: 'Jane Smith' }
      ])
    },
    {
      id: '3',
      url: '/api/posts',
      method: 'POST',
      status: 422,
      statusText: 'Unprocessable Entity',
      duration: 65,
      timestamp: Date.now() - 1000,
      isLivewire: false,
      requestHeaders: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer token123'
      },
      requestBody: JSON.stringify({
        title: '',
        content: 'Test content'
      }),
      responseHeaders: {
        'Content-Type': 'application/json'
      },
      responseBody: JSON.stringify({
        errors: {
          title: ['The title field is required.']
        }
      })
    }
  ];
  
  const mockPort = { name: 'tapped-devtools-panel' };
  
  beforeEach(() => {
    jest.clearAllMocks();
  });
  
  test('renders loading state when requests are loading', () => {
    render(
      <RequestMonitor 
        requests={[]}
        loading={true}
        port={mockPort as any}
      />
    );
    
    expect(screen.getByText(/loading requests/i)).toBeInTheDocument();
  });
  
  test('renders empty state when no requests are available', () => {
    render(
      <RequestMonitor 
        requests={[]}
        loading={false}
        port={mockPort as any}
      />
    );
    
    expect(screen.getByText(/no requests recorded/i)).toBeInTheDocument();
  });
  
  test('renders request list with summary information', () => {
    render(
      <RequestMonitor 
        requests={mockRequests}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Should show request count
    expect(screen.getByText(/3 requests/i)).toBeInTheDocument();
    
    // Should show request methods
    expect(screen.getAllByText(/POST/i).length).toBe(2);
    expect(screen.getByText(/GET/i)).toBeInTheDocument();
    
    // Should show URLs
    expect(screen.getByText(/\/livewire\/message\/counter/i)).toBeInTheDocument();
    expect(screen.getByText(/\/api\/users/i)).toBeInTheDocument();
    expect(screen.getByText(/\/api\/posts/i)).toBeInTheDocument();
    
    // Should show status codes
    expect(screen.getAllByText(/200/i).length).toBe(2);
    expect(screen.getByText(/422/i)).toBeInTheDocument();
    
    // Should show durations
    expect(screen.getByText(/120ms/i)).toBeInTheDocument();
    expect(screen.getByText(/85ms/i)).toBeInTheDocument();
    expect(screen.getByText(/65ms/i)).toBeInTheDocument();
    
    // Should indicate Livewire requests
    const livewireIndicator = screen.getByTestId('livewire-indicator');
    expect(livewireIndicator).toBeInTheDocument();
  });
  
  test('expands request to show details when clicked', () => {
    render(
      <RequestMonitor 
        requests={mockRequests}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Find and click the first request
    const firstRequest = screen.getAllByRole('listitem')[0];
    fireEvent.click(firstRequest);
    
    // Should show expanded details with tabs
    expect(screen.getByRole('tab', { name: /request/i })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /response/i })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /headers/i })).toBeInTheDocument();
    
    // Should show Livewire-specific information
    expect(screen.getByText(/livewire component/i)).toBeInTheDocument();
    expect(screen.getByText(/counter/i)).toBeInTheDocument();
    expect(screen.getByText(/callMethod/i)).toBeInTheDocument();
    expect(screen.getByText(/increment/i)).toBeInTheDocument();
    
    // Should show formatted request body
    expect(screen.getByText(/"component": "counter"/i)).toBeInTheDocument();
    
    // Switch to response tab
    const responseTab = screen.getByRole('tab', { name: /response/i });
    fireEvent.click(responseTab);
    
    // Should show formatted response body
    expect(screen.getByText(/"html": "<div>Count: 1<\/div>"/i)).toBeInTheDocument();
    
    // Switch to headers tab
    const headersTab = screen.getByRole('tab', { name: /headers/i });
    fireEvent.click(headersTab);
    
    // Should show request and response headers
    expect(screen.getByText(/content-type/i)).toBeInTheDocument();
    expect(screen.getByText(/application\/json/i)).toBeInTheDocument();
    expect(screen.getByText(/x-livewire/i)).toBeInTheDocument();
    expect(screen.getByText(/x-csrf-token/i)).toBeInTheDocument();
  });
  
  test('filters requests by type', () => {
    render(
      <RequestMonitor 
        requests={mockRequests}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Find and click filter dropdown
    const filterDropdown = screen.getByRole('combobox', { name: /filter/i });
    fireEvent.change(filterDropdown, { target: { value: 'livewire' } });
    
    // Should only show Livewire requests
    const visibleRequests = screen.getAllByRole('listitem');
    expect(visibleRequests.length).toBe(1);
    expect(visibleRequests[0]).toHaveTextContent(/\/livewire\/message\/counter/i);
    
    // Change filter to error requests
    fireEvent.change(filterDropdown, { target: { value: 'error' } });
    
    // Should only show error requests (422)
    const errorRequests = screen.getAllByRole('listitem');
    expect(errorRequests.length).toBe(1);
    expect(errorRequests[0]).toHaveTextContent(/422/i);
  });
  
  test('sorts requests by timestamp', () => {
    render(
      <RequestMonitor 
        requests={mockRequests}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // By default, requests should be sorted by timestamp (newest first)
    const requestItems = screen.getAllByRole('listitem');
    
    // First request should be the most recent one
    expect(requestItems[0]).toHaveTextContent(/\/api\/posts/i);
    
    // Toggle sort order
    const sortButton = screen.getByRole('button', { name: /sort/i });
    fireEvent.click(sortButton);
    
    // Now requests should be sorted by timestamp (oldest first)
    const requestsAsc = screen.getAllByRole('listitem');
    
    // First request should be the oldest one
    expect(requestsAsc[0]).toHaveTextContent(/\/livewire\/message\/counter/i);
  });
  
  test('filters requests by search term', () => {
    render(
      <RequestMonitor 
        requests={mockRequests}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Find search input
    const searchInput = screen.getByPlaceholderText(/search requests/i);
    
    // Search for "users"
    fireEvent.change(searchInput, { target: { value: 'users' } });
    
    // Should only show requests containing "users"
    const visibleRequests = screen.getAllByRole('listitem');
    expect(visibleRequests.length).toBe(1);
    expect(visibleRequests[0]).toHaveTextContent(/\/api\/users/i);
  });
  
  test('clears requests when clear button is clicked', async () => {
    render(
      <RequestMonitor 
        requests={mockRequests}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Find and click clear button
    const clearButton = screen.getByRole('button', { name: /clear/i });
    fireEvent.click(clearButton);
    
    // Should send clear requests message
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.CLEAR_REQUESTS
        })
      );
    });
  });
  
  test('refreshes requests when refresh button is clicked', async () => {
    render(
      <RequestMonitor 
        requests={mockRequests}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Find and click refresh button
    const refreshButton = screen.getByRole('button', { name: /refresh/i });
    fireEvent.click(refreshButton);
    
    // Should send get requests message
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.GET_REQUESTS
        })
      );
    });
  });
  
  test('displays request timing information', () => {
    render(
      <RequestMonitor 
        requests={mockRequests}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Find and click the first request
    const firstRequest = screen.getAllByRole('listitem')[0];
    fireEvent.click(firstRequest);
    
    // Should show duration information
    expect(screen.getByText(/120ms/i)).toBeInTheDocument();
    
    // Should show timing breakdown
    expect(screen.getByTestId('timing-bar')).toBeInTheDocument();
  });
  
  test('shows syntax-highlighted request and response bodies', () => {
    render(
      <RequestMonitor 
        requests={mockRequests}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Find and click the first request
    const firstRequest = screen.getAllByRole('listitem')[0];
    fireEvent.click(firstRequest);
    
    // Request body should be syntax highlighted
    const requestBody = screen.getByTestId('request-body');
    expect(requestBody).toHaveClass('json-syntax');
    
    // Switch to response tab
    const responseTab = screen.getByRole('tab', { name: /response/i });
    fireEvent.click(responseTab);
    
    // Response body should be syntax highlighted
    const responseBody = screen.getByTestId('response-body');
    expect(responseBody).toHaveClass('json-syntax');
  });
  
  test('shows request error details for failed requests', () => {
    render(
      <RequestMonitor 
        requests={mockRequests}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Find and click the error request (422)
    const errorRequest = screen.getAllByRole('listitem')[2];
    fireEvent.click(errorRequest);
    
    // Should show error status
    expect(screen.getByText(/422 Unprocessable Entity/i)).toBeInTheDocument();
    
    // Switch to response tab
    const responseTab = screen.getByRole('tab', { name: /response/i });
    fireEvent.click(responseTab);
    
    // Should show error details
    expect(screen.getByText(/"errors"/i)).toBeInTheDocument();
    expect(screen.getByText(/"title"/i)).toBeInTheDocument();
    expect(screen.getByText(/"The title field is required\."/i)).toBeInTheDocument();
  });
  
  test('applies dark theme correctly', () => {
    // Mock body with dark theme class
    document.body.classList.add('theme-dark');
    
    render(
      <RequestMonitor 
        requests={mockRequests}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // The request monitor should have dark theme class
    const monitor = screen.getByTestId('request-monitor');
    expect(monitor).toHaveClass('dark-theme');
    
    // Clean up
    document.body.classList.remove('theme-dark');
  });
  
  test('shows different visual indicators for request methods', () => {
    render(
      <RequestMonitor 
        requests={mockRequests}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // POST requests should have post method class
    const postRequests = screen.getAllByTestId('method-POST');
    expect(postRequests.length).toBe(2);
    expect(postRequests[0]).toHaveClass('method-post');
    
    // GET requests should have get method class
    const getRequests = screen.getAllByTestId('method-GET');
    expect(getRequests.length).toBe(1);
    expect(getRequests[0]).toHaveClass('method-get');
  });
  
  test('shows different visual indicators for status codes', () => {
    render(
      <RequestMonitor 
        requests={mockRequests}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // 200 responses should have success class
    const successResponses = screen.getAllByTestId('status-200');
    expect(successResponses.length).toBe(2);
    expect(successResponses[0]).toHaveClass('status-success');
    
    // 422 responses should have error class
    const errorResponses = screen.getAllByTestId('status-422');
    expect(errorResponses.length).toBe(1);
    expect(errorResponses[0]).toHaveClass('status-error');
  });
});
