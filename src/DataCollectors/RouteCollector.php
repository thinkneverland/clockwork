<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\DataCollectors;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class RouteCollector extends AbstractDataCollector
{
    /**
     * @var array<string, mixed>|null The resolved route info
     */
    protected ?array $route = null;

    /**
     * Start collecting route data.
     */
    public function startCollecting(): void
    {
        // Using a different syntax to avoid IDE type-hinting issues
        // While maintaining functionality with Laravel's Event facade
        \Illuminate\Support\Facades\Event::listen(
            'Illuminate\Foundation\Http\Events\RequestHandled', 
            function ($event) {
                $this->collectRouteData($event->request->route());
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        return [
            'route' => $this->route,
            'has_route' => !is_null($this->route),
        ];
    }

    /**
     * Collect route data from the current request.
     *
     * @param \Illuminate\Routing\Route|null $route
     */
    protected function collectRouteData(?object $route): void
    {
        if (!$route) {
            return;
        }

        $this->route = [
            'domain' => $route->domain(),
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'action' => $this->getRouteAction($route),
            'middleware' => $this->getRouteMiddleware($route),
            'controller' => $route->getController() ? get_class($route->getController()) : null,
            'method' => $route->methods(),
            'parameters' => $this->safeSerialize($route->parameters()),
            'parameter_names' => $route->parameterNames(),
            'is_fallback' => $route->isFallback,
        ];
    }

    /**
     * Get the route action in a readable format.
     */
    protected function getRouteAction(object $route): string
    {
        $action = $route->getActionName();
        
        // If the action is a controller, make it more readable
        if (str_contains($action, '@')) {
            return $action;
        }
        
        // If the action is a closure, indicate that
        if ($action === 'Closure') {
            return 'Closure';
        }
        
        // For other actions, show the full action name
        return $action;
    }

    /**
     * Get middleware applied to the route.
     *
     * @return array<int, string>
     */
    protected function getRouteMiddleware(object $route): array
    {
        $middleware = $route->middleware();
        
        if (is_array($middleware)) {
            return $middleware;
        }
        
        return explode('|', $middleware);
    }
}
