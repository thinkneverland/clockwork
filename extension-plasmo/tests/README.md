# Tapped Extension Test Suite

This directory contains a comprehensive test suite for the Tapped browser extension, ensuring functionality, reliability, and cross-browser compatibility.

## Test Structure

The test suite is organized into the following categories:

### Core Utilities

- **BrowserUtils**: Tests for browser detection, API standardization, and feature detection
- **MessageUtils**: Tests for type-safe message passing between content scripts, background scripts, and DevTools panels
- **EventUtils**: Tests for standardized event handling with proper cleanup and cross-browser compatibility
- **DataProcessor**: Tests for efficient data processing, caching, worker thread utilization, and batch processing
- **StateOptimizer**: Tests for memoization, selective state updates, batched updates, and memory monitoring

### MCP Protocol Implementation

- **WebSocketWrapper**: Tests for WebSocket connection management, resource cleanup, and error handling
- **ConnectionHealthMonitor**: Tests for connection health tracking, zombie detection, and automatic reconnection
- **McpClient**: Tests for MCP protocol client with proper message handling and authentication

### UI Components

- **ThemeManager**: Tests for theme management including light/dark mode and high contrast support

### End-to-End Testing

- **CrossBrowserCompatibility**: Tests for consistent behavior across Chrome, Firefox, and Edge

## Running Tests

To run the tests, use the following npm scripts:

```bash
# Run all tests
npm test

# Run tests with coverage
npm run test:coverage

# Run only end-to-end tests
npm run test:e2e

# Run tests in watch mode during development
npm run test:watch
```

## Test Coverage Requirements

The test suite aims to maintain at least 70% coverage across all modules, with emphasis on the following areas:

1. **Cross-browser compatibility**: Ensuring consistent behavior across Chrome, Firefox, and Edge
2. **Connection reliability**: Proper WebSocket handling, reconnection strategies, and zombie detection
3. **Memory management**: Preventing memory leaks through proper resource cleanup
4. **Performance optimization**: Validating memoization, batching, and efficient data processing
5. **Type safety**: Ensuring proper TypeScript type checking throughout the codebase

## Adding New Tests

When adding new features or utilities to the extension, follow these steps:

1. Create a new test file in the appropriate directory
2. Write tests covering both positive and negative cases
3. Include tests for browser-specific behavior if applicable
4. Ensure proper cleanup of resources in tests
5. Run the test suite to verify all tests pass

## Mock Implementation

The test suite uses Jest mocks to simulate browser environments, WebSocket connections, and other external dependencies. Key mocks include:

- Browser API (Chrome, Firefox, Edge)
- WebSocket implementation
- Storage API
- Performance monitoring API
- DOM event handling

## Continuous Integration

These tests are designed to run in a CI environment to ensure consistent quality across all changes. The test suite runs automatically on pull requests and before releases.
