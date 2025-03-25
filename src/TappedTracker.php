<?php

namespace ThinkNeverland\Tapped;

use Illuminate\Support\Facades\App;
use ThinkNeverland\Tapped\Facades\Tapped;

/**
 * Simple static helpers to use Tapped with minimal code
 */
class TappedTracker
{
    /**
     * Track a Livewire component
     * 
     * @param mixed $component
     * @return void
     */
    public static function track($component): void
    {
        // Only track in development environment
        if (!App::environment('local', 'development', 'testing')) {
            return;
        }

        try {
            // Get the state manager and track the component
            Tapped::getStateManager()->track($component);
        } catch (\Exception $e) {
            // Silently fail in production
            if (App::environment('local', 'development')) {
                throw $e;
            }
        }
    }

    /**
     * Log a custom event
     * 
     * @param string $event
     * @param array $data
     * @return void
     */
    public static function log(string $event, array $data = []): void
    {
        // Only log in development environment
        if (!App::environment('local', 'development', 'testing')) {
            return;
        }

        try {
            // Get the event logger and log the event
            Tapped::getEventLogger()->log($event, $data);
        } catch (\Exception $e) {
            // Silently fail in production
            if (App::environment('local', 'development')) {
                throw $e;
            }
        }
    }
}
