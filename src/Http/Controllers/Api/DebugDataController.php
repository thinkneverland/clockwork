<?php

namespace ThinkNeverland\Tapped\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ThinkNeverland\Tapped\DataCollectors\LivewireCollector;
use ThinkNeverland\Tapped\DataCollectors\QueryCollector;
use ThinkNeverland\Tapped\DataCollectors\RequestCollector;
use ThinkNeverland\Tapped\DataCollectors\EventCollector;
use ThinkNeverland\Tapped\Services\DebugStateSerializer;

class DebugDataController extends ApiController
{
    protected $livewireCollector;
    protected $queryCollector;
    protected $requestCollector;
    protected $eventCollector;
    protected $debugStateSerializer;

    /**
     * Constructor with dependency injection.
     */
    public function __construct(
        LivewireCollector $livewireCollector,
        QueryCollector $queryCollector,
        RequestCollector $requestCollector,
        EventCollector $eventCollector,
        DebugStateSerializer $debugStateSerializer
    ) {
        $this->livewireCollector = $livewireCollector;
        $this->queryCollector = $queryCollector;
        $this->requestCollector = $requestCollector;
        $this->eventCollector = $eventCollector;
        $this->debugStateSerializer = $debugStateSerializer;
    }

    /**
     * Get all debug data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $format = $request->query('format', 'json');
        $data = [
            'livewire' => $this->livewireCollector->getData(),
            'queries' => $this->queryCollector->getData(),
            'requests' => $this->requestCollector->getData(),
            'events' => $this->eventCollector->getData(),
        ];

        if ($format === 'binary') {
            $serialized = $this->debugStateSerializer->serializeToBinary($data);
            return response()->make($serialized, 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="tapped-debug-data.bin"',
            ]);
        }

        return $this->respondWithSuccess($data);
    }

    /**
     * Get Livewire component data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getLivewireData(Request $request): JsonResponse
    {
        $componentId = $request->query('component_id');
        $data = $this->livewireCollector->getData();

        if ($componentId) {
            $components = array_filter($data, function ($component) use ($componentId) {
                return $component['id'] === $componentId;
            });
            
            if (empty($components)) {
                return $this->respondNotFound("Livewire component with ID {$componentId} not found");
            }
            
            return $this->respondWithSuccess(reset($components));
        }

        return $this->respondWithSuccess($data);
    }

    /**
     * Get database query data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getQueryData(Request $request): JsonResponse
    {
        $n1Detection = $request->query('n1_detection', false);
        $data = $this->queryCollector->getData();

        if ($n1Detection) {
            $n1Queries = $this->queryCollector->detectN1Queries();
            return $this->respondWithSuccess([
                'queries' => $data,
                'n1_issues' => $n1Queries,
            ]);
        }

        return $this->respondWithSuccess($data);
    }

    /**
     * Get HTTP request data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getRequestData(Request $request): JsonResponse
    {
        $requestId = $request->query('request_id');
        $data = $this->requestCollector->getData();

        if ($requestId) {
            $requests = array_filter($data, function ($req) use ($requestId) {
                return $req['id'] === $requestId;
            });
            
            if (empty($requests)) {
                return $this->respondNotFound("Request with ID {$requestId} not found");
            }
            
            return $this->respondWithSuccess(reset($requests));
        }

        return $this->respondWithSuccess($data);
    }

    /**
     * Get event data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getEventData(Request $request): JsonResponse
    {
        $eventType = $request->query('type');
        $data = $this->eventCollector->getData();

        if ($eventType) {
            $filteredEvents = array_filter($data, function ($event) use ($eventType) {
                return $event['type'] === $eventType;
            });
            
            return $this->respondWithSuccess(array_values($filteredEvents));
        }

        return $this->respondWithSuccess($data);
    }

    /**
     * Capture a debug snapshot.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function captureSnapshot(Request $request): JsonResponse
    {
        $label = $request->input('label', 'Snapshot ' . date('Y-m-d H:i:s'));
        
        $data = [
            'label' => $label,
            'timestamp' => now()->timestamp,
            'livewire' => $this->livewireCollector->getData(),
            'queries' => $this->queryCollector->getData(),
            'requests' => $this->requestCollector->getData(),
            'events' => $this->eventCollector->getData(),
        ];
        
        $snapshot = $this->debugStateSerializer->createSnapshot($data);
        
        return $this->respondWithSuccess([
            'snapshot_id' => $snapshot['id'],
            'label' => $snapshot['label'],
            'timestamp' => $snapshot['timestamp'],
        ]);
    }

    /**
     * Get a list of all snapshots.
     *
     * @return JsonResponse
     */
    public function getSnapshots(): JsonResponse
    {
        $snapshots = $this->debugStateSerializer->listSnapshots();
        return $this->respondWithSuccess($snapshots);
    }

    /**
     * Get a specific snapshot by ID.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function getSnapshot(string $id): JsonResponse
    {
        $snapshot = $this->debugStateSerializer->getSnapshot($id);
        
        if (!$snapshot) {
            return $this->respondNotFound("Snapshot with ID {$id} not found");
        }
        
        return $this->respondWithSuccess($snapshot);
    }

    /**
     * Delete a snapshot.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function deleteSnapshot(string $id): JsonResponse
    {
        $success = $this->debugStateSerializer->deleteSnapshot($id);
        
        if (!$success) {
            return $this->respondNotFound("Snapshot with ID {$id} not found");
        }
        
        return $this->respondWithSuccess(null, "Snapshot deleted successfully");
    }
}
