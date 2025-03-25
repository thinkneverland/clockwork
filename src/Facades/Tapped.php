<?php

namespace ThinkNeverland\Tapped\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ThinkNeverland\Tapped\Services\LivewireStateManager getStateManager()
 * @method static \ThinkNeverland\Tapped\Services\EventLogger getEventLogger()
 * @method static bool isEnabled()
 * @method static bool extensiveLoggingEnabled()
 * @method static \ThinkNeverland\Tapped\Tapped enableDebugMode()
 * @method static \ThinkNeverland\Tapped\Tapped disableDebugMode()
 * @method static bool isDebugModeEnabled()
 * @method static array getDevEnvironmentInfo()
 * @method static array getJsDependencies(bool $includeDevDependencies = true)
 * @method static string generatePackageJson(bool $includeDevDependencies = true)
 */
class Tapped extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'tapped';
    }
}
