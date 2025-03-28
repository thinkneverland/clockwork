<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use ThinkNeverland\Tapped\Contracts\StorageManager;
use ThinkNeverland\Tapped\DataCollectors\DatabaseQueryCollector;
use ThinkNeverland\Tapped\DataCollectors\LivewireCollector;
use ThinkNeverland\Tapped\DataCollectors\RequestCollector;

class DashboardController extends Controller
{
    /**
     * @var StorageManager
     */
    protected StorageManager $storage;

    /**
     * DashboardController constructor.
     *
     * @param StorageManager $storage
     */
    public function __construct(StorageManager $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Display the dashboard overview.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Get recent requests (last 10)
        $requests = $this->storage->latest(10);
        
        // Dashboard stats
        $stats = [
            'total_requests' => $this->storage->count(),
            'request_count_today' => $this->countRequestsToday($requests),
            'average_response_time' => $this->calculateAverageResponseTime($requests),
            'livewire_components' => $this->countLivewireComponents($requests),
            'database_queries' => $this->countDatabaseQueries($requests),
            'error_count' => $this->countErrors($requests),
        ];
        
        return View::make('tapped::dashboard.index', [
            'requests' => $requests,
            'stats' => $stats
        ]);
    }
    
    /**
     * Count requests made today.
     *
     * @param array $requests
     * @return int
     */
    protected function countRequestsToday(array $requests): int
    {
        $today = date('Y-m-d');
        $count = 0;
        
        foreach ($requests as $request) {
            if (isset($request['data'][RequestCollector::class]['time'])) {
                $requestDate = substr($request['data'][RequestCollector::class]['time'], 0, 10);
                if ($requestDate === $today) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Calculate average response time for requests.
     *
     * @param array $requests
     * @return float
     */
    protected function calculateAverageResponseTime(array $requests): float
    {
        $total = 0;
        $count = 0;
        
        foreach ($requests as $request) {
            if (isset($request['data'][RequestCollector::class]['response_time'])) {
                $total += $request['data'][RequestCollector::class]['response_time'];
                $count++;
            }
        }
        
        return $count > 0 ? round($total / $count, 2) : 0;
    }
    
    /**
     * Count Livewire components.
     *
     * @param array $requests
     * @return int
     */
    protected function countLivewireComponents(array $requests): int
    {
        $total = 0;
        
        foreach ($requests as $request) {
            if (isset($request['data'][LivewireCollector::class]['components'])) {
                $total += count($request['data'][LivewireCollector::class]['components']);
            }
        }
        
        return $total;
    }
    
    /**
     * Count database queries.
     *
     * @param array $requests
     * @return int
     */
    protected function countDatabaseQueries(array $requests): int
    {
        $total = 0;
        
        foreach ($requests as $request) {
            if (isset($request['data'][DatabaseQueryCollector::class]['queries'])) {
                $total += count($request['data'][DatabaseQueryCollector::class]['queries']);
            }
        }
        
        return $total;
    }
    
    /**
     * Count errors.
     *
     * @param array $requests
     * @return int
     */
    protected function countErrors(array $requests): int
    {
        $total = 0;
        
        foreach ($requests as $request) {
            if (isset($request['data'][RequestCollector::class]['response_status'])) {
                $status = $request['data'][RequestCollector::class]['response_status'];
                if ($status >= 400) {
                    $total++;
                }
            }
        }
        
        return $total;
    }
}
