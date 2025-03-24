<?php

namespace ThinkNeverland\Tapped;

use Illuminate\Contracts\Foundation\Application;
use ThinkNeverland\Tapped\Services\LivewireStateManager;
use ThinkNeverland\Tapped\Services\EventLogger;

class Tapped
{
    protected Application $app;
    protected LivewireStateManager $stateManager;
    protected EventLogger $eventLogger;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->stateManager = $app->make(LivewireStateManager::class);
        $this->eventLogger = $app->make(EventLogger::class);
    }

    public function getStateManager(): LivewireStateManager
    {
        return $this->stateManager;
    }

    public function getEventLogger(): EventLogger
    {
        return $this->eventLogger;
    }

    public function isEnabled(): bool
    {
        return (bool) config('tapped.enabled');
    }

    public function extensiveLoggingEnabled(): bool
    {
        return (bool) config('tapped.extensive_logging');
    }
}
