<?php

namespace Tests\Feature\ErrorHandling;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Thinkneverland\Tapped\ErrorHandling\Exceptions\TappedException;
use Thinkneverland\Tapped\ErrorHandling\Exceptions\DatabaseException;
use Thinkneverland\Tapped\ErrorHandling\Exceptions\NetworkException;
use Thinkneverland\Tapped\ErrorHandling\ErrorHandler;

class ErrorHandlingTest extends TestCase
{
    /**
     * Test the error handler captures and logs exceptions correctly
     *
     * @return void
     */
    public function testErrorHandlerLogsExceptions()
    {
        // Get the error handler instance
        $errorHandler = app(ErrorHandler::class);
        
        // Create a test exception
        $exception = new DatabaseException('Test database error', [
            'query' => 'SELECT * FROM test',
            'code' => 'DB_ERROR_001'
        ]);
        
        // Handle the exception
        $result = $errorHandler->captureException($exception);
        
        // Assert the result has expected properties
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('level', $result);
        $this->assertArrayHasKey('category', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertEquals('error', $result['level']);
        $this->assertEquals('database', $result['category']);
    }
    
    /**
     * Test that the middleware returns proper error responses
     *
     * @return void
     */
    public function testErrorHandlingMiddlewareReturnsProperResponse()
    {
        // Create a route that will throw an exception
        $this->app['router']->get('/test-error', function () {
            throw new NetworkException('Test network error', [
                'url' => 'http://example.com',
                'statusCode' => 500
            ]);
        })->middleware('tapped.error-handling');
        
        // Make a request to the route
        $response = $this->get('/test-error');
        
        // Assert the response contains the expected error structure
        $response->assertStatus(500);
        $response->assertJson([
            'error' => [
                'message' => 'A network error occurred. Please check your connection and try again.',
                'category' => 'network',
            ]
        ]);
        
        // In debug mode, the response should contain more details
        config(['error-handling.debug_mode' => true]);
        
        $response = $this->get('/test-error');
        $response->assertStatus(500);
        $response->assertJsonStructure([
            'error' => [
                'message',
                'category',
                'context',
                'code'
            ]
        ]);
    }
    
    /**
     * Test that client-side error reporting works
     *
     * @return void
     */
    public function testClientErrorReporting()
    {
        // Enable client error reporting
        config(['error-handling.client_reporting.enabled' => true]);
        
        // Create a test error payload
        $errorPayload = [
            'errors' => [
                [
                    'error_id' => 'test-123',
                    'timestamp' => now()->toISOString(),
                    'message' => 'JavaScript Error',
                    'stack' => 'Error: JavaScript Error\n    at test.js:10:5',
                    'level' => 'error',
                    'category' => 'websocket',
                    'context' => [
                        'url' => 'http://localhost/app',
                        'browser' => 'Chrome 96.0'
                    ],
                    'source' => 'javascript',
                    'client_type' => 'browser-extension'
                ]
            ]
        ];
        
        // Make a request to the error reporting endpoint
        $response = $this->postJson('/api/tapped/errors/report', $errorPayload, [
            'X-Request-ID' => 'test-request-id',
            'X-Tapped-Client' => 'browser-extension'
        ]);
        
        // Assert the response is successful
        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }
    
    /**
     * Test that rate limiting on error reporting works
     *
     * @return void
     */
    public function testErrorReportingRateLimit()
    {
        // Set a low rate limit
        config([
            'error-handling.client_reporting.enabled' => true,
            'error-handling.client_reporting.rate_limit' => 2
        ]);
        
        // Create a test error payload
        $errorPayload = [
            'errors' => [
                [
                    'error_id' => 'test-123',
                    'message' => 'Test error',
                    'level' => 'error'
                ]
            ]
        ];
        
        // First two requests should succeed
        $this->postJson('/api/tapped/errors/report', $errorPayload)->assertStatus(200);
        $this->postJson('/api/tapped/errors/report', $errorPayload)->assertStatus(200);
        
        // Third request should be rate limited
        $this->postJson('/api/tapped/errors/report', $errorPayload)->assertStatus(429);
    }
}
