<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use ThinkNeverland\Tapped\Contracts\StorageManager;
use ThinkNeverland\Tapped\Livewire\StateManager;
use ThinkNeverland\Tapped\DataCollectors\LivewireCollector;
use ThinkNeverland\Tapped\DataCollectors\LivewireEventCollector;

class LivewireController extends Controller
{
    /**
     * @var StorageManager
     */
    protected StorageManager $storage;

    /**
     * @var StateManager
     */
    protected StateManager $stateManager;

    /**
     * LivewireController constructor.
     *
     * @param StorageManager $storage
     * @param StateManager $stateManager
     */
    public function __construct(StorageManager $storage, StateManager $stateManager)
    {
        $this->storage = $storage;
        $this->stateManager = $stateManager;
    }

    /**
     * Display the Livewire components overview.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Get recent requests with Livewire components
        $requests = $this->getRequestsWithLivewireComponents(10);
        
        // Statistics for the overview
        $stats = [
            'total_components' => $this->countTotalComponents($requests),
            'unique_components' => $this->countUniqueComponents($requests),
            'event_count' => $this->countEvents($requests),
            'property_updates' => $this->countPropertyUpdates($requests),
        ];
        
        return View::make('tapped::livewire.index', [
            'requests' => $requests,
            'stats' => $stats
        ]);
    }

    /**
     * Get all active components.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function components(Request $request)
    {
        $components = $this->stateManager->getAllTrackedComponents();
        
        return response()->json([
            'components' => $components
        ]);
    }

    /**
     * Display details for a specific component.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\View\View
     */
    public function component(Request $request, string $id)
    {
        $component = $this->stateManager->getComponentData($id);
        $stateHistory = $this->stateManager->getStateHistory($id);
        $branches = $this->stateManager->getBranches($id);
        
        if (!$component) {
            abort(404, 'Component not found');
        }
        
        return View::make('tapped::livewire.component', [
            'component' => $component,
            'stateHistory' => $stateHistory,
            'branches' => $branches
        ]);
    }

    /**
     * Update a component's state.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateComponent(Request $request, string $id)
    {
        $property = $request->input('property');
        $value = $request->input('value');
        $branchId = $request->input('branch_id');
        
        try {
            $result = $this->stateManager->updateComponentProperty($id, $property, $value, $branchId);
            
            return response()->json([
                'success' => true,
                'message' => "Property '{$property}' updated successfully",
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Execute a component method.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function executeMethod(Request $request, string $id)
    {
        $method = $request->input('method');
        $params = $request->input('params', []);
        
        try {
            $result = $this->stateManager->executeComponentMethod($id, $method, $params);
            
            return response()->json([
                'success' => true,
                'message' => "Method '{$method}' executed successfully",
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get requests with Livewire components.
     *
     * @param int $limit
     * @return array
     */
    protected function getRequestsWithLivewireComponents(int $limit): array
    {
        $allRequests = $this->storage->latest($limit * 2); // Get more to filter
        $requests = [];
        
        foreach ($allRequests as $request) {
            if (isset($request['data'][LivewireCollector::class]['components']) && 
                !empty($request['data'][LivewireCollector::class]['components'])) {
                $requests[] = $request;
                
                if (count($requests) >= $limit) {
                    break;
                }
            }
        }
        
        return $requests;
    }

    /**
     * Count total components across requests.
     *
     * @param array $requests
     * @return int
     */
    protected function countTotalComponents(array $requests): int
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
     * Count unique component classes across requests.
     *
     * @param array $requests
     * @return int
     */
    protected function countUniqueComponents(array $requests): int
    {
        $uniqueComponents = [];
        
        foreach ($requests as $request) {
            if (isset($request['data'][LivewireCollector::class]['components'])) {
                foreach ($request['data'][LivewireCollector::class]['components'] as $component) {
                    if (isset($component['class'])) {
                        $uniqueComponents[$component['class']] = true;
                    }
                }
            }
        }
        
        return count($uniqueComponents);
    }

    /**
     * Count Livewire events across requests.
     *
     * @param array $requests
     * @return int
     */
    protected function countEvents(array $requests): int
    {
        $total = 0;
        
        foreach ($requests as $request) {
            if (isset($request['data'][LivewireEventCollector::class]['events'])) {
                $total += count($request['data'][LivewireEventCollector::class]['events']);
            }
        }
        
        return $total;
    }

    /**
     * Count property updates across requests.
     *
     * @param array $requests
     * @return int
     */
    protected function countPropertyUpdates(array $requests): int
    {
        $total = 0;
        
        foreach ($requests as $request) {
            if (isset($request['data'][LivewireCollector::class]['updates'])) {
                $total += count($request['data'][LivewireCollector::class]['updates']);
            }
        }
        
        return $total;
    }
}
