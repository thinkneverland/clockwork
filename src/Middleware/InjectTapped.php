<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use ThinkNeverland\Tapped\Support\Tapped;

class InjectTapped
{
    /**
     * @var Tapped The Tapped instance
     */
    protected Tapped $tapped;

    /**
     * InjectTapped constructor.
     */
    public function __construct(Tapped $tapped)
    {
        $this->tapped = $tapped;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // Don't inject Tapped if it's disabled or we're in a production environment
        if (!config('tapped.enabled', false) || App::environment('production') && !config('app.debug', false)) {
            return $next($request);
        }

        // Don't inject Tapped for ignored paths
        if ($this->isIgnoredPath($request)) {
            return $next($request);
        }

        // Start collecting data
        $this->tapped->startCollectors();

        // Process the request
        $response = $next($request);

        // Stop collecting data and store the results
        $this->tapped->stopCollectors();
        $requestId = $this->tapped->storeRequest();

        // Add Tapped headers to the response if it's an HTTP response
        if ($response instanceof Response) {
            $response->header('X-Tapped-Id', $requestId);
            $response->header('X-Tapped-Version', Tapped::VERSION);
        }

        return $response;
    }

    /**
     * Determine if the request path is in the ignored list.
     */
    protected function isIgnoredPath(Request $request): bool
    {
        $path = $request->decodedPath();
        $ignoredPaths = config('tapped.ignored_paths', []);

        foreach ($ignoredPaths as $ignoredPath) {
            if (preg_match($ignoredPath, $path)) {
                return true;
            }
        }

        return false;
    }
}
