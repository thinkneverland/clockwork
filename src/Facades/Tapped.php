<?php

namespace ThinkNeverland\Tapped\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ThinkNeverland\Tapped\Services\LivewireStateManager getStateManager()
 * @method static \ThinkNeverland\Tapped\Services\EventLogger getEventLogger()
 * @method static bool isEnabled()
 * @method static bool extensiveLoggingEnabled()
 */
class Tapped extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'tapped';
    }
}
