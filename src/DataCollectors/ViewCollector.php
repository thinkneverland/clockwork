<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\DataCollectors;

use Illuminate\Support\Facades\Event;
use Illuminate\View\Events\ViewRendered;
use Illuminate\View\View;

class ViewCollector extends AbstractDataCollector
{
    /**
     * @var array<int, array<string, mixed>> Collected views
     */
    protected array $views = [];

    /**
     * Start collecting view data.
     */
    public function startCollecting(): void
    {
        Event::listen(ViewRendered::class, function (ViewRendered $event) {
            $this->recordView($event->view);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        return [
            'views' => $this->views,
            'total_views' => count($this->views),
        ];
    }

    /**
     * Record a rendered view.
     */
    protected function recordView(View $view): void
    {
        $name = $view->getName();
        $path = $view->getPath();
        
        // Extract data, excluding any Closure instances or other unserializable objects
        $data = $this->extractViewData($view->getData());
        
        // Get backtrace to determine render origin
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        // Filter out the framework internal calls
        $backtrace = array_values(array_filter($backtrace, function ($trace) {
            return isset($trace['file']) && 
                !str_contains($trace['file'], '/vendor/laravel/') && 
                !str_contains($trace['file'], '/vendor/illuminate/') &&
                !str_contains($trace['file'], '/vendor/thinkneverland/tapped/');
        }));

        $this->views[] = [
            'name' => $name,
            'path' => $path,
            'data' => $data,
            'time' => microtime(true),
            'file' => $backtrace[0]['file'] ?? null,
            'line' => $backtrace[0]['line'] ?? null,
        ];
    }

    /**
     * Extract and safely serialize view data.
     *
     * @param array<string, mixed> $viewData
     * @return array<string, mixed>
     */
    protected function extractViewData(array $viewData): array
    {
        $data = [];
        
        foreach ($viewData as $key => $value) {
            // Skip internal Laravel view data
            if (in_array($key, ['__env', '__data', '__path', 'app', 'errors', 'resolver'])) {
                continue;
            }
            
            // Safely serialize view data
            $data[$key] = $this->safeSerialize($value);
        }
        
        return $data;
    }
}
