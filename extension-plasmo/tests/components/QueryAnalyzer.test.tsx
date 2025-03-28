/**
 * QueryAnalyzer component tests
 * 
 * Tests for the component that analyzes database queries, formats SQL,
 * detects N+1 query patterns, and provides performance recommendations.
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import { QueryAnalyzer } from '../../components/QueryAnalyzer';
import * as MessageUtils from '../../lib/utils/MessageUtils';
import * as QueryProcessor from '../../lib/processors/QueryProcessor';

// Mock dependencies
jest.mock('../../lib/utils/MessageUtils', () => ({
  MessageActionType: {
    GET_QUERIES: 'GET_QUERIES',
    CLEAR_QUERIES: 'CLEAR_QUERIES',
    EXPLAIN_QUERY: 'EXPLAIN_QUERY'
  },
  createMessage: jest.fn((action, data) => ({ action, data })),
  sendThroughPort: jest.fn()
}));

jest.mock('../../lib/processors/QueryProcessor', () => ({
  formatQuery: jest.fn(query => `/* Formatted */\n${query}`),
  detectN1Patterns: jest.fn(queries => {
    // Simple mock implementation that returns patterns if there are similar queries
    if (queries.length > 2 && queries[0].query.includes('SELECT')) {
      return [{
        pattern: 'SELECT * FROM users WHERE id = ?',
        occurrences: queries.length,
        similar_queries: queries.map(q => q.id),
        tables: ['users'],
        suggestion: 'Consider eager loading with a JOIN or using a single query with WHERE IN clause'
      }];
    }
    return [];
  }),
  categorizeQueries: jest.fn(queries => {
    // Simple mock implementation that categorizes by type
    return {
      select: queries.filter(q => q.query.includes('SELECT')),
      insert: queries.filter(q => q.query.includes('INSERT')),
      update: queries.filter(q => q.query.includes('UPDATE')),
      delete: queries.filter(q => q.query.includes('DELETE')),
      other: queries.filter(q => !q.query.includes('SELECT') && 
                                !q.query.includes('INSERT') && 
                                !q.query.includes('UPDATE') && 
                                !q.query.includes('DELETE'))
    };
  }),
  analyzeQueryPerformance: jest.fn(queries => {
    // Simple mock implementation that flags slow queries
    return queries.map(q => ({
      ...q,
      isSlowQuery: q.duration > 100,
      indexStatus: q.duration > 100 ? 'missing_index' : 'optimal'
    }));
  })
}));

describe('QueryAnalyzer', () => {
  // Sample query data for testing
  const mockQueries = [
    {
      id: '1',
      query: 'SELECT * FROM users WHERE id = 1',
      duration: 5,
      connection: 'mysql',
      bindings: ['1'],
      timestamp: Date.now() - 5000,
      source: {
        file: 'UserController.php',
        line: 45,
        componentId: 'comp-1',
        componentName: 'UserProfile'
      }
    },
    {
      id: '2',
      query: 'SELECT * FROM users WHERE id = 2',
      duration: 5,
      connection: 'mysql',
      bindings: ['2'],
      timestamp: Date.now() - 4000,
      source: {
        file: 'UserController.php',
        line: 45,
        componentId: 'comp-1',
        componentName: 'UserProfile'
      }
    },
    {
      id: '3',
      query: 'SELECT * FROM users WHERE id = 3',
      duration: 5,
      connection: 'mysql',
      bindings: ['3'],
      timestamp: Date.now() - 3000,
      source: {
        file: 'UserController.php',
        line: 45,
        componentId: 'comp-1',
        componentName: 'UserProfile'
      }
    },
    {
      id: '4',
      query: 'INSERT INTO logs (message) VALUES (?)',
      duration: 2,
      connection: 'mysql',
      bindings: ['User viewed'],
      timestamp: Date.now() - 2000,
      source: {
        file: 'LogService.php',
        line: 23,
        componentId: 'comp-2',
        componentName: 'Logger'
      }
    },
    {
      id: '5',
      query: 'SELECT * FROM posts WHERE user_id = 1 LIMIT 5',
      duration: 150, // Slow query
      connection: 'mysql',
      bindings: ['1'],
      timestamp: Date.now() - 1000,
      source: {
        file: 'PostController.php',
        line: 67,
        componentId: 'comp-3',
        componentName: 'PostList'
      }
    }
  ];
  
  const mockPort = { name: 'tapped-devtools-panel' };
  
  beforeEach(() => {
    jest.clearAllMocks();
  });
  
  test('renders loading state when queries are loading', () => {
    render(
      <QueryAnalyzer 
        queries={[]}
        loading={true}
        port={mockPort as any}
      />
    );
    
    expect(screen.getByText(/loading queries/i)).toBeInTheDocument();
  });
  
  test('renders empty state when no queries are available', () => {
    render(
      <QueryAnalyzer 
        queries={[]}
        loading={false}
        port={mockPort as any}
      />
    );
    
    expect(screen.getByText(/no queries recorded/i)).toBeInTheDocument();
  });
  
  test('renders query list with summary information', () => {
    render(
      <QueryAnalyzer 
        queries={mockQueries}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Should show query count
    expect(screen.getByText(/5 queries/i)).toBeInTheDocument();
    
    // Should show all queries
    const queryItems = screen.getAllByRole('listitem');
    expect(queryItems.length).toBe(5);
    
    // Should show query types
    expect(screen.getAllByText(/SELECT/i).length).toBe(4); // 3 selects plus one in slow query section
    expect(screen.getByText(/INSERT/i)).toBeInTheDocument();
    
    // Should show duration
    expect(screen.getByText(/5ms/i)).toBeInTheDocument();
    expect(screen.getByText(/2ms/i)).toBeInTheDocument();
    expect(screen.getByText(/150ms/i)).toBeInTheDocument();
    
    // Should highlight slow queries
    const slowQuerySection = screen.getByText(/slow queries/i);
    expect(slowQuerySection).toBeInTheDocument();
    
    // Should show component names
    expect(screen.getByText(/UserProfile/i)).toBeInTheDocument();
    expect(screen.getByText(/Logger/i)).toBeInTheDocument();
    expect(screen.getByText(/PostList/i)).toBeInTheDocument();
  });
  
  test('detects and displays N+1 query patterns', () => {
    // Mock the N+1 detection to return a pattern
    QueryProcessor.detectN1Patterns.mockReturnValueOnce([
      {
        pattern: 'SELECT * FROM users WHERE id = ?',
        occurrences: 3,
        similar_queries: ['1', '2', '3'],
        tables: ['users'],
        suggestion: 'Consider eager loading with a JOIN or using a single query with WHERE IN clause'
      }
    ]);
    
    render(
      <QueryAnalyzer 
        queries={mockQueries}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Should show N+1 section
    expect(screen.getByText(/N\+1 Query Patterns/i)).toBeInTheDocument();
    
    // Should show the detected pattern
    expect(screen.getByText(/SELECT \* FROM users WHERE id = \?/i)).toBeInTheDocument();
    
    // Should show occurrences
    expect(screen.getByText(/3 occurrences/i)).toBeInTheDocument();
    
    // Should show suggestion
    expect(screen.getByText(/Consider eager loading/i)).toBeInTheDocument();
  });
  
  test('expands query to show details when clicked', () => {
    render(
      <QueryAnalyzer 
        queries={mockQueries}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Find and click the first query
    const firstQuery = screen.getAllByRole('listitem')[0];
    fireEvent.click(firstQuery);
    
    // Should show expanded details
    expect(screen.getByText(/bindings/i)).toBeInTheDocument();
    expect(screen.getByText(/UserController.php:45/i)).toBeInTheDocument();
    expect(screen.getByText(/mysql/i)).toBeInTheDocument();
    
    // Should show formatted query
    expect(screen.getByText(/\/\* Formatted \*\//i)).toBeInTheDocument();
  });
  
  test('filters queries by type', () => {
    render(
      <QueryAnalyzer 
        queries={mockQueries}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Find and click filter dropdown
    const filterDropdown = screen.getByRole('combobox', { name: /filter/i });
    fireEvent.change(filterDropdown, { target: { value: 'insert' } });
    
    // Should only show INSERT queries
    const visibleQueries = screen.getAllByRole('listitem');
    expect(visibleQueries.length).toBe(1);
    expect(visibleQueries[0]).toHaveTextContent(/INSERT INTO logs/i);
  });
  
  test('filters queries by component', () => {
    render(
      <QueryAnalyzer 
        queries={mockQueries}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Find component filter
    const componentFilter = screen.getByLabelText(/component/i);
    fireEvent.change(componentFilter, { target: { value: 'PostList' } });
    
    // Should only show queries from PostList component
    const visibleQueries = screen.getAllByRole('listitem');
    expect(visibleQueries.length).toBe(1);
    expect(visibleQueries[0]).toHaveTextContent(/posts WHERE user_id/i);
  });
  
  test('sorts queries by duration', () => {
    render(
      <QueryAnalyzer 
        queries={mockQueries}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Find and click sort dropdown
    const sortDropdown = screen.getByRole('combobox', { name: /sort/i });
    fireEvent.change(sortDropdown, { target: { value: 'duration' } });
    
    // Queries should be sorted by duration (longest first)
    const queryItems = screen.getAllByRole('listitem');
    
    // First query should be the slow one
    expect(queryItems[0]).toHaveTextContent(/150ms/i);
    
    // Change sort to duration-asc
    fireEvent.change(sortDropdown, { target: { value: 'duration-asc' } });
    
    // Queries should be sorted by duration (shortest first)
    const queriesAsc = screen.getAllByRole('listitem');
    
    // First query should be the fastest one
    expect(queriesAsc[0]).toHaveTextContent(/2ms/i);
  });
  
  test('requests EXPLAIN query when explain button is clicked', async () => {
    render(
      <QueryAnalyzer 
        queries={mockQueries}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Find and click the first query
    const firstQuery = screen.getAllByRole('listitem')[0];
    fireEvent.click(firstQuery);
    
    // Find and click explain button
    const explainButton = screen.getByRole('button', { name: /explain/i });
    fireEvent.click(explainButton);
    
    // Should send explain query message
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.EXPLAIN_QUERY,
          data: expect.objectContaining({
            queryId: '1'
          })
        })
      );
    });
  });
  
  test('clears queries when clear button is clicked', async () => {
    render(
      <QueryAnalyzer 
        queries={mockQueries}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Find and click clear button
    const clearButton = screen.getByRole('button', { name: /clear/i });
    fireEvent.click(clearButton);
    
    // Should send clear queries message
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.CLEAR_QUERIES
        })
      );
    });
  });
  
  test('refreshes queries when refresh button is clicked', async () => {
    render(
      <QueryAnalyzer 
        queries={mockQueries}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Find and click refresh button
    const refreshButton = screen.getByRole('button', { name: /refresh/i });
    fireEvent.click(refreshButton);
    
    // Should send get queries message
    await waitFor(() => {
      expect(MessageUtils.sendThroughPort).toHaveBeenCalledWith(
        mockPort,
        expect.objectContaining({
          action: MessageUtils.MessageActionType.GET_QUERIES
        })
      );
    });
  });
  
  test('performs query search with provided text', () => {
    render(
      <QueryAnalyzer 
        queries={mockQueries}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Find search input
    const searchInput = screen.getByPlaceholderText(/search queries/i);
    
    // Search for "posts"
    fireEvent.change(searchInput, { target: { value: 'posts' } });
    
    // Should only show queries containing "posts"
    const visibleQueries = screen.getAllByRole('listitem');
    expect(visibleQueries.length).toBe(1);
    expect(visibleQueries[0]).toHaveTextContent(/posts WHERE user_id/i);
  });
  
  test('displays aggregated statistics for queries', () => {
    render(
      <QueryAnalyzer 
        queries={mockQueries}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Should show total query count
    expect(screen.getByText(/5 queries/i)).toBeInTheDocument();
    
    // Should show total duration
    expect(screen.getByText(/167ms total/i)).toBeInTheDocument();
    
    // Should show average duration
    expect(screen.getByText(/33.4ms avg/i)).toBeInTheDocument();
    
    // Should show query type breakdown
    expect(screen.getByText(/4 SELECT/i)).toBeInTheDocument();
    expect(screen.getByText(/1 INSERT/i)).toBeInTheDocument();
  });
  
  test('applies dark theme correctly', () => {
    // Mock body with dark theme class
    document.body.classList.add('theme-dark');
    
    render(
      <QueryAnalyzer 
        queries={mockQueries}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // The analyzer should have dark theme class
    const analyzer = screen.getByTestId('query-analyzer');
    expect(analyzer).toHaveClass('dark-theme');
    
    // Clean up
    document.body.classList.remove('theme-dark');
  });
  
  test('shows formatted SQL with syntax highlighting', () => {
    render(
      <QueryAnalyzer 
        queries={mockQueries}
        loading={false}
        port={mockPort as any}
      />
    );
    
    // Find and click the first query
    const firstQuery = screen.getAllByRole('listitem')[0];
    fireEvent.click(firstQuery);
    
    // Should format query
    expect(QueryProcessor.formatQuery).toHaveBeenCalled();
    
    // Should show formatted query
    const formattedQuery = screen.getByText(/\/\* Formatted \*\//i);
    expect(formattedQuery).toBeInTheDocument();
    
    // SQL should have syntax highlighting
    const sqlContainer = screen.getByTestId('sql-container');
    expect(sqlContainer).toHaveClass('sql-syntax');
  });
});
