<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\DataCollectors;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use ThinkNeverland\Tapped\Events\ComponentStateModified;
use ThinkNeverland\Tapped\Support\Serializer;

/**
 * Collects events from Livewire components to build a detailed timeline.
 */
class LivewireEventCollector extends AbstractDataCollector
{
    /**
     * @var array<int, array<string, mixed>> Event timeline
     */
    protected array $timeline = [];

    /**
     * @var array<string, array<string, mixed>> Component details by ID
     */
    protected array $components = [];

    /**
     * @var array<string, array<string, array<string, mixed>>> Method calls by component ID and method name
     */
    protected array $methodCalls = [];

    /**
     * @var float Collector start time
     */
    protected float $startTime;
    
    /**
     * @var Serializer The serializer instance
     */
    protected Serializer $serializer;

    /**
     * Create a new LivewireEventCollector instance.
     *
     * @param Serializer|null $serializer
     */
    public function __construct(Serializer $serializer = null)
    {
        $this->serializer = $serializer ?? new Serializer();
    }
    
    /**
     * Start collecting Livewire component data.
     */
    public function startCollecting(): void
    {
        $this->startTime = microtime(true);
        $this->registerLivewireHooks();
        $this->registerSystemEventListeners();
    }

    /**
     * {@inheritdoc}
     */
    public function shouldCollect(): bool
    {
        // Use native config function to avoid facade issues
        return \config('tapped.collect.livewire_events', true) && class_exists('\Livewire\Component');
    }

    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        return [
            'timeline' => $this->timeline,
            'components' => $this->components,
            'method_calls' => $this->methodCalls,
            'total_events' => count($this->timeline),
        ];
    }

    /**
     * Register Livewire hooks to track component lifecycle and interactions.
     */
    protected function registerLivewireHooks(): void
    {
        // Exit early if Livewire is not installed
        if (!class_exists('\Livewire\Livewire')) {
            return;
        }

        try {
            // For Livewire 3.x - Use direct static listen method
            if (class_exists('\Livewire\Livewire') && method_exists('\Livewire\Livewire', 'listen')) {
                $this->registerLivewireEventsWithoutFacade();
                return;
            }

            // Fallback for Livewire 2.x
            $livewire = $this->getLivewireInstance();
            if ($livewire && method_exists($livewire, 'hook')) {
                $this->registerLivewire2Hooks($livewire);
            }
        } catch (\Throwable $e) {
            // Silently fail if we can't register hooks
        }
    }

    /**
     * Register event listeners for Livewire 3.x without using facade
     */
    protected function registerLivewireEventsWithoutFacade(): void
    {
        // Component initialization
        call_user_func(['\Livewire\Livewire', 'listen'], 'component.initializing', function ($component, $request) {
            $this->recordComponentEvent($component, 'initializing', [
                'request_method' => $request->method(),
                'request_path' => $request->path(),
            ]);
        });

        // Component hydration (creation of instance)
        call_user_func(['\Livewire\Livewire', 'listen'], 'component.hydrate', function ($component, $request) {
            $this->recordComponentEvent($component, 'hydrate', [
                'request_data' => $this->sanitizeRequestData($request->all()),
            ]);
            
            // Record component details
            $this->recordComponentDetails($component);
        });

        // Component initialization after hydration
        call_user_func(['\Livewire\Livewire', 'listen'], 'component.hydrate.subsequent', function ($component, $request) {
            $this->recordComponentEvent($component, 'hydrate_subsequent', []);
        });

        // Component booting 
        call_user_func(['\Livewire\Livewire', 'listen'], 'component.booted', function ($component, $request) {
            $this->recordComponentEvent($component, 'booted', []);
        });

        // Before calling a method
        call_user_func(['\Livewire\Livewire', 'listen'], 'component.calling', function ($component, $method, $params) {
            $this->recordComponentEvent($component, 'method_calling', [
                'method' => $method,
                'params' => $this->serializer->serialize($params),
            ]);
            
            // Track method calls specifically
            $this->recordMethodCall($component, $method, $params, 'started');
        });

        // After calling a method
        call_user_func(['\Livewire\Livewire', 'listen'], 'component.called', function ($component, $method, $params, $returnValue) {
            $this->recordComponentEvent($component, 'method_called', [
                'method' => $method,
                'params' => $this->serializer->serialize($params),
                'return_value' => $this->serializer->serialize($returnValue),
            ]);
            
            // Update method call status
            $this->recordMethodCall($component, $method, $params, 'completed', $returnValue);
        });

        // Property updates
        call_user_func(['\Livewire\Livewire', 'listen'], 'property.updating', function ($component, $name, $value, $oldValue) {
            $this->recordComponentEvent($component, 'property_updating', [
                'property' => $name,
                'old_value' => $this->serializer->serialize($oldValue),
                'new_value' => $this->serializer->serialize($value),
            ]);
        });
        
        call_user_func(['\Livewire\Livewire', 'listen'], 'property.updated', function ($component, $name, $value, $oldValue) {
            $this->recordComponentEvent($component, 'property_updated', [
                'property' => $name,
                'old_value' => $this->serializer->serialize($oldValue),
                'new_value' => $this->serializer->serialize($value),
            ]);
        });

        // Component rendering
        call_user_func(['\Livewire\Livewire', 'listen'], 'component.rendering', function ($component, $view) {
            $this->recordComponentEvent($component, 'rendering', [
                'view' => $view->getName(),
            ]);
        });
        
        call_user_func(['\Livewire\Livewire', 'listen'], 'component.rendered', function ($component, $view) {
            $this->recordComponentEvent($component, 'rendered', [
                'view' => $view->getName(),
                'render_time_ms' => $this->calculateRenderTime($component),
            ]);
        });

        // Component dehydration (serializing to send to browser)
        call_user_func(['\Livewire\Livewire', 'listen'], 'component.dehydrate', function ($component, $response) {
            $this->recordComponentEvent($component, 'dehydrate', [
                'response_size' => strlen(json_encode($response->getResponseData())),
            ]);
        });
        
        call_user_func(['\Livewire\Livewire', 'listen'], 'component.dehydrate.subsequent', function ($component, $response) {
            $this->recordComponentEvent($component, 'dehydrate_subsequent', []);
        });
    }

    /**
     * Register event listeners for Livewire 2.x using the hook API
     * 
     * @param object $livewire The Livewire manager instance
     */
    protected function registerLivewire2Hooks(object $livewire): void
    {
        // Component initialization
        $livewire->hook('component.initializing', function ($component, $request) {
            $this->recordComponentEvent($component, 'initializing', [
                'request_method' => $request->method(),
                'request_path' => $request->path(),
            ]);
        });

        // Component hydration (creation of instance)
        $livewire->hook('component.hydrate', function ($component, $request) {
            $this->recordComponentEvent($component, 'hydrate', [
                'request_data' => $this->sanitizeRequestData($request->all()),
            ]);
            
            // Record component details
            $this->recordComponentDetails($component);
        });

        // Component initialization after hydration
        $livewire->hook('component.hydrate.subsequent', function ($component, $request) {
            $this->recordComponentEvent($component, 'hydrate_subsequent', []);
        });

        // Component booting 
        $livewire->hook('component.booted', function ($component, $request) {
            $this->recordComponentEvent($component, 'booted', []);
        });

        // Before calling a method
        $livewire->hook('component.calling', function ($component, $method, $params) {
            $this->recordComponentEvent($component, 'method_calling', [
                'method' => $method,
                'params' => $this->serializer->serialize($params),
            ]);
            
            // Track method calls specifically
            $this->recordMethodCall($component, $method, $params, 'started');
        });

        // After calling a method
        $livewire->hook('component.called', function ($component, $method, $params, $returnValue) {
            $this->recordComponentEvent($component, 'method_called', [
                'method' => $method,
                'params' => $this->serializer->serialize($params),
                'return_value' => $this->serializer->serialize($returnValue),
            ]);
            
            // Update method call status
            $this->recordMethodCall($component, $method, $params, 'completed', $returnValue);
        });

        // Property updates
        $livewire->hook('property.updating', function ($component, $name, $value, $oldValue) {
            $this->recordComponentEvent($component, 'property_updating', [
                'property' => $name,
                'old_value' => $this->serializer->serialize($oldValue),
                'new_value' => $this->serializer->serialize($value),
            ]);
        });
        
        $livewire->hook('property.updated', function ($component, $name, $value, $oldValue) {
            $this->recordComponentEvent($component, 'property_updated', [
                'property' => $name,
                'old_value' => $this->serializer->serialize($oldValue),
                'new_value' => $this->serializer->serialize($value),
            ]);
        });

        // Component rendering
        $livewire->hook('component.rendering', function ($component, $view) {
            $this->recordComponentEvent($component, 'rendering', [
                'view' => $view->getName(),
            ]);
        });
        
        $livewire->hook('component.rendered', function ($component, $view) {
            $this->recordComponentEvent($component, 'rendered', [
                'view' => $view->getName(),
                'render_time_ms' => $this->calculateRenderTime($component),
            ]);
        });

        // Component dehydration (serializing to send to browser)
        $livewire->hook('component.dehydrate', function ($component, $response) {
            $this->recordComponentEvent($component, 'dehydrate', [
                'response_size' => strlen(json_encode($response->getResponseData())),
            ]);
        });
        
        $livewire->hook('component.dehydrate.subsequent', function ($component, $response) {
            $this->recordComponentEvent($component, 'dehydrate_subsequent', []);
        });
    }

    /**
     * Register event listeners for system events.
     */
    protected function registerSystemEventListeners(): void
    {
        // Listen for component state modifications from the debugger
        $this->addEventListener(ComponentStateModified::class, function (ComponentStateModified $event) {
            $this->timeline[] = [
                'type' => 'debugger_action',
                'subtype' => $event->isSnapshot ? 'snapshot_applied' : 'property_modified',
                'component_id' => $event->componentId,
                'property' => $event->property,
                'value' => $this->serializer->serialize($event->value),
                'timestamp' => microtime(true),
                'time_since_start' => microtime(true) - $this->startTime,
            ];
        });

        // Listen for Livewire events
        $this->addEventListener('Livewire\Event\*', function ($eventName, $data) {
            $this->timeline[] = [
                'type' => 'livewire_event',
                'event' => $eventName,
                'data' => $this->serializer->serialize($data),
                'timestamp' => microtime(true),
                'time_since_start' => microtime(true) - $this->startTime,
            ];
        });
    }

    /**
     * Record a component event in the timeline.
     *
     * @param object $component The component instance
     * @param string $event The event type
     * @param array<string, mixed> $data Additional event data
     */
    protected function recordComponentEvent(object $component, string $event, array $data): void
    {
        $componentId = $component->getId();
        $componentName = get_class($component);
        
        $this->timeline[] = array_merge([
            'type' => 'component_lifecycle',
            'subtype' => $event,
            'component_id' => $componentId,
            'component_name' => $componentName,
            'timestamp' => microtime(true),
            'time_since_start' => microtime(true) - $this->startTime,
        ], $data);
    }

    /**
     * Record component details for reference.
     *
     * @param object $component The component instance
     */
    protected function recordComponentDetails(object $component): void
    {
        $componentId = $component->getId();
        $componentName = get_class($component);
        
        // Get basic component details
        if (!isset($this->components[$componentId])) {
            $this->components[$componentId] = [
                'id' => $componentId,
                'name' => $componentName,
                'first_seen' => microtime(true),
                'time_since_start' => microtime(true) - $this->startTime,
                'interaction_count' => 0,
            ];
        }
        
        // Increment interaction count
        $this->components[$componentId]['interaction_count']++;
        $this->components[$componentId]['last_seen'] = microtime(true);
    }

    /**
     * Record a method call with its status.
     *
     * @param object $component The component instance
     * @param string $method The method name
     * @param array<int, mixed> $params The method parameters
     * @param string $status The call status (started/completed)
     * @param mixed $returnValue The return value (if completed)
     */
    protected function recordMethodCall(object $component, string $method, array $params, string $status, $returnValue = null): void
    {
        $componentId = $component->getId();
        
        if (!isset($this->methodCalls[$componentId])) {
            $this->methodCalls[$componentId] = [];
        }
        
        if (!isset($this->methodCalls[$componentId][$method])) {
            $this->methodCalls[$componentId][$method] = [];
        }
        
        $callData = [
            'status' => $status,
            'params' => $this->serializer->serialize($params),
            'timestamp' => microtime(true),
            'time_since_start' => microtime(true) - $this->startTime,
        ];
        
        if ($status === 'completed' && $returnValue !== null) {
            $callData['return_value'] = $this->serializer->serialize($returnValue);
            
            // If we have a started record, calculate duration
            $lastIndex = count($this->methodCalls[$componentId][$method]) - 1;
            if ($lastIndex >= 0 && 
                $this->methodCalls[$componentId][$method][$lastIndex]['status'] === 'started') {
                $callData['duration_ms'] = ($callData['timestamp'] - 
                    $this->methodCalls[$componentId][$method][$lastIndex]['timestamp']) * 1000;
            }
        }
        
        $this->methodCalls[$componentId][$method][] = $callData;
    }

    /**
     * Calculate the render time for a component.
     *
     * @param object $component The component instance
     * @return float|null The render time in milliseconds or null if unknown
     */
    protected function calculateRenderTime(object $component): ?float
    {
        $componentId = $component->getId();
        
        // Look for the most recent 'rendering' event for this component
        for ($i = count($this->timeline) - 1; $i >= 0; $i--) {
            $event = $this->timeline[$i];
            if ($event['type'] === 'component_lifecycle' && 
                $event['subtype'] === 'rendering' && 
                $event['component_id'] === $componentId) {
                return (microtime(true) - $event['timestamp']) * 1000;
            }
        }
        
        return null;
    }

    /**
     * Sanitize request data to avoid sensitive information.
     *
     * @param array<string, mixed> $data The request data
     * @return array<string, mixed> The sanitized data
     */
    protected function sanitizeRequestData(array $data): array
    {
        $sensitiveKeys = ['password', 'token', 'auth', 'key', 'secret', 'credential'];
        $result = [];
        
        foreach ($data as $key => $value) {
            // Check if this is a sensitive key
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (stripos($key, $sensitiveKey) !== false) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                $result[$key] = '[REDACTED]';
            } else if (is_array($value)) {
                $result[$key] = $this->sanitizeRequestData($value);
            } else {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Get the Livewire instance safely.
     *
     * @return object|null
     */
    protected function getLivewireInstance(): ?object
    {
        try {
            // Try to get Livewire instance without facades
            if (function_exists('app')) {
                return \app('livewire');
            }
            
            // Fallback to global application instance if available
            if (isset($GLOBALS['app']) && method_exists($GLOBALS['app'], 'make')) {
                return $GLOBALS['app']->make('livewire');
            }
            
            return null;
        } catch (\Throwable $e) {
            return null;
        }
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
            // Use Laravel's event helper directly
            if (function_exists('event')) {
                \event($event, $callback);
                return;
            }
        } catch (\Throwable $e) {
            // Log error or handle failure silently
        }
    }
}
