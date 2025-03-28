<?php

namespace ThinkNeverland\Tapped\ErrorHandling\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response as ResponseFacade;
use ThinkNeverland\Tapped\ErrorHandling\ErrorHandler;
use ThinkNeverland\Tapped\ErrorHandling\Exceptions\TappedException;
use ThinkNeverland\Tapped\ErrorHandling\Exceptions\WebSocketException;
use Throwable;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Middleware for consistent error handling across all requests
 * 
 * Enhanced with WebSocket-specific error handling, rate limiting for repeated errors,
 * and circuit breaker pattern implementation for improved system stability.
 */
class ErrorHandlingMiddleware
{
    /**
     * Maximum number of similar errors allowed in a time window before rate limiting
     * 
     * @var int
     */
    protected int $errorRateLimit = 10;
    
    /**
     * Time window for error rate limiting in seconds
     * 
     * @var int
     */
    protected int $errorRateWindow = 60;
    
    /**
     * Circuit breaker threshold - number of consecutive errors before circuit opens
     * 
     * @var int
     */
    protected int $circuitBreakerThreshold = 5;
    
    /**
     * Circuit breaker recovery time in seconds
     * 
     * @var int
     */
    protected int $circuitBreakerRecoveryTime = 30;
    
    /**
     * Handle an incoming request
     *
     * @param Request $request The request instance
     * @param Closure $next Next middleware
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if we're in a circuit breaker state for this endpoint
        if ($this->isCircuitOpen($request)) {
            return $this->circuitBreakerResponse($request);
        }
        // Set a temporary increased execution time limit for this request
        // to prevent timeouts in complex view compilation
        $originalTimeLimit = ini_get('max_execution_time');
        if ((int)$originalTimeLimit > 0) { // Only modify if there is a limit
            ini_set('max_execution_time', '60'); // 60 seconds instead of 30
        }
        // Add a unique request ID for tracing errors
        if (!$request->hasHeader('X-Request-ID')) {
            $requestId = uniqid('req-', true);
            $request->headers->set('X-Request-ID', $requestId);
        }
        
        // Add timestamp for performance tracking
        $request->headers->set('X-Request-Start-Time', microtime(true));
        
        // Continue with the request
        try {
            $response = $next($request);
            
            // Clear error count on successful requests to reset circuit breaker
            $this->recordSuccessfulRequest($request);
            
            // Add the request ID to the response for client-side tracking
            if ($response instanceof Response || $response instanceof StreamedResponse) {
                if (!$response->headers->has('X-Request-ID') && $request->hasHeader('X-Request-ID')) {
                    $response->headers->set('X-Request-ID', $request->header('X-Request-ID'));
                }
                
                // Add performance metrics if applicable
                if ($request->hasHeader('X-Request-Start-Time')) {
                    $duration = (microtime(true) - $request->header('X-Request-Start-Time')) * 1000; // ms
                    $response->headers->set('X-Request-Duration', round($duration, 2));
                }
            }
            
            return $response;
        } catch (Throwable $exception) {
            // Record the error for rate limiting and circuit breaker
            $this->recordError($request, $exception);
            
            // Handle based on exception type
            return $this->handleException($request, $exception);
        }
    }
    
    /**
     * Handle the caught exception
     *
     * @param Request $request The request instance
     * @param Throwable $exception The exception
     * @return Response
     */
    protected function handleException(Request $request, Throwable $exception)
    {
        // Check for rate limiting of errors
        if ($this->isRateLimited($request, $exception)) {
            Log::warning('Error rate limit exceeded', [
                'request_id' => $request->header('X-Request-ID'),
                'url' => $request->fullUrl(),
                'error_type' => get_class($exception)
            ]);
        }
        // Get appropriate category based on exception type
        $category = $this->getCategoryFromException($exception);
        
        // Log the exception with enhanced context
        $context = [
            'request_id' => $request->header('X-Request-ID'),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
            'session_id' => $request->session()->getId() ?? 'none',
            'route' => $request->route() ? $request->route()->getName() : 'unknown',
        ];
        
        // Add performance data if available
        if ($request->hasHeader('X-Request-Start-Time')) {
            $duration = (microtime(true) - $request->header('X-Request-Start-Time')) * 1000; // ms
            $context['request_duration_ms'] = round($duration, 2);
        }
        
        // Add WebSocket specific context for websocket requests
        if ($category === ErrorHandler::CATEGORY_WEBSOCKET || $exception instanceof WebSocketException) {
            $context['connection_id'] = $request->header('X-Connection-ID') ?? 'unknown';
            $context['socket_state'] = $this->getWebSocketState($request);
            $context['message_type'] = $request->input('message_type') ?? 'unknown';
        }
        
        // Check if this is an AJAX/API request or a WebSocket request
        $isApiRequest = $request->expectsJson() || $request->ajax();
        $isWebSocketRequest = $request->is('websocket/*') || $category === ErrorHandler::CATEGORY_WEBSOCKET || $exception instanceof WebSocketException;
        
        // If it's a Tapped exception, use its user message
        if ($exception instanceof TappedException) {
            $errorInfo = ErrorHandler::reportException(
                $exception, 
                $this->getErrorLevelFromException($exception), 
                $category, 
                array_merge($context, $exception->getContext())
            );
            
            // Special handling for WebSocket exceptions
            if ($isWebSocketRequest) {
                return $this->handleWebSocketException($request, $exception, $errorInfo, $category);
            }
            
            if ($isApiRequest) {
                return ResponseFacade::json([
                    'success' => false,
                    'message' => $exception->getUserMessage(),
                    'error_code' => $exception->getErrorCode(),
                    'recoverable' => $exception->isRecoverable(),
                    'request_id' => $request->header('X-Request-ID'),
                    'retry_after' => $this->shouldRetryRequest($exception) ? $this->getRetryDelay($exception) : null
                ], $this->getStatusCodeFromException($exception));
            }
            
            // For regular requests, we'll render an error view
            return ResponseFacade::view('errors.generic', [
                'errorMessage' => $exception->getUserMessage(),
                'errorCode' => $exception->getErrorCode(),
                'recoverable' => $exception->isRecoverable(),
                'requestId' => $request->header('X-Request-ID'),
                'retryable' => $this->shouldRetryRequest($exception)
            ], $this->getStatusCodeFromException($exception));
        }
        
        // For regular exceptions, use the error handler
        $errorInfo = ErrorHandler::reportException(
            $exception, 
            ErrorHandler::LEVEL_ERROR, 
            $category, 
            $context
        );
        
        // Special handling for WebSocket exceptions
        if ($isWebSocketRequest) {
            return $this->handleWebSocketException($request, $exception, $errorInfo, $category);
        }
        
        if ($isApiRequest) {
            return ResponseFacade::json([
                'success' => false,
                'message' => $exception instanceof TappedException ? $exception->getMessage() : $errorInfo['message'],
                'error_code' => $errorInfo['error_code'],
                'request_id' => $request->header('X-Request-ID'),
                'retry_after' => $this->shouldRetryRequest($exception) ? $this->getRetryDelay($exception) : null,
                'diagnostics' => $this->getDiagnosticInfo($request, $exception)
            ], $this->getStatusCodeFromException($exception));
        }
        
        // For regular requests, render an error view
        return ResponseFacade::view('errors.generic', [
            'errorMessage' => $exception instanceof TappedException ? $exception->getMessage() : $errorInfo['message'],
            'errorCode' => $errorInfo['error_code'],
            'recoverable' => true,
            'requestId' => $request->header('X-Request-ID'),
            'retryable' => $this->shouldRetryRequest($exception),
            'diagnostics' => $this->getDiagnosticInfo($request, $exception)
        ], $this->getStatusCodeFromException($exception));
    }
    
    /**
     * Specialized handler for WebSocket exceptions
     * 
     * @param Request $request The request instance
     * @param Throwable $exception The exception
     * @param array $errorInfo Error information from the handler
     * @param string $category Error category
     * @return Response
     */
    protected function handleWebSocketException(Request $request, Throwable $exception, array $errorInfo, string $category): Response
    {
        $statusCode = $this->getStatusCodeFromException($exception);
        $recoverable = $exception instanceof TappedException ? $exception->isRecoverable() : true;
        $retryDelay = $this->getRetryDelay($exception);
        $message = $exception instanceof TappedException 
            ? $exception->getMessage() 
            : $errorInfo['message'];
        
        // WebSocket connections often expect a specific error format
        return ResponseFacade::json([
            'success' => false,
            'message' => $message,
            'error_code' => $errorInfo['error_code'] ?? 'unknown',
            'request_id' => $request->header('X-Request-ID'),
            'connection_id' => $request->header('X-Connection-ID') ?? 'unknown',
            'recoverable' => $recoverable,
            'retry_after' => $this->shouldRetryRequest($exception) ? $retryDelay : null,
            'connection_state' => $this->getWebSocketState($request),
            'diagnostics' => $this->getDiagnosticInfo($request, $exception)
        ], $statusCode);
    }
    
    /**
     * Get error category based on exception type
     *
     * @param Throwable $exception The exception
     * @return string Error category
     */
    protected function getCategoryFromException(Throwable $exception): string
    {
        $exceptionClass = get_class($exception);
        
        $categoryMap = [
            'ThinkNeverland\\Tapped\\ErrorHandling\\Exceptions\\DatabaseException' => ErrorHandler::CATEGORY_DATABASE,
            'ThinkNeverland\\Tapped\\ErrorHandling\\Exceptions\\NetworkException' => ErrorHandler::CATEGORY_NETWORK,
            'ThinkNeverland\\Tapped\\ErrorHandling\\Exceptions\\WebSocketException' => ErrorHandler::CATEGORY_WEBSOCKET,
            'ThinkNeverland\\Tapped\\ErrorHandling\\Exceptions\\SecurityException' => ErrorHandler::CATEGORY_SECURITY,
            'ThinkNeverland\\Tapped\\ErrorHandling\\Exceptions\\LivewireException' => ErrorHandler::CATEGORY_LIVEWIRE,
            'Illuminate\\Database\\QueryException' => ErrorHandler::CATEGORY_DATABASE,
            'PDOException' => ErrorHandler::CATEGORY_DATABASE,
            'GuzzleHttp\\Exception\\RequestException' => ErrorHandler::CATEGORY_NETWORK,
            'GuzzleHttp\\Exception\\ConnectException' => ErrorHandler::CATEGORY_NETWORK,
            'Livewire\\Exceptions\\ComponentNotFoundException' => ErrorHandler::CATEGORY_LIVEWIRE,
            // Add more WebSocket related exceptions
            'Ratchet\\Client\\Exception' => ErrorHandler::CATEGORY_WEBSOCKET,
            'Ratchet\\ConnectionException' => ErrorHandler::CATEGORY_WEBSOCKET,
            'React\\Socket\\ConnectionException' => ErrorHandler::CATEGORY_WEBSOCKET,
        ];
        
        return $categoryMap[$exceptionClass] ?? ErrorHandler::CATEGORY_UNKNOWN;
    }
    
    /**
     * Get error level based on exception type
     *
     * @param Throwable $exception The exception
     * @return string Error level
     */
    protected function getErrorLevelFromException(Throwable $exception): string
    {
        if ($exception instanceof TappedException) {
            // SecurityExceptions are always critical
            if ($exception instanceof \ThinkNeverland\Tapped\ErrorHandling\Exceptions\SecurityException) {
                return ErrorHandler::LEVEL_CRITICAL;
            }
            
            // ValidationExceptions are just warnings
            if ($exception instanceof \ThinkNeverland\Tapped\ErrorHandling\Exceptions\ValidationException) {
                return ErrorHandler::LEVEL_WARNING;
            }
        }
        
        // Default to error level
        return ErrorHandler::LEVEL_ERROR;
    }
    
    /**
     * Get HTTP status code based on exception type
     *
     * @param Throwable $exception The exception
     * @return int HTTP status code
     */
    protected function getStatusCodeFromException(Throwable $exception): int
    {
        if ($exception instanceof TappedException) {
            if ($exception instanceof \ThinkNeverland\Tapped\ErrorHandling\Exceptions\ValidationException) {
                return 422; // Unprocessable Entity
            }
            
            if ($exception instanceof \ThinkNeverland\Tapped\ErrorHandling\Exceptions\SecurityException) {
                return 403; // Forbidden
            }
            
            if ($exception instanceof \ThinkNeverland\Tapped\ErrorHandling\Exceptions\DatabaseException) {
                return 500; // Internal Server Error
            }
            
            if ($exception instanceof \ThinkNeverland\Tapped\ErrorHandling\Exceptions\NetworkException) {
                return 503; // Service Unavailable
            }
        }
        
        // Default status code
        return 500;
    }
    
    /**
     * Check if the error rate for this type of request/error has been exceeded
     *
     * @param Request $request The request instance
     * @param Throwable $exception The exception
     * @return bool
     */
    protected function isRateLimited(Request $request, Throwable $exception): bool
    {
        $key = 'error_rate:' . md5($request->path() . '|' . get_class($exception));
        $count = (int) Cache::get($key, 0);
        
        return $count > $this->errorRateLimit;
    }
    
    /**
     * Record an error occurrence for rate limiting and circuit breaker functionality
     *
     * @param Request $request The request instance
     * @param Throwable $exception The exception
     * @return void
     */
    protected function recordError(Request $request, Throwable $exception): void
    {
        // Rate limiting key - specific to error type and endpoint
        $rateLimitKey = 'error_rate:' . md5($request->path() . '|' . get_class($exception));
        $count = (int) Cache::get($rateLimitKey, 0);
        Cache::put($rateLimitKey, $count + 1, $this->errorRateWindow);
        
        // Circuit breaker key - specific to endpoint
        $circuitKey = 'circuit:' . md5($request->path());
        $errorCount = (int) Cache::get($circuitKey, 0);
        Cache::put($circuitKey, $errorCount + 1, $this->circuitBreakerRecoveryTime);
        
        // Open the circuit if threshold reached
        if ($errorCount + 1 >= $this->circuitBreakerThreshold) {
            Cache::put('circuit_open:' . md5($request->path()), true, $this->circuitBreakerRecoveryTime);
            Log::warning('Circuit breaker opened for endpoint', [
                'endpoint' => $request->path(),
                'recovery_time' => $this->circuitBreakerRecoveryTime,
                'error_count' => $errorCount + 1
            ]);
        }
    }
    
    /**
     * Reset error counts for successful requests
     *
     * @param Request $request The request instance
     * @return void
     */
    protected function recordSuccessfulRequest(Request $request): void
    {
        // Reset circuit breaker counter for this endpoint
        $circuitKey = 'circuit:' . md5($request->path());
        Cache::forget($circuitKey);
        
        // If the circuit was open, close it on successful request in half-open state
        $circuitOpenKey = 'circuit_open:' . md5($request->path());
        if (Cache::has($circuitOpenKey) && Cache::has('circuit_half_open:' . md5($request->path()))) {
            Cache::forget($circuitOpenKey);
            Cache::forget('circuit_half_open:' . md5($request->path()));
            Log::info('Circuit breaker closed for endpoint after successful request', [
                'endpoint' => $request->path(),
            ]);
        }
    }
    
    /**
     * Check if circuit breaker is open for this endpoint
     *
     * @param Request $request The request instance
     * @return bool
     */
    protected function isCircuitOpen(Request $request): bool
    {
        $circuitOpenKey = 'circuit_open:' . md5($request->path());
        
        // If circuit is open, check if we should try a test request (half-open state)
        if (Cache::has($circuitOpenKey)) {
            // If we haven't tried a half-open request yet, allow one through
            if (!Cache::has('circuit_half_open:' . md5($request->path()))) {
                Cache::put('circuit_half_open:' . md5($request->path()), true, 60);
                return false;
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate circuit breaker response
     *
     * @param Request $request The request instance
     * @return Response
     */
    protected function circuitBreakerResponse(Request $request): Response
    {
        $isApiRequest = $request->expectsJson() || $request->ajax();
        $retryAfter = $this->circuitBreakerRecoveryTime;
        
        if ($isApiRequest) {
            return ResponseFacade::json([
                'success' => false,
                'message' => 'Service temporarily unavailable due to too many errors. Please try again later.',
                'error_code' => 'circuit_open',
                'retry_after' => $retryAfter,
                'request_id' => $request->header('X-Request-ID')
            ], 503)->header('Retry-After', $retryAfter);
        }
        
        return ResponseFacade::view('errors.service_unavailable', [
            'errorMessage' => 'Service temporarily unavailable due to too many errors. Please try again later.',
            'errorCode' => 'circuit_open',
            'retryAfter' => $retryAfter,
            'requestId' => $request->header('X-Request-ID')
        ], 503)->header('Retry-After', $retryAfter);
    }
    
    /**
     * Get the WebSocket connection state for improved debugging
     *
     * @param Request $request The request instance
     * @return string
     */
    protected function getWebSocketState(Request $request): string
    {
        // Try to determine WebSocket state from request parameters or headers
        $stateMap = [
            'connecting' => 'CONNECTING',
            'connected' => 'CONNECTED',
            'closing' => 'CLOSING',
            'closed' => 'CLOSED',
            'error' => 'ERROR'
        ];
        
        $state = strtolower($request->input('connection_state', ''));
        if (array_key_exists($state, $stateMap)) {
            return $stateMap[$state];
        }
        
        // Try to get from header
        $state = strtolower($request->header('X-WebSocket-State', ''));
        if (array_key_exists($state, $stateMap)) {
            return $stateMap[$state];
        }
        
        return 'UNKNOWN';
    }
    
    /**
     * Determine if this exception type should trigger an automatic retry
     *
     * @param Throwable $exception The exception
     * @return bool
     */
    protected function shouldRetryRequest(Throwable $exception): bool
    {
        // Recoverable network and WebSocket errors should be retried
        if ($exception instanceof TappedException && $exception->isRecoverable()) {
            return true;
        }
        
        // Specific error types that can be retried
        $retryableExceptions = [
            'GuzzleHttp\\Exception\\ConnectException',
            'ThinkNeverland\\Tapped\\ErrorHandling\\Exceptions\\NetworkException',
            'ThinkNeverland\\Tapped\\ErrorHandling\\Exceptions\\WebSocketException',
            'Ratchet\\ConnectionException'
        ];
        
        foreach ($retryableExceptions as $class) {
            if (is_a($exception, $class)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get appropriate retry delay based on exception type
     *
     * @param Throwable $exception The exception
     * @return int Delay in seconds
     */
    protected function getRetryDelay(Throwable $exception): int
    {
        // WebSocket exceptions benefit from a short retry
        if ($exception instanceof WebSocketException) {
            return 3;
        }
        
        // Network errors should have a longer delay
        return 10;
    }
    
    /**
     * Get diagnostic information for debugging
     *
     * @param Request $request The request instance
     * @param Throwable $exception The exception
     * @return array
     */
    protected function getDiagnosticInfo(Request $request, Throwable $exception): array
    {
        // Basic diagnostics that are safe to return to the client
        $diagnostics = [
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => date('Y-m-d H:i:s'),
            'error_type' => get_class($exception),
        ];
        
        // Add WebSocket specific diagnostics
        if ($this->getCategoryFromException($exception) === ErrorHandler::CATEGORY_WEBSOCKET) {
            $diagnostics['connection_state'] = $this->getWebSocketState($request);
            $diagnostics['connection_id'] = $request->header('X-Connection-ID') ?? 'unknown';
        }
        
        return $diagnostics;
    }
}
