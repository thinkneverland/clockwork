<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use ThinkNeverland\Tapped\Contracts\StorageManager;
use ThinkNeverland\Tapped\DataCollectors\RequestCollector;
use ThinkNeverland\Tapped\DataCollectors\LivewireRequestCollector;

class RequestController extends Controller
{
    /**
     * @var StorageManager
     */
    protected StorageManager $storage;

    /**
     * RequestController constructor.
     *
     * @param StorageManager $storage
     */
    public function __construct(StorageManager $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Display the request overview page.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Get filter parameters
        $filter = $request->input('filter', '');
        $method = $request->input('method', '');
        $statusCode = $request->input('status', '');
        $page = max(1, (int)$request->input('page', 1));
        $perPage = 20;
        
        // Get requests with possible filtering
        $requests = $this->getFilteredRequests($filter, $method, $statusCode, $page, $perPage);
        
        // Generate stats for the view
        $methodStats = $this->getMethodStats();
        $statusCodeStats = $this->getStatusCodeStats();
        
        return View::make('tapped::requests.index', [
            'requests' => $requests['data'],
            'pagination' => [
                'current' => $page,
                'total' => $requests['total_pages'],
                'count' => $requests['total']
            ],
            'methodStats' => $methodStats,
            'statusCodeStats' => $statusCodeStats,
            'filter' => $filter,
            'method' => $method,
            'status' => $statusCode
        ]);
    }

    /**
     * Display detailed information about a single request.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\View\View
     */
    public function show(Request $request, string $id)
    {
        $requestData = $this->storage->find($id);
        
        if (!$requestData) {
            abort(404, 'Request not found');
        }
        
        // Get Livewire requests associated with this request
        $livewireRequests = $this->getLivewireRequests($id);
        
        return View::make('tapped::requests.show', [
            'request' => $requestData,
            'livewireRequests' => $livewireRequests
        ]);
    }

    /**
     * Get filtered requests with pagination.
     *
     * @param string $filter
     * @param string $method
     * @param string $statusCode
     * @param int $page
     * @param int $perPage
     * @return array
     */
    protected function getFilteredRequests(string $filter, string $method, string $statusCode, int $page, int $perPage): array
    {
        $allRequests = $this->storage->all();
        $filteredRequests = [];
        
        foreach ($allRequests as $request) {
            if (!isset($request['data'][RequestCollector::class])) {
                continue;
            }
            
            $requestData = $request['data'][RequestCollector::class];
            
            // Apply filters
            if ($filter && !$this->matchesFilter($requestData, $filter)) {
                continue;
            }
            
            if ($method && (!isset($requestData['method']) || $requestData['method'] !== $method)) {
                continue;
            }
            
            if ($statusCode && (!isset($requestData['response_status']) || (string)$requestData['response_status'] !== $statusCode)) {
                continue;
            }
            
            $filteredRequests[] = $request;
        }
        
        // Calculate pagination
        $total = count($filteredRequests);
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $pagedRequests = array_slice($filteredRequests, $offset, $perPage);
        
        return [
            'data' => $pagedRequests,
            'total' => $total,
            'total_pages' => $totalPages
        ];
    }

    /**
     * Check if request matches the filter string.
     *
     * @param array $requestData
     * @param string $filter
     * @return bool
     */
    protected function matchesFilter(array $requestData, string $filter): bool
    {
        // Check if the filter matches the URL
        if (isset($requestData['uri']) && stripos($requestData['uri'], $filter) !== false) {
            return true;
        }
        
        // Check if the filter matches the controller or action
        if (isset($requestData['controller']) && stripos($requestData['controller'], $filter) !== false) {
            return true;
        }
        
        return false;
    }

    /**
     * Get statistics about request methods.
     *
     * @return array
     */
    protected function getMethodStats(): array
    {
        $stats = [
            'GET' => 0,
            'POST' => 0,
            'PUT' => 0,
            'PATCH' => 0,
            'DELETE' => 0,
            'OPTIONS' => 0,
            'HEAD' => 0,
        ];
        
        $requests = $this->storage->all();
        
        foreach ($requests as $request) {
            if (isset($request['data'][RequestCollector::class]['method'])) {
                $method = $request['data'][RequestCollector::class]['method'];
                if (isset($stats[$method])) {
                    $stats[$method]++;
                }
            }
        }
        
        return $stats;
    }

    /**
     * Get statistics about response status codes.
     *
     * @return array
     */
    protected function getStatusCodeStats(): array
    {
        $stats = [
            '2xx' => 0,
            '3xx' => 0,
            '4xx' => 0,
            '5xx' => 0,
        ];
        
        $requests = $this->storage->all();
        
        foreach ($requests as $request) {
            if (isset($request['data'][RequestCollector::class]['response_status'])) {
                $status = (int)$request['data'][RequestCollector::class]['response_status'];
                
                if ($status >= 200 && $status < 300) {
                    $stats['2xx']++;
                } elseif ($status >= 300 && $status < 400) {
                    $stats['3xx']++;
                } elseif ($status >= 400 && $status < 500) {
                    $stats['4xx']++;
                } elseif ($status >= 500) {
                    $stats['5xx']++;
                }
            }
        }
        
        return $stats;
    }

    /**
     * Get Livewire AJAX requests associated with a request.
     *
     * @param string $requestId
     * @return array
     */
    protected function getLivewireRequests(string $requestId): array
    {
        $livewireRequests = [];
        $allRequests = $this->storage->all();
        
        foreach ($allRequests as $request) {
            if (isset($request['data'][LivewireRequestCollector::class])) {
                $livewireRequest = $request['data'][LivewireRequestCollector::class];
                
                if (isset($livewireRequest['parent_request_id']) && $livewireRequest['parent_request_id'] === $requestId) {
                    $livewireRequests[] = $request;
                }
            }
        }
        
        return $livewireRequests;
    }
}
