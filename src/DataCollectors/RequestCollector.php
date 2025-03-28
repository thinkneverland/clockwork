<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\DataCollectors;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class RequestCollector extends AbstractDataCollector
{
    /**
     * Current request capture time.
     */
    private float $startTime;

    /**
     * RequestCollector constructor.
     */
    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        $request = request();

        $data = [
            'id' => $this->generateRequestId($request),
            'time' => date('Y-m-d H:i:s'),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'uri' => $request->path(),
            'host' => $request->getHost(),
            'ip' => $request->ip(),
            'protocol' => $request->server('SERVER_PROTOCOL'),
            'headers' => $this->formatHeaders($request),
            'payload' => $this->formatPayload($request),
            'session' => $this->formatSession($request),
            'response_status' => http_response_code(),
            'response_time' => round((microtime(true) - $this->startTime) * 1000, 2), // in ms
            'route' => $this->getCurrentRoute(),
            'memory_usage' => $this->getMemoryUsage(),
        ];

        return $data;
    }

    /**
     * Generate a unique request ID.
     */
    protected function generateRequestId(Request $request): string
    {
        return str_replace('.', '', uniqid('', true));
    }

    /**
     * Format request headers.
     *
     * @return array<string, string>
     */
    protected function formatHeaders(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            // Skip sensitive headers
            if (in_array(strtolower($name), ['cookie', 'authorization'])) {
                $headers[$name] = '[Sensitive data hidden]';
                continue;
            }

            $headers[$name] = implode(', ', $values);
        }

        return $headers;
    }

    /**
     * Format request payload.
     *
     * @return array<string, mixed>
     */
    protected function formatPayload(Request $request): array
    {
        $payload = $request->all();

        // Filter sensitive data
        $sensitiveKeys = ['password', 'password_confirmation', 'token', 'secret'];

        foreach ($sensitiveKeys as $key) {
            if (isset($payload[$key])) {
                $payload[$key] = '[Sensitive data hidden]';
            }
        }

        return $this->safeSerialize($payload);
    }

    /**
     * Format session data.
     *
     * @return array<string, mixed>|null
     */
    protected function formatSession(Request $request): ?array
    {
        if (!$request->hasSession()) {
            return null;
        }

        $sessionData = [];
        foreach ($request->session()->all() as $key => $value) {
            // Skip sensitive session data
            if (in_array($key, ['_token', 'password', 'password_confirmation'])) {
                $sessionData[$key] = '[Sensitive data hidden]';
                continue;
            }

            $sessionData[$key] = $this->safeSerialize($value);
        }

        return $sessionData;
    }

    /**
     * Get current route information.
     *
     * @return array<string, mixed>|null
     */
    protected function getCurrentRoute(): ?array
    {
        $route = Route::current();

        if (!$route) {
            return null;
        }

        return [
            'name' => $route->getName(),
            'action' => $route->getActionName(),
            'middleware' => $route->middleware(),
            'parameters' => $this->safeSerialize($route->parameters()),
        ];
    }

    /**
     * Get memory usage.
     */
    protected function getMemoryUsage(): string
    {
        $bytes = memory_get_usage(true);
        
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        } elseif ($bytes < 1073741824) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        
        return round($bytes / 1073741824, 2) . ' GB';
    }
}
