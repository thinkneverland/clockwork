# Tapped Extension Migration Plan: Webpack to Plasmo Framework

## Overview

This document outlines the implementation plan for migrating the Tapped browser extension from its current Webpack-based architecture to the Plasmo framework. The migration will enhance cross-browser compatibility, simplify the codebase, improve development workflows, and ensure better maintainability.

## Table of Contents

1. [Migration Goals](#migration-goals)
2. [Technical Requirements](#technical-requirements)
   - [Feature List](#feature-list)
   - [Core Requirements](#core-requirements)
   - [Enhanced Security Requirements](#enhanced-security-requirements)
   - [Performance Requirements](#performance-requirements)
3. [Current Architecture](#current-architecture)
4. [Plasmo Architecture](#plasmo-architecture)
5. [Migration Strategy](#migration-strategy)
6. [Implementation Steps](#implementation-steps)
7. [Testing Plan](#testing-plan)
8. [Project Timeline](#project-timeline)
9. [Appendix: File Mapping](#appendix-file-mapping)

## Migration Goals

- Simplify extension development with Plasmo's modern framework
- Improve cross-browser compatibility (Chrome, Firefox, Edge)
- Enhance performance for long-running WebSocket connections
- Reduce code complexity and maintenance overhead
- Leverage type safety with TypeScript
- Enable automated testing and CI/CD pipeline integration
- Improve developer experience with hot-reloading and faster builds
- Ensure seamless developer experience when transitioning from Clockwork

## Technical Requirements

### Feature List

#### Livewire Component Debugging

- **Component Inspector**
  - Real-time component discovery and listing
  - Component property inspection with type information
  - Methods listing with parameter information
  - Visual component highlighting in the web page (Alt + hover)
  - Component search and filtering capabilities
  - Component nesting hierarchy visualization
  - Track changes mode for property updates
  - Component mount/update/destroy lifecycle tracking

- **State Editor**
  - Inline property editing with validation
  - Property history tracking
  - Batch editing of multiple properties
  - Edit protection for computed properties
  - JSON/array structure editing support
  - Visual feedback on property updates
  - Undo/redo functionality for edits

- **Event Timeline**
  - Chronological visualization of all events
  - Color-coded event categorization
  - Event filtering by type and component
  - Event payload inspection
  - Event source tracing (component and code location)
  - Search functionality for events
  - Livewire lifecycle event tracking
  - Custom event tracking
  - Event dispatching capability

- **Time-Travel Debugging**
  - Component state snapshots
  - Named/labeled snapshots for organization
  - Snapshot comparison (diff view)
  - State restoration from snapshots
  - Automatic snapshots at key points
  - Snapshot export/import functionality
  - Timeline integration for snapshots

#### Database & Performance Monitoring

- **Query Analyzer**
  - SQL query logging with execution time
  - Query parameter binding display
  - Syntax highlighting for SQL
  - Slow query highlighting
  - Query origin tracking (component and file)
  - Query result preview
  - EXPLAIN query execution capability
  - Database transaction tracking

- **N+1 Query Detection**
  - Automatic detection of N+1 query patterns
  - Pattern visualization and grouping
  - Affected component identification
  - Suggested fixes with code examples
  - Performance impact estimation
  - Easy navigation to source code causing N+1 issues

#### Request & Network Monitoring

- **AJAX Request Tracking**
  - All HTTP request tracking
  - Special handling of Livewire AJAX calls
  - Request headers inspection
  - Request payload inspection
  - Response inspection with formatting
  - Response time tracking
  - Error handling and display
  - Request filtering by type and status

- **WebSocket Monitoring**
  - Connection status tracking
  - Message inspection (sent/received)
  - Reconnection handling
  - Latency monitoring
  - Message filtering

#### Visual Debugging Tools

- **Screenshots & Recording**
  - Page screenshot capability
  - Element-specific screenshots
  - Recording of state changes
  - Visual annotation tools
  - Export to image/GIF
  - Timeline integration

- **Visual Component Overlay**
  - Visual component boundaries
  - Component information tooltips
  - Click-to-inspect functionality
  - Togglable overlay display
  - Filter by component type

#### Integration Capabilities

- **IDE Integration Support**
  - Data export to IDE plugins
  - Jump-to-source functionality
  - API access for external tools
  - CLI tool integration

- **Webhook Notifications**
  - Event-based webhook triggers
  - Custom payload formatting
  - Integration with Slack, Discord, etc.
  - Filtering options for notifications

#### Advanced Features

- **Theme Support**
  - Light/dark mode toggle
  - Automatic theme detection
  - Custom theme capability
  - High contrast mode

- **Performance Monitoring**
  - Component render times
  - Memory usage tracking
  - CPU profiling
  - Asset loading performance
  - Performance recommendations
  - Historical performance trends
  - Comparative analysis

- **Error Tracking**
  - JavaScript error capture
  - PHP error tracking
  - Error context preservation
  - Stack trace visualization
  - Error playback for debugging

- **Method Execution**
  - Direct component method invocation
  - Parameter input forms
  - Method response visualization
  - Error handling for method execution
  - Method execution history

- **Livewire Version Support**
  - Compatibility with Livewire v1, v2, and v3+
  - Version-specific protocol adaptors
  - Feature detection for different versions
  - Automatic version detection

- **Data Export and Import**
  - Export debugging sessions (JSON/CSV)
  - Import previous debugging sessions
  - Session snapshots and restoration
  - Shareable debugging archives

- **Advanced Search and Filtering**
  - Global search across all panels
  - Regex-based filtering
  - Time-based filtering
  - Saved search/filter presets
  - Real-time filter updates

- **Notification System**
  - In-extension alert system
  - Configurable thresholds for alerts
  - Browser notification integration
  - Aggregation for high-frequency events

- **Multi-tab Support**
  - Tab-specific data isolation
  - Aggregated view for multiple tabs
  - Tab identifier in all records
  - Cross-tab feature comparison

- **Remote Debugging**
  - Secure remote connection capability
  - Token-based authentication
  - Remote session management
  - Network diagnostics for remote connections

- **Request/Response Modification**
  - Livewire request interception
  - Request modification before sending
  - Response simulation for testing
  - Conditional request modification rules

- **API Reference Panel**
  - Component API documentation access
  - Quick reference for methods/properties
  - Inline documentation links
  - Context-aware documentation

### Core Requirements

### Core Requirements

- **WebSocket Communication**
  - Reliable MCP protocol implementation
  - Auto-reconnection with exponential backoff
  - Connection health monitoring
  - Ping/pong mechanism for zombie detection
  - Message queue for offline operation
  - Message protocol versioning

- **Cross-Browser Compatibility**
  - Support for Chrome, Firefox, and Edge
  - Consistent UI across browsers
  - Browser-specific API abstraction
  - Responsive design for different DevTools sizes

- **Performance Optimization**
  - Minimal impact on application performance
  - Efficient data processing
  - Memory leak prevention
  - Optimized rendering of large data sets
  - Background processing for heavy operations

- **TypeScript & Code Quality**
  - Full TypeScript support throughout
  - Strict type checking
  - Code documentation
  - Component-based architecture
  - Unit and integration testing

### Enhanced Security Requirements

- **Data Protection**
  - Secure storage of sensitive data
  - Optional API token authentication
  - Protection against exposing production data
  - Sanitization of output data

- **Extension Permissions**
  - Minimal required permissions
  - Clear explanation of permission usage
  - Segmented permission requests

### Performance Requirements

- **Connection Management**
  - WebSocket connection pooling
  - Automatic connection health recovery
  - Resource cleanup on tab/window close
  - Bandwidth optimization with selective data transmission

- **UI Performance**
  - Virtualized lists for large data sets
  - Lazy loading of data
  - Efficient DOM updates
  - Performance metrics for monitoring extension impact

## Current Architecture

The current Tapped extension is built using:

- Webpack for bundling
- JavaScript (ES6+) 
- Custom browser compatibility layer
- Manually managed manifest files for different browsers
- Custom WebSocket connection management
- Event-based communication system

Key components include:
- Background service worker for managing connections
- Content scripts for page integration
- DevTools panel for UI
- Shared utilities and services

## Plasmo Architecture

The Plasmo framework provides:

- A unified development experience across browsers
- TypeScript support out of the box
- Simplified manifest management
- Automatic handling of browser-specific APIs
- Hot module replacement during development
- Built-in tools for packaging and distribution
- Declarative component-based approach

## Migration Strategy

We will adopt a complete rebuild approach rather than an incremental migration, for the following reasons:

1. Plasmo's architecture differs significantly from the current custom webpack setup
2. A clean implementation will allow us to properly leverage Plasmo's features
3. It's easier to ensure correctness by starting fresh than adapting legacy code
4. The migration provides an opportunity to implement best practices from the start

The migration will follow these high-level phases:
1. Set up the new Plasmo project structure
2. Implement core functionality module by module
3. Adapt the MCP protocol client for Plasmo
4. Rebuild the UI components
5. Implement cross-browser testing
6. Validate feature parity
7. Package for distribution

## Implementation Steps

### Phase 1: Project Setup and Basic Structure

1. Initialize a new Plasmo project:
   ```bash
   pnpm create plasmo tapped-extension
   ```

2. Set up TypeScript configuration
   ```bash
   pnpm add -D typescript @types/chrome @types/firefox-webext-browser
   ```

3. Configure project structure:
   ```
   tapped-extension/
   ├── src/
   │   ├── contents/       # Content scripts
   │   ├── background.ts   # Background service worker
   │   ├── popup.tsx       # Extension popup
   │   ├── options.tsx     # Options page
   │   ├── devtools.html   # DevTools entry
   │   ├── panel.tsx       # DevTools panel
   │   └── assets/         # Images and static assets
   ├── lib/
   │   ├── mcp/            # MCP protocol implementation
   │   ├── store/          # State management
   │   ├── utils/          # Utilities
   │   └── types/          # TypeScript types
   ├── package.json
   └── tsconfig.json
   ```

4. Set up basic dependencies:
   ```json
   {
     "dependencies": {
       "@sentry/browser": "^9.9.0",
       "highlight.js": "^11.4.0",
       "lodash": "^4.17.21",
       "sql-formatter": "^4.0.2"
     },
     "devDependencies": {
       "plasmo": "latest",
       "typescript": "^5.0.0",
       "@types/chrome": "^0.0.246",
       "@types/firefox-webext-browser": "^120.0.0",
       "@types/react": "^18.2.0",
       "@types/node": "^20.0.0",
       "@types/lodash": "^4.14.202",
       "jest": "^29.0.0",
       "@types/jest": "^29.0.0"
     }
   }
   ```

### Phase 2: Core Services Implementation

1. Implement MCP Protocol Client in TypeScript:
   ```typescript
   // lib/mcp/McpClient.ts
   export class McpClient {
     // Ported from existing McpClient.js
     // with TypeScript enhancements
   }
   ```

2. Implement Connection Registry with health monitoring:
   ```typescript
   // lib/mcp/ConnectionRegistry.ts
   export class ConnectionRegistry {
     // Ported from existing ConnectionRegistry.js
     // Enhanced with TypeScript types
   }
   ```

3. Create Connection Health Monitor:
   ```typescript
   // lib/mcp/ConnectionHealthMonitor.ts
   export class ConnectionHealthMonitor {
     // Enhanced WebSocket health monitoring
     // Ping/pong mechanisms
     // Zombie connection detection
     // Connection metrics tracking
   }
   ```

4. Implement WebSocket Wrapper:
   ```typescript
   // lib/mcp/WebSocketWrapper.ts
   export class WebSocketWrapper {
     // Proper cleanup of WebSocket resources
     // Prevent memory leaks
     // Enhanced error handling and recovery
   }
   ```

5. Create browser abstraction utilities:
   ```typescript
   // lib/utils/BrowserUtils.ts
   // Replaces the current BrowserAPI.js but leverages Plasmo's
   // cross-browser capabilities
   ```

6. Implement Event Utils:
   ```typescript
   // lib/utils/EventUtils.ts
   // Standardized event handling
   // Proper event listener cleanup
   // Cross-browser compatibility
   ```

7. Create Data Processor for efficient data handling:
   ```typescript
   // lib/utils/DataProcessor.ts
   // Efficient data processing
   // Smart caching
   // Optimized JSON handling
   // Batch processing capabilities
   ```

8. Implement State Optimizer:
   ```typescript
   // lib/utils/StateOptimizer.ts
   // Memoization utilities
   // Selective state updates
   // Batched updates
   // Memory monitoring
   ```

### Phase 3: Background Service Worker

1. Implement background service worker:
   ```typescript
   // background.ts
   import { ConnectionRegistry } from './lib/mcp/ConnectionRegistry'
   import { storage } from '@plasmohq/storage'
   
   // Background service worker implementation
   // Handles connection management, health monitoring
   // and message routing
   ```

2. Implement storage service leveraging Plasmo's storage API:
   ```typescript
   // lib/store/Storage.ts
   import { Storage } from '@plasmohq/storage'
   
   export const storage = new Storage()
   // Implement methods to interact with extension storage
   ```

### Phase 4: Content Scripts

1. Implement main content script:
   ```typescript
   // contents/detector.ts
   import type { PlasmoCSConfig } from 'plasmo'
   
   export const config: PlasmoCSConfig = {
     matches: ["<all_urls>"],
     run_at: "document_start"
   }
   
   // Livewire detection and initialization logic
   ```

2. Implement component highlighter:
   ```typescript
   // contents/highlighter.tsx
   import type { PlasmoCSConfig, PlasmoGetInlineAnchor } from 'plasmo'
   
   export const config: PlasmoCSConfig = {
     matches: ["<all_urls>"]
   }
   
   // Component highlighting implementation
   // Visual overlay for component boundaries
   // Component information tooltips
   ```

3. Implement state inspector:
   ```typescript
   // contents/inspector.ts
   import type { PlasmoCSConfig } from 'plasmo'
   
   export const config: PlasmoCSConfig = {
     matches: ["<all_urls>"]
   }
   
   // Component state inspection logic
   // Property tracking
   // State change detection
   ```

4. Implement event tracker:
   ```typescript
   // contents/event-tracker.ts
   import type { PlasmoCSConfig } from 'plasmo'
   
   export const config: PlasmoCSConfig = {
     matches: ["<all_urls>"]
   }
   
   // Track and capture Livewire events
   // Monitor custom events
   // Capture event payloads
   ```

5. Implement request monitor:
   ```typescript
   // contents/request-monitor.ts
   import type { PlasmoCSConfig } from 'plasmo'
   
   export const config: PlasmoCSConfig = {
     matches: ["<all_urls>"]
   }
   
   // Intercept and log HTTP requests
   // Special handling for Livewire AJAX
   // Capture request/response data
   ```

6. Implement screenshot tool:
   ```typescript
   // contents/screenshot.ts
   import type { PlasmoCSConfig } from 'plasmo'
   
   export const config: PlasmoCSConfig = {
     matches: ["<all_urls>"]
   }
   
   // Screenshot capture functionality
   // Element selector capabilities
   // Recording capability
   ```

7. Implement state editor:
   ```typescript
   // contents/state-editor.ts
   import type { PlasmoCSConfig } from 'plasmo'
   
   export const config: PlasmoCSConfig = {
     matches: ["<all_urls>"]
   }
   
   // Component property editing
   // Update validation
   // Property change application
   ```

### Phase 5: DevTools Panel

1. Create DevTools entry point:
   ```html
   <!-- devtools.html -->
   <!DOCTYPE html>
   <html>
     <head>
       <script src="./devtools.js"></script>
     </head>
   </html>
   ```

2. Create DevTools initialization script:
   ```typescript
   // devtools.ts
   chrome.devtools.panels.create(
     "Tapped",
     "/assets/icon-16.png",
     "/panel.html"
   )
   ```

3. Implement DevTools panel UI:
   ```tsx
   // panel.tsx
   import React, { useEffect, useState } from 'react'
   import './panel.css'
   
   // Panel component implementation
   ```

4. Create Components Tab:
   ```tsx
   // panel/components/ComponentsTab.tsx
   // Component listing
   // Property inspector
   // State editor
   // Component search
   // Component highlighting
   ```

5. Create Queries Tab:
   ```tsx
   // panel/queries/QueriesTab.tsx
   // Query listing
   // SQL formatting and highlighting
   // N+1 query detection
   // Slow query highlighting
   // EXPLAIN capability
   ```

6. Create Events Tab:
   ```tsx
   // panel/events/EventsTab.tsx
   // Event timeline
   // Event filtering
   // Event payload inspector
   // Event source tracking
   ```

7. Create Requests Tab:
   ```tsx
   // panel/requests/RequestsTab.tsx
   // HTTP request listing
   // Request/response inspector
   // Headers and payload formatting
   // Filter by type and status
   ```

8. Create Timeline Tab:
   ```tsx
   // panel/timeline/TimelineTab.tsx
   // Chronological event visualization
   // Component lifecycle tracking
   // Request timing visualization
   // Snapshot markers
   ```

9. Create Snapshots Tab:
   ```tsx
   // panel/snapshots/SnapshotsTab.tsx
   // Snapshot listing
   // Snapshot comparison (diff view)
   // Snapshot restoration
   // Snapshot management
   ```

10. Create Settings UI:
    ```tsx
    // panel/settings/SettingsPanel.tsx
    // Theme selection
    // Connection configuration
    // Display preferences
    // Feature toggles
    ```

### Phase 6: Popup and Options Pages

1. Create popup component:
   ```tsx
   // popup.tsx
   import React from 'react'
   import './popup.css'
   
   function IndexPopup() {
     // Popup UI implementation
   }
   
   export default IndexPopup
   ```

2. Create options component:
   ```tsx
   // options.tsx
   import React from 'react'
   import './options.css'
   
   function OptionsPage() {
     // Options UI implementation
   }
   
   export default OptionsPage
   ```

### Phase 7: Message Handling & Data Processing

1. Implement message passing utilities:
   ```typescript
   // lib/utils/MessageUtils.ts
   // Type-safe message passing between 
   // content, background, and devtools
   ```

2. Create message routing system:
   ```typescript
   // lib/utils/MessageRouter.ts
   // Message routing implementation with
   // TypeScript type checking
   ```

3. Implement Query Processor:
   ```typescript
   // lib/processors/QueryProcessor.ts
   // SQL parsing and formatting
   // N+1 query detection algorithms
   // Query performance analysis
   // Suggested fixes generation
   ```

4. Implement Component Processor:
   ```typescript
   // lib/processors/ComponentProcessor.ts
   // Component hierarchy processing
   // Property change tracking
   // Component state diffing
   // Property validation
   ```

5. Implement Event Processor:
   ```typescript
   // lib/processors/EventProcessor.ts
   // Event timeline management
   // Event categorization
   // Event source tracking
   // Event filtering logic
   ```

6. Implement Request Processor:
   ```typescript
   // lib/processors/RequestProcessor.ts
   // Request filtering and categorization
   // Response formatting
   // Performance metrics calculation
   // Livewire request special handling
   ```

7. Implement Snapshot Manager:
   ```typescript
   // lib/processors/SnapshotManager.ts
   // Snapshot creation and restoration
   // Snapshot comparison algorithms
   // Efficient snapshot storage
   // Snapshot import/export
   ```

### Phase 8: Testing Setup

1. Configure Jest for testing:
   ```js
   // jest.config.js
   module.exports = {
     preset: 'ts-jest',
     testEnvironment: 'jsdom',
     // ...other config
   }
   ```

2. Create initial tests:
   ```typescript
   // tests/McpClient.test.ts
   // MCP client tests
   ```

## Testing Plan

### Unit Testing
- Test each module in isolation
- Verify MCP protocol client functionality
- Test browser abstraction utilities
- Verify storage operations
- Test database query parsing and N+1 detection
- Validate state editing logic
- Test snapshot creation and restoration
- Verify event processing algorithms

### Integration Testing
- Test communication between different extension components
- Verify WebSocket connection and health monitoring
- Test message routing and handling
- Validate component inspector with mock components
- Test query analyzer with sample database queries
- Verify event timeline with mock events
- Test screenshot and recording functionality

### End-to-End Testing
- Test with actual Laravel Livewire applications
- Verify real-time state updates
- Test component editing functionality
- Verify event logging and timeline
- Test across different browsers (Chrome, Firefox, Edge)
- Validate N+1 query detection with real-world scenarios
- Test WebSocket server interaction
- Verify time-travel debugging with state restoration

### Performance Testing
- Measure memory usage during long sessions
- Test WebSocket reconnection capabilities
- Verify large dataset handling efficiency
- Benchmark UI rendering performance
- Measure impact on web page performance
- Test with high-frequency updates
- Validate connection health monitoring effectiveness
- Performance comparison with original extension

## Project Timeline

| Phase | Description | Duration |
|-------|-------------|----------|
| 1 | Project Setup | 1 day |
| 2 | Core Services Implementation | 2 days |
| 3 | Background Service Worker | 1 day |
| 4 | Content Scripts | 2 days |
| 5 | DevTools Panel | 2 days |
| 6 | Popup and Options Pages | 1 day |
| 7 | Message Handling | 1 day |
| 8 | Testing | 2 days |
| 9 | Final Adjustments | 1 day |
| 10 | Documentation | 1 day |

**Total Estimated Duration**: 2 weeks

## Appendix: File Mapping

This section maps current files to their Plasmo equivalents to ensure we don't miss any functionality during migration.

| Current | Plasmo Equivalent | Notes |
|---------|-------------------|-------|
| manifest.json | package.json + plasmo.json | Manifest properties will be split between these files |
| src/background/background.js | src/background.ts | Background service worker |
| src/content/content.js | src/contents/*.ts | Split into multiple content scripts |
| src/shared/mcp/McpClient.js | lib/mcp/McpClient.ts | MCP protocol client |
| src/shared/connections/ConnectionRegistry.js | lib/mcp/ConnectionRegistry.ts | Connection management |
| src/shared/browser/BrowserAPI.js | Built-in Plasmo utilities | Replaced by Plasmo's browser API abstraction |
| src/shared/browser/EventUtils.js | lib/utils/EventUtils.ts | Cross-browser event handling |
| src/shared/utils/RetryUtils.js | lib/utils/RetryUtils.ts | Connection retry logic |
| src/devtools/panel.js | src/panel.tsx | DevTools panel as React component |
| src/devtools/panel/*.js | src/panel/*/*.tsx | Panel tabs as React components |
| src/popup/popup.html | src/popup.tsx | Popup as React component |
| src/shared/store/*.js | lib/store/*.ts | State management |
| src/shared/defaults/*.js | lib/config/*.ts | Default configuration |
| src/shared/ui/*.js | lib/ui/*.tsx | Shared UI components |
| src/shared/mcp/MessageFormatter.js | lib/mcp/MessageFormatter.ts | MCP message formatting |
| src/shared/mcp/handlers/*.js | lib/mcp/handlers/*.ts | MCP message handlers |

## Feature Parity Checklist

This checklist ensures we don't miss any features during the migration:

### Core Features
- [ ] Real-time Livewire component state inspection
- [ ] Inline editing of component properties
- [ ] Component method execution
- [ ] Component highlighting in page
- [ ] Livewire event monitoring
- [ ] Database query logging and analysis
- [ ] N+1 query detection
- [ ] HTTP request monitoring
- [ ] WebSocket communication with MCP server
- [ ] Time-travel debugging with snapshots
- [ ] Event timeline visualization
- [ ] Theme support (light/dark)

### Enhanced Features
- [ ] Screenshot and recording capabilities
- [ ] Component visual overlay
- [ ] Performance monitoring and metrics
- [ ] Connection health monitoring
- [ ] Batch property editing
- [ ] Query EXPLAIN functionality
- [ ] Memory leak detection
- [ ] WebSocket heartbeat mechanism
- [ ] Auto-reconnection with exponential backoff
- [ ] Data export and import functionality
- [ ] Advanced search and filtering
- [ ] Notification system for important events
- [ ] Multi-tab debugging support
- [ ] Remote debugging capabilities
- [ ] Request/response modification
- [ ] API reference integration

### Livewire Support
- [ ] Livewire v1 compatibility
- [ ] Livewire v2 compatibility
- [ ] Livewire v3+ compatibility
- [ ] Automatic version detection
- [ ] Version-specific optimizations

### Performance Optimizations
- [ ] Efficient data processing
- [ ] Memory usage optimizations
- [ ] Connection pooling
- [ ] Worker thread utilization
- [ ] Virtualized list rendering
- [ ] Selective data transmission
- [ ] Resource cleanup and leak prevention

### Cross-Browser Support
- [ ] Chrome compatibility
- [ ] Firefox compatibility
- [ ] Edge compatibility
- [ ] Browser-specific adaptations
- [ ] Consistent UI across browsers
- [ ] Standardized event handling
