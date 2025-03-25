<?php

namespace ThinkNeverland\Tapped\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Carbon;
use Livewire\Mechanisms\ComponentRegistry;
use ThinkNeverland\Tapped\Services\LivewireStateManager;
use Illuminate\Support\Str;

class TappedMiddleware
{
    protected LivewireStateManager $stateManager;

    public function __construct(LivewireStateManager $stateManager)
    {
        $this->stateManager = $stateManager;
    }

    public function handle(Request $request, Closure $next)
    {
        // Check if this is a Livewire request - use header or request content
        $isLivewireRequest = $request->hasHeader('X-Livewire') ||
            $request->has('components') ||
            $request->has('fingerprint');

        if ($isLivewireRequest) {
            // Register any Livewire components that are about to be rendered
            $this->registerLivewireComponents();
        }

        $response = $next($request);

        // Generate a unique ID for this request
        $requestId = Str::uuid()->toString();

        // Path where metadata can be retrieved
        $metadataPath = '/_tapped';

        // Tapped version
        $version = '1.0.0';

        // Add headers to enable the Tapped extension
        $response->headers->set('X-Tapped-ID', $requestId);
        $response->headers->set('X-Tapped-Path', $metadataPath);
        $response->headers->set('X-Tapped-Version', $version);

        // If this is a Livewire request, we'll inject our debug information
        if ($isLivewireRequest && method_exists($response, 'getData')) {
            $this->injectDebugInformation($response);
        }

        return $response;
    }

    protected function registerLivewireComponents(): void
    {
        // In Livewire v3, components are handled differently
        // We'll try to get components from the request data as they're rendered
        $livewireRequest = request()->input('components', []);

        if (!empty($livewireRequest)) {
            foreach ($livewireRequest as $componentData) {
                if (isset($componentData['name'])) {
                    $componentName = $componentData['name'];
                    $registry = App::make(ComponentRegistry::class);

                    // Try to find the class from the component name
                    try {
                        $componentClass = $registry->getClass($componentName);
                        if ($componentClass && class_exists($componentClass)) {
                            $component = App::make($componentClass);

                            // For Livewire v3, we need to set the component ID if available
                            if (isset($componentData['id'])) {
                                $reflectionProperty = new \ReflectionProperty($component, 'id');
                                $reflectionProperty->setAccessible(true);
                                $reflectionProperty->setValue($component, $componentData['id']);
                            }

                            $this->stateManager->registerComponent($component);
                        }
                    } catch (\Exception $e) {
                        // If component isn't found, just continue
                        continue;
                    }
                }
            }
        }
    }

    protected function injectDebugInformation($response): void
    {
        if (!method_exists($response, 'getData')) {
            return;
        }

        $data = $response->getData(true);

        // Add our debug information
        $data['tapped'] = [
            'states' => $this->stateManager->getState(null),
            'timestamp' => Carbon::now()->toIso8601String(),
        ];

        $response->setData($data);
    }
}
