<?php

namespace ThinkNeverland\Tapped\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use ThinkNeverland\Tapped\Services\LivewireStateManager;
use ThinkNeverland\Tapped\Services\EventLogger;

class AiController
{
    protected LivewireStateManager $stateManager;
    protected EventLogger $eventLogger;

    public function __construct(LivewireStateManager $stateManager, EventLogger $eventLogger)
    {
        $this->stateManager = $stateManager;
        $this->eventLogger = $eventLogger;
    }

    public function getDebugInfo(Request $request): JsonResponse
    {
        return response()->json([
            'states' => $this->stateManager->getState(null),
            'events' => $this->eventLogger->getEvents(100),
            'timestamp' => Carbon::now()->toIso8601String(),
        ]);
    }

    public function getState(Request $request): JsonResponse
    {
        $componentId = $request->input('component_id');
        $snapshotId = $request->input('snapshot_id');

        if ($snapshotId) {
            $state = $this->stateManager->getSnapshot($componentId, $snapshotId);
        } else {
            $state = $this->stateManager->getState($componentId);
        }

        return response()->json([
            'state' => $state,
            'timestamp' => Carbon::now()->toIso8601String(),
        ]);
    }

    public function getLogs(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $limit = $request->input('limit', 100);
        $start = $request->input('start');
        $end = $request->input('end');

        if ($start && $end) {
            $events = $this->eventLogger->getEventsBetween(
                Carbon::parse($start),
                Carbon::parse($end),
                $limit
            );
        } elseif ($type) {
            $events = $this->eventLogger->getEventsByType($type, $limit);
        } else {
            $events = $this->eventLogger->getEvents($limit);
        }

        return response()->json([
            'events' => $events,
            'timestamp' => Carbon::now()->toIso8601String(),
        ]);
    }

    public function storeScreenshot(Request $request): JsonResponse
    {
        $image = $request->file('screenshot');
        $componentId = $request->input('component_id');

        if (!$image || !$componentId) {
            return response()->json([
                'error' => 'Missing required parameters',
            ], 422);
        }

        $path = $image->store('screenshots', 'public');

        return response()->json([
            'url' => Storage::disk('public')->url($path),
            'timestamp' => Carbon::now()->toIso8601String(),
        ]);
    }
}
