<?php

namespace ThinkNeverland\Tapped\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Carbon;
use ThinkNeverland\Tapped\Services\LivewireStateManager;

class TappedMiddleware
{
    protected LivewireStateManager $stateManager;

    public function __construct(LivewireStateManager $stateManager)
    {
        $this->stateManager = $stateManager;
    }

    public function handle(Request $request, Closure $next)
    {
        if (!Config::get('tapped.enabled') || !$request->hasHeader('X-Livewire')) {
            return $next($request);
        }

        // Register any Livewire components that are about to be rendered
        $this->registerLivewireComponents();

        $response = $next($request);

        // If this is a Livewire request, we'll inject our debug information
        if ($request->hasHeader('X-Livewire')) {
            $this->injectDebugInformation($response);
        }

        return $response;
    }

    protected function registerLivewireComponents(): void
    {
        $components = App::make('livewire')->getComponentsByName();

        foreach ($components as $name => $class) {
            if ($component = App::make($class)) {
                $this->stateManager->registerComponent($component);
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
