<?php

namespace ThinkNeverland\Tapped\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use ThinkNeverland\Tapped\ErrorHandling\ErrorHandler;

/**
 * API controller for receiving error reports from the client-side
 */
class ErrorReportController
{
    /**
     * Process incoming error reports from JavaScript clients
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $errors = $request->input('errors', []);
        $processed = 0;
        
        foreach ($errors as $error) {
            // Map JavaScript error level to PHP error level
            $level = $this->mapErrorLevel($error['level'] ?? 'error');
            
            // Map JavaScript error category to PHP error category
            $category = $this->mapErrorCategory($error['category'] ?? 'unknown');
            
            // Process the error
            ErrorHandler::reportMessage(
                $error['message'] ?? 'Unknown client error',
                $level,
                $category,
                $this->prepareContext($error, $request)
            );
            
            $processed++;
        }
        
        return response()->json([
            'success' => true,
            'processed' => $processed,
            'received' => count($errors)
        ]);
    }
    
    /**
     * Map JavaScript error level to PHP error level
     *
     * @param string $jsLevel
     * @return string
     */
    protected function mapErrorLevel(string $jsLevel): string
    {
        $mapping = [
            'debug' => ErrorHandler::LEVEL_DEBUG,
            'info' => ErrorHandler::LEVEL_INFO,
            'warning' => ErrorHandler::LEVEL_WARNING,
            'error' => ErrorHandler::LEVEL_ERROR,
            'critical' => ErrorHandler::LEVEL_CRITICAL
        ];
        
        return $mapping[strtolower($jsLevel)] ?? ErrorHandler::LEVEL_ERROR;
    }
    
    /**
     * Map JavaScript error category to PHP error category
     *
     * @param string $jsCategory
     * @return string
     */
    protected function mapErrorCategory(string $jsCategory): string
    {
        $mapping = [
            'network' => ErrorHandler::CATEGORY_NETWORK,
            'websocket' => ErrorHandler::CATEGORY_WEBSOCKET,
            'storage' => ErrorHandler::CATEGORY_DATABASE,
            'database' => ErrorHandler::CATEGORY_DATABASE,
            'livewire' => ErrorHandler::CATEGORY_LIVEWIRE,
            'security' => ErrorHandler::CATEGORY_SECURITY,
            'api' => ErrorHandler::CATEGORY_NETWORK,
            'extension' => ErrorHandler::CATEGORY_INTERNAL,
            'unknown' => ErrorHandler::CATEGORY_UNKNOWN
        ];
        
        return $mapping[strtolower($jsCategory)] ?? ErrorHandler::CATEGORY_UNKNOWN;
    }
    
    /**
     * Prepare context data for error logging
     *
     * @param array $error
     * @param Request $request
     * @return array
     */
    protected function prepareContext(array $error, Request $request): array
    {
        $context = [
            'source' => 'javascript',
            'request_id' => $request->header('X-Request-ID') ?? ($error['context']['request_id'] ?? null),
            'client_error_id' => $error['error_id'] ?? null,
            'stack' => $error['stack'] ?? null,
        ];
        
        // Add browser context if available
        if (isset($error['context']['browser'])) {
            $context['browser'] = $error['context']['browser'];
        }
        
        // Add URL if available
        if (isset($error['context']['url'])) {
            $context['url'] = $error['context']['url'];
        }
        
        // Add any additional context from the error
        if (isset($error['context']) && is_array($error['context'])) {
            foreach ($error['context'] as $key => $value) {
                // Skip already processed context keys
                if (!in_array($key, ['browser', 'url', 'request_id'])) {
                    $context[$key] = $value;
                }
            }
        }
        
        return $context;
    }
}
