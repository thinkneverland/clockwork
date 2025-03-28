# Tapped Error Handling System

This document explains the standardized error handling system implemented across the Tapped codebase. The system is designed to provide consistent error handling, logging, and user feedback in both PHP (backend) and JavaScript (frontend/extension) environments.

## Table of Contents

1. [Overview](#overview)
2. [PHP Error Handling](#php-error-handling)
   - [Exception Classes](#exception-classes)
   - [ErrorHandler](#errorhandler)
   - [Middleware](#middleware)
3. [JavaScript Error Handling](#javascript-error-handling)
   - [Error Classes](#error-classes)
   - [ErrorHandler](#javascript-errorhandler)
   - [ErrorReporter](#errorreporter)
4. [Best Practices](#best-practices)
5. [Integration Examples](#integration-examples)

## Overview

The error handling system consists of these key components:

- **Custom exception/error classes**: Type-specific exceptions and errors for different failure scenarios
- **Central error handlers**: Utility classes for managing and processing errors consistently
- **Middleware**: Automatic exception handling for web requests
- **Error reporting**: Client-to-server error reporting for centralized logging
- **User-friendly messages**: Consistent user feedback without exposing sensitive details

## PHP Error Handling

### Exception Classes

All custom exceptions extend the base `TappedException` class which provides:

- Error codes generation
- User-friendly messages
- Context tracking
- Recoverability indicators

Available exception types:

| Exception | Purpose | Default Message |
|-----------|---------|-----------------|
| `TappedException` | Base exception class | An unexpected error occurred. Please try again later. |
| `DatabaseException` | Database-related failures | A database error occurred. Please try again later. |
| `NetworkException` | Network connectivity issues | A network error occurred. Please check your connection and try again. |
| `WebSocketException` | WebSocket connection failures | The realtime connection was interrupted. Please refresh the page. |
| `LivewireException` | Livewire component issues | There was a problem with a page component. Please refresh the page. |
| `SecurityException` | Security-related problems | A security issue was detected. Please contact support if this persists. |
| `ValidationException` | Input validation failures | Please check your input and try again. |
| `TimeoutException` | Operation timeout errors | The operation timed out. Please try again later. |

### Example Usage:

```php
use ThinkNeverland\Tapped\ErrorHandling\Exceptions\DatabaseException;

try {
    // Database operation
    $results = DB::table('users')->get();
} catch (\PDOException $e) {
    throw new DatabaseException(
        'Failed to query users table',
        [
            'user_message' => 'Could not load user data',
            'query' => 'SELECT * FROM users',
            'context' => ['user_id' => auth()->id()]
        ],
        $e // Original exception as previous
    );
}
```

### ErrorHandler

The `ErrorHandler` class provides static methods for handling and logging errors:

```php
use ThinkNeverland\Tapped\ErrorHandling\ErrorHandler;

// Report an exception
ErrorHandler::reportException(
    $exception,
    ErrorHandler::LEVEL_ERROR,
    ErrorHandler::CATEGORY_DATABASE,
    ['context' => 'additional information']
);

// Report a message
ErrorHandler::reportMessage(
    'Database connection warning',
    ErrorHandler::LEVEL_WARNING,
    ErrorHandler::CATEGORY_DATABASE
);

// Get error history (for debugging)
$recentErrors = ErrorHandler::getErrorHistory(10);

// Execute code with error handling
$result = ErrorHandler::withErrorHandling(
    function() {
        // Your code here
        return $result;
    },
    ErrorHandler::CATEGORY_WEBSOCKET,
    function($error) {
        // Custom error handler
        return fallbackValue();
    }
);
```

### Middleware

The `ErrorHandlingMiddleware` automatically catches exceptions and:

1. Logs them using the `ErrorHandler`
2. Returns appropriate HTTP responses
3. Renders user-friendly error pages for web requests
4. Returns structured JSON responses for API requests

## JavaScript Error Handling

### Error Classes

All custom error classes extend the base `TappedError` class which provides:

- Error codes
- User-friendly messages
- Context tracking
- Recoverability indicators

Available error types:

| Error | Purpose | Default Message |
|-------|---------|-----------------|
| `TappedError` | Base error class | An unexpected error occurred. Please try again later. |
| `NetworkError` | Network connectivity issues | A network error occurred. Please check your connection and try again. |
| `StorageError` | Storage/persistence failures | Failed to save data. Please try again. |
| `WebSocketError` | WebSocket connection failures | The realtime connection was interrupted. Please refresh the page. |
| `ValidationError` | Input validation failures | Please check your input and try again. |
| `InterprocessError` | Extension messaging errors | There was a communication error in the extension. |
| `SecurityError` | Security-related problems | A security issue was detected. Please contact support if this persists. |

### JavaScript ErrorHandler

The JS `ErrorHandler` provides methods for handling, logging, and tracking errors:

```javascript
import errorHandler, { ErrorLevel, ErrorCategory } from '../shared/utils/ErrorHandler';

// Capture a message
errorHandler.captureMessage(
  'Connection successfully established',
  ErrorLevel.INFO,
  ErrorCategory.WEBSOCKET
);

// Capture an exception
errorHandler.captureException(
  error,
  ErrorLevel.ERROR,
  ErrorCategory.STORAGE,
  { key: 'settings', action: 'save' }
);

// Execute code with error handling
const result = errorHandler.withErrorHandling(
  () => {
    // Your code here
    return result;
  },
  ErrorCategory.INTERPROCESS,
  (error) => {
    // Custom error handler
    return fallbackValue;
  }
);

// Get error history (for debugging)
const recentErrors = errorHandler.getErrorHistory(10);
```

### ErrorReporter

The `ErrorReporter` sends JavaScript errors to the server for centralized logging:

```javascript
import errorReporter from '../shared/utils/ErrorReporter';

// Configure the reporter
errorReporter.configure({
  endpoint: '/api/errors/report',
  enabled: true
});

// Report an error
errorReporter.reportError(
  error,
  'error',
  'storage',
  { context: 'additional information' }
);

// Report a message
errorReporter.reportMessage(
  'User performed a high-risk action',
  'warning',
  'security'
);

// Report an API error
errorReporter.reportApiError(
  '/api/users',
  { method: 'POST', body: data },
  error
);
```

## Best Practices

1. **Use specific error types** matching the failure scenario
2. **Include context** that will help with debugging
3. **Provide user-friendly messages** that guide users on next steps
4. **Maintain error codes** for tracking issues across systems
5. **Centralize error handling** using the provided utilities
6. **Log appropriately** using the correct error levels
7. **Think about recoverability** - can the user recover from this error?
8. **Handle errors at the right level** - not too deep, not too shallow

## Integration Examples

### PHP Controller Example

```php
public function store(Request $request)
{
    try {
        // Validate the input
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8'
        ]);
        
        // Create the user
        $user = User::create($validated);
        
        return response()->json(['success' => true, 'user' => $user]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        // Transform into our standard ValidationException
        throw ValidationException::fromValidator(
            $e->validator,
            'User creation validation failed'
        );
    } catch (\PDOException $e) {
        // Database errors
        throw new DatabaseException(
            'Failed to create user in database',
            [
                'user_message' => 'We could not create your account at this time. Please try again later.',
                'context' => ['email' => $request->email]
            ],
            $e
        );
    } catch (\Throwable $e) {
        // General error handling
        return ErrorHandler::handleException(
            $e,
            'We could not process your request at this time. Please try again later.',
            ErrorHandler::CATEGORY_INTERNAL
        );
    }
}
```

### JavaScript Component Example

```javascript
import errorHandler, { ErrorLevel, ErrorCategory } from '../utils/ErrorHandler';
import { NetworkError } from '../utils/Errors';

async function fetchUserProfile(userId) {
  try {
    const response = await fetch(`/api/users/${userId}`);
    
    if (!response.ok) {
      throw new NetworkError(
        `Failed to fetch user profile: ${response.status} ${response.statusText}`,
        {
          cause: new Error(`HTTP error ${response.status}`),
          statusCode: response.status,
          endpoint: `/api/users/${userId}`
        }
      );
    }
    
    return await response.json();
  } catch (error) {
    errorHandler.captureException(
      error,
      ErrorLevel.ERROR,
      ErrorCategory.NETWORK,
      { userId, action: 'fetchUserProfile' }
    );
    
    // Re-throw with user-friendly message
    throw error instanceof NetworkError 
      ? error 
      : new NetworkError('Failed to load user profile', { cause: error });
  }
}

// Usage with error handling wrapper
const loadProfile = errorHandler.withErrorHandling(
  async () => {
    return await fetchUserProfile(123);
  },
  ErrorCategory.NETWORK,
  (error) => {
    // Return fallback/empty profile
    return { id: 123, name: 'Unknown User', error: true };
  }
);
```

By following these patterns, we maintain consistent error handling across the entire Tapped codebase, making errors more predictable, easier to debug, and providing better feedback to users.
