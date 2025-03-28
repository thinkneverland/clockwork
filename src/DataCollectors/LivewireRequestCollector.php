<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\DataCollectors;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Config;
use Illuminate\Container\Container;
use ThinkNeverland\Tapped\Support\Serializer;

/**
 * Collects Livewire-specific AJAX request data.
 * 
 * Focuses on monitoring Livewire AJAX calls, including payload inspection,
 * performance metrics, and error tracking.
 */
class LivewireRequestCollector extends AbstractDataCollector
{
    /**
     * @var array<int, array<string, mixed>> Collected Livewire requests
     */
    protected array $requests = [];
    
    /**
     * @var array<string, float> Start times for pending requests by fingerprint
     */
    protected array $pendingRequests = [];
    
    /**
     * @var Serializer The serializer instance
     */
    protected Serializer $serializer;
    
    /**
     * Create a new LivewireRequestCollector instance.
     *
     * @param Serializer|null $serializer
     */
    public function __construct(Serializer $serializer = null)
    {
        $this->serializer = $serializer ?? new Serializer();
    }
    
    /**
     * {@inheritdoc}
     */
    public function shouldCollect(): bool
    {
        return Config::get('tapped.collect.livewire_requests', true) && class_exists('\Livewire\Livewire');
    }
    
    /**
     * Start collecting Livewire request data.
     */
    public function startCollecting(): void
    {
        $this->registerEventListeners();
    }
    
    /**
     * Register event listeners.
     */
    protected function registerEventListeners(): void
    {
        // Listen for Livewire request events
        $this->addEventListener('Livewire\Livewire:createdRequest', function ($event) {
            $this->recordRequestStart($event);
        });
        
        $this->addEventListener('Livewire\Livewire:receivedResponse', function ($event) {
            $this->recordRequestEnd($event);
        });
        
        $this->addEventListener('Livewire\Livewire:failedRequest', function ($event, $error) {
            $this->recordRequestError($event, $error);
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        return [
            'requests' => $this->requests,
        ];
    }
    
    /**
     * Record the start of a Livewire request.
     *
     * @param mixed $event The event data
     */
    protected function recordRequestStart($event): void
    {
        // Extract request data
        $fingerprint = $this->extractFingerprint($event);
        $requestData = $this->extractRequestData($event);
        
        // Store start time and fingerprint for matching with response
        $this->pendingRequests[$fingerprint] = microtime(true);
        
        // Record initial request data
        $this->requests[] = [
            'id' => uniqid('livewire_', true),
            'fingerprint' => $fingerprint,
            'component_id' => $requestData['componentId'] ?? null,
            'component_name' => $requestData['componentName'] ?? null,
            'method' => $requestData['method'] ?? 'unknown',
            'params' => $this->serializer->serialize($requestData['params'] ?? []),
            'updates' => $this->serializer->serialize($requestData['updates'] ?? []),
            'data' => $this->serializer->serialize($requestData),
            'status' => 'pending',
            'start_time' => $this->pendingRequests[$fingerprint],
            'duration_ms' => null,
            'error' => null,
        ];
    }
    
    /**
     * Record the successful completion of a Livewire request.
     *
     * @param mixed $event The event data
     */
    protected function recordRequestEnd($event): void
    {
        $fingerprint = $this->extractFingerprint($event);
        $responseData = $this->extractResponseData($event);
        
        // Find the matching request
        $requestIndex = $this->findRequestByFingerprint($fingerprint);
        
        if ($requestIndex !== null) {
            $endTime = microtime(true);
            $startTime = $this->pendingRequests[$fingerprint] ?? $endTime;
            $duration = ($endTime - $startTime) * 1000; // in milliseconds
            
            // Update the request with response data
            $this->requests[$requestIndex] = array_merge(
                $this->requests[$requestIndex],
                [
                    'status' => 'completed',
                    'duration_ms' => round($duration, 2),
                    'end_time' => $endTime,
                    'response' => $this->serializer->serialize($responseData),
                    'effects' => $this->serializer->serialize($responseData['effects'] ?? []),
                ]
            );
            
            // Remove from pending requests
            unset($this->pendingRequests[$fingerprint]);
        }
    }
    
    /**
     * Record a Livewire request error.
     *
     * @param mixed $event The event data
     * @param mixed $error The error data
     */
    protected function recordRequestError($event, $error): void
    {
        $fingerprint = $this->extractFingerprint($event);
        
        // Find the matching request
        $requestIndex = $this->findRequestByFingerprint($fingerprint);
        
        if ($requestIndex !== null) {
            $endTime = microtime(true);
            $startTime = $this->pendingRequests[$fingerprint] ?? $endTime;
            $duration = ($endTime - $startTime) * 1000; // in milliseconds
            
            // Extract error details
            $errorMessage = $error instanceof \Throwable
                ? $error->getMessage()
                : (is_string($error) ? $error : 'Unknown error');
                
            $errorTrace = $error instanceof \Throwable
                ? $error->getTraceAsString()
                : null;
            
            // Update the request with error data
            $this->requests[$requestIndex] = array_merge(
                $this->requests[$requestIndex],
                [
                    'status' => 'failed',
                    'duration_ms' => round($duration, 2),
                    'end_time' => $endTime,
                    'error' => [
                        'message' => $errorMessage,
                        'trace' => $errorTrace,
                    ],
                ]
            );
            
            // Remove from pending requests
            unset($this->pendingRequests[$fingerprint]);
        }
    }
    
    /**
     * Extract a unique fingerprint from the event data.
     *
     * @param mixed $event The event data
     * @return string A unique identifier for the request
     */
    protected function extractFingerprint($event): string
    {
        // Try to get component ID and random value to create a fingerprint
        $componentId = $this->extractComponentId($event);
        $data = is_object($event) && method_exists($event, 'getData') ? $event->getData() : null;
        $random = is_array($data) && isset($data['requestId']) ? $data['requestId'] : uniqid();
        
        return $componentId . '-' . $random;
    }
    
    /**
     * Extract component ID from event data.
     *
     * @param mixed $event The event data
     * @return string The component ID or a placeholder
     */
    protected function extractComponentId($event): string
    {
        // Try different ways to get the component ID
        if (is_object($event)) {
            if (method_exists($event, 'getComponentId')) {
                return $event->getComponentId();
            }
            
            if (method_exists($event, 'getData')) {
                $data = $event->getData();
                if (is_array($data) && isset($data['componentId'])) {
                    return $data['componentId'];
                }
            }
            
            if (property_exists($event, 'componentId')) {
                return $event->componentId;
            }
        }
        
        return 'unknown-component';
    }
    
    /**
     * Extract request data from event.
     *
     * @param mixed $event The event data
     * @return array<string, mixed> The request data
     */
    protected function extractRequestData($event): array
    {
        if (is_object($event) && method_exists($event, 'getData')) {
            return $event->getData() ?? [];
        }
        
        if (is_object($event) && property_exists($event, 'data')) {
            return $event->data ?? [];
        }
        
        if (is_array($event)) {
            return $event;
        }
        
        return [];
    }
    
    /**
     * Extract response data from event.
     *
     * @param mixed $event The event data
     * @return array<string, mixed> The response data
     */
    protected function extractResponseData($event): array
    {
        if (is_object($event) && method_exists($event, 'getResponse')) {
            return $event->getResponse() ?? [];
        }
        
        if (is_object($event) && property_exists($event, 'response')) {
            return $event->response ?? [];
        }
        
        return [];
    }
    
    /**
     * Find a request by fingerprint.
     *
     * @param string $fingerprint The request fingerprint
     * @return int|null The request index or null if not found
     */
    protected function findRequestByFingerprint(string $fingerprint): ?int
    {
        foreach ($this->requests as $index => $request) {
            if ($request['fingerprint'] === $fingerprint) {
                return $index;
            }
        }
        
        return null;
    }
    
    /**
     * Register an event listener safely.
     *
     * @param string $event
     * @param callable $callback
     * @return void
     */
    protected function addEventListener(string $event, callable $callback): void
    {
        try {
            // Try to use Event facade if available
            if (class_exists('\Illuminate\Support\Facades\Event')) {
                $eventClass = '\Illuminate\Support\Facades\Event';
                $eventClass::listen($event, $callback);
                return;
            }
            
            // Fallback to global event dispatcher if available
            try {
                $app = Container::getInstance();
                if ($app && $app->bound('events')) {
                    $app->make('events')->listen($event, $callback);
                    return;
                }
            } catch (\Throwable $e) {
                // Silently fail if container not available
            }
        } catch (\Throwable $e) {
            // Log error or handle failure silently
        }
    }
}
