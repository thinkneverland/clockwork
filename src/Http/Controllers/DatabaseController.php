<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use ThinkNeverland\Tapped\Contracts\StorageManager;
use ThinkNeverland\Tapped\DataCollectors\DatabaseQueryCollector;
use ThinkNeverland\Tapped\DataCollectors\ModelCollector;

class DatabaseController extends Controller
{
    /**
     * @var StorageManager
     */
    protected StorageManager $storage;

    /**
     * DatabaseController constructor.
     *
     * @param StorageManager $storage
     */
    public function __construct(StorageManager $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Display the database overview.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Get recent requests with database queries
        $requests = $this->getRequestsWithDatabaseQueries(10);
        
        // Calculate statistics for the overview
        $stats = [
            'total_queries' => $this->countTotalQueries($requests),
            'slow_queries' => $this->countSlowQueries($requests),
            'n_plus_one' => $this->countNPlusOneQueries($requests),
            'avg_query_time' => $this->calculateAverageQueryTime($requests),
        ];
        
        return View::make('tapped::database.index', [
            'requests' => $requests,
            'stats' => $stats
        ]);
    }

    /**
     * Display database queries.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function queries(Request $request)
    {
        // Get filter parameters
        $filter = $request->input('filter', '');
        $connection = $request->input('connection', '');
        $timeThreshold = (float) $request->input('threshold', 0);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;
        
        // Get queries with possible filtering
        $queries = $this->getFilteredQueries($filter, $connection, $timeThreshold, $page, $perPage);
        
        return View::make('tapped::database.queries', [
            'queries' => $queries['data'],
            'pagination' => [
                'current' => $page,
                'total' => $queries['total_pages'],
                'count' => $queries['total']
            ],
            'filter' => $filter,
            'connection' => $connection,
            'timeThreshold' => $timeThreshold,
            'connections' => $this->getUniqueConnections()
        ]);
    }

    /**
     * Display model operations.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function models(Request $request)
    {
        // Get filter parameters
        $filter = $request->input('filter', '');
        $operation = $request->input('operation', '');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;
        
        // Get model operations with possible filtering
        $models = $this->getFilteredModelOperations($filter, $operation, $page, $perPage);
        
        $stats = [
            'creates' => $this->countModelOperationsByType('create'),
            'updates' => $this->countModelOperationsByType('update'),
            'deletes' => $this->countModelOperationsByType('delete'),
            'unique_models' => count($this->getUniqueModels())
        ];
        
        return View::make('tapped::database.models', [
            'models' => $models['data'],
            'pagination' => [
                'current' => $page,
                'total' => $models['total_pages'],
                'count' => $models['total']
            ],
            'filter' => $filter,
            'operation' => $operation,
            'stats' => $stats
        ]);
    }

    /**
     * Get requests with database queries.
     *
     * @param int $limit
     * @return array
     */
    protected function getRequestsWithDatabaseQueries(int $limit): array
    {
        $allRequests = $this->storage->latest($limit * 2); // Get more to filter
        $requests = [];
        
        foreach ($allRequests as $request) {
            if (isset($request['data'][DatabaseQueryCollector::class]['queries']) && 
                !empty($request['data'][DatabaseQueryCollector::class]['queries'])) {
                $requests[] = $request;
                
                if (count($requests) >= $limit) {
                    break;
                }
            }
        }
        
        return $requests;
    }

    /**
     * Count total queries across requests.
     *
     * @param array $requests
     * @return int
     */
    protected function countTotalQueries(array $requests): int
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
     * Count slow queries (taking more than 100ms).
     *
     * @param array $requests
     * @return int
     */
    protected function countSlowQueries(array $requests): int
    {
        $count = 0;
        
        foreach ($requests as $request) {
            if (isset($request['data'][DatabaseQueryCollector::class]['queries'])) {
                foreach ($request['data'][DatabaseQueryCollector::class]['queries'] as $query) {
                    if (isset($query['time']) && $query['time'] > 100) {
                        $count++;
                    }
                }
            }
        }
        
        return $count;
    }

    /**
     * Count N+1 query issues detected.
     *
     * @param array $requests
     * @return int
     */
    protected function countNPlusOneQueries(array $requests): int
    {
        $count = 0;
        
        foreach ($requests as $request) {
            if (isset($request['data'][DatabaseQueryCollector::class]['n_plus_one'])) {
                $count += count($request['data'][DatabaseQueryCollector::class]['n_plus_one']);
            }
        }
        
        return $count;
    }

    /**
     * Calculate average query time.
     *
     * @param array $requests
     * @return float
     */
    protected function calculateAverageQueryTime(array $requests): float
    {
        $totalTime = 0;
        $queryCount = 0;
        
        foreach ($requests as $request) {
            if (isset($request['data'][DatabaseQueryCollector::class]['queries'])) {
                foreach ($request['data'][DatabaseQueryCollector::class]['queries'] as $query) {
                    if (isset($query['time'])) {
                        $totalTime += $query['time'];
                        $queryCount++;
                    }
                }
            }
        }
        
        return $queryCount > 0 ? round($totalTime / $queryCount, 2) : 0;
    }

    /**
     * Get filtered database queries with pagination.
     *
     * @param string $filter
     * @param string $connection
     * @param float $timeThreshold
     * @param int $page
     * @param int $perPage
     * @return array
     */
    protected function getFilteredQueries(string $filter, string $connection, float $timeThreshold, int $page, int $perPage): array
    {
        $allRequests = $this->storage->all();
        $allQueries = [];
        
        foreach ($allRequests as $request) {
            if (isset($request['data'][DatabaseQueryCollector::class]['queries'])) {
                foreach ($request['data'][DatabaseQueryCollector::class]['queries'] as $query) {
                    $query['request_id'] = $request['id'];
                    $allQueries[] = $query;
                }
            }
        }
        
        // Filter queries
        $filteredQueries = array_filter($allQueries, function ($query) use ($filter, $connection, $timeThreshold) {
            // Filter by query text
            if ($filter && (!isset($query['query']) || stripos($query['query'], $filter) === false)) {
                return false;
            }
            
            // Filter by connection
            if ($connection && (!isset($query['connection']) || $query['connection'] !== $connection)) {
                return false;
            }
            
            // Filter by time threshold
            if ($timeThreshold > 0 && (!isset($query['time']) || $query['time'] < $timeThreshold)) {
                return false;
            }
            
            return true;
        });
        
        // Sort by time (descending)
        usort($filteredQueries, function ($a, $b) {
            $timeA = $a['time'] ?? 0;
            $timeB = $b['time'] ?? 0;
            return $timeB <=> $timeA;
        });
        
        // Calculate pagination
        $total = count($filteredQueries);
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $pagedQueries = array_slice($filteredQueries, $offset, $perPage);
        
        return [
            'data' => $pagedQueries,
            'total' => $total,
            'total_pages' => $totalPages
        ];
    }

    /**
     * Get unique database connections.
     *
     * @return array
     */
    protected function getUniqueConnections(): array
    {
        $connections = [];
        $allRequests = $this->storage->all();
        
        foreach ($allRequests as $request) {
            if (isset($request['data'][DatabaseQueryCollector::class]['queries'])) {
                foreach ($request['data'][DatabaseQueryCollector::class]['queries'] as $query) {
                    if (isset($query['connection'])) {
                        $connections[$query['connection']] = true;
                    }
                }
            }
        }
        
        return array_keys($connections);
    }

    /**
     * Get filtered model operations with pagination.
     *
     * @param string $filter
     * @param string $operation
     * @param int $page
     * @param int $perPage
     * @return array
     */
    protected function getFilteredModelOperations(string $filter, string $operation, int $page, int $perPage): array
    {
        $allRequests = $this->storage->all();
        $allOperations = [];
        
        foreach ($allRequests as $request) {
            if (isset($request['data'][ModelCollector::class]['models'])) {
                foreach ($request['data'][ModelCollector::class]['models'] as $modelOperation) {
                    $modelOperation['request_id'] = $request['id'];
                    $allOperations[] = $modelOperation;
                }
            }
        }
        
        // Filter operations
        $filteredOperations = array_filter($allOperations, function ($op) use ($filter, $operation) {
            // Filter by model class
            if ($filter && (!isset($op['model']) || stripos($op['model'], $filter) === false)) {
                return false;
            }
            
            // Filter by operation type
            if ($operation && (!isset($op['operation']) || $op['operation'] !== $operation)) {
                return false;
            }
            
            return true;
        });
        
        // Sort by time (descending)
        usort($filteredOperations, function ($a, $b) {
            $timeA = $a['time'] ?? 0;
            $timeB = $b['time'] ?? 0;
            return $timeB <=> $timeA;
        });
        
        // Calculate pagination
        $total = count($filteredOperations);
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $pagedOperations = array_slice($filteredOperations, $offset, $perPage);
        
        return [
            'data' => $pagedOperations,
            'total' => $total,
            'total_pages' => $totalPages
        ];
    }

    /**
     * Count model operations by operation type.
     *
     * @param string $type
     * @return int
     */
    protected function countModelOperationsByType(string $type): int
    {
        $count = 0;
        $allRequests = $this->storage->all();
        
        foreach ($allRequests as $request) {
            if (isset($request['data'][ModelCollector::class]['models'])) {
                foreach ($request['data'][ModelCollector::class]['models'] as $modelOperation) {
                    if (isset($modelOperation['operation']) && $modelOperation['operation'] === $type) {
                        $count++;
                    }
                }
            }
        }
        
        return $count;
    }

    /**
     * Get unique model classes.
     *
     * @return array
     */
    protected function getUniqueModels(): array
    {
        $models = [];
        $allRequests = $this->storage->all();
        
        foreach ($allRequests as $request) {
            if (isset($request['data'][ModelCollector::class]['models'])) {
                foreach ($request['data'][ModelCollector::class]['models'] as $modelOperation) {
                    if (isset($modelOperation['model'])) {
                        $models[$modelOperation['model']] = true;
                    }
                }
            }
        }
        
        return array_keys($models);
    }
}
