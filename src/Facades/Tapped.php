<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\Facades;

use Illuminate\Support\Facades\Facade;
use ThinkNeverland\Tapped\Support\Tapped as TappedSupport;

/**
 * @method static void startCollectors()
 * @method static void stopCollectors()
 * @method static string storeRequest()
 * @method static array retrieveRequest(string $requestId)
 * @method static array listRequests(?int $limit = null, int $offset = 0)
 * @method static \ThinkNeverland\Tapped\Contracts\DataCollector|null getCollector(string $collectorClass)
 * @method static \Illuminate\Support\Collection getAllCollectors()
 * @method static array getData()
 *
 * @see \ThinkNeverland\Tapped\Support\Tapped
 */
class Tapped extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'tapped';
    }
}
