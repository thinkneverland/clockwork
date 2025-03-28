<?php

declare(strict_types=1);

namespace ThinkNeverland\Tapped\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use ThinkNeverland\Tapped\Contracts\StorageManager;
use ThinkNeverland\Tapped\DataCollectors\EventCollector;
use ThinkNeverland\Tapped\DataCollectors\LivewireEventCollector;
use ThinkNeverland\Tapped\DataCollectors\RequestCollector;

class TimelineController extends Controller
{
    /**
     * @var StorageManager
     */
    protected StorageManager $storage;

    /**
     * TimelineController constructor.
     *
     * @param StorageManager $storage
     */
    public function __construct(StorageManager $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Display the event timeline.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Get request ID or use latest if not specified
        $requestId = $request->input('request_id');
        $timelineData = $this->getTimelineData($requestId);
        
        return View::make('tapped::timeline.index', [
            'timeline' => $timelineData['timeline'],
            'requestId' => $timelineData['request_id'],
            'availableRequests' => $this->getAvailableRequests(),
            'stats' => [
                'events_count' => count($timelineData['timeline']),
                'duration' => $timelineData['duration']
            ]
        ]);
    }

    /**
     * Get events for a specific timeline.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function events(Request $request)
    {
        $requestId = $request->input('request_id');
        $filter = $request->input('filter', '');
        $type = $request->input('type', '');
        
        $timelineData = $this->getTimelineData($requestId);
        $events = $timelineData['timeline'];
        
        // Apply filters
        if ($filter || $type) {
            $events = array_filter($events, function ($event) use ($filter, $type) {
                $matchesFilter = !$filter || (
                    (isset($event['name']) && stripos($event['name'], $filter) !== false) ||
                    (isset($event['description']) && stripos($event['description'], $filter) !== false)
                );
                
                $matchesType = !$type || (isset($event['type']) && $event['type'] === $type);
                
                return $matchesFilter && $matchesType;
            });
        }
        
        return response()->json([
            'events' => array_values($events)
        ]);
    }

    /**
     * Get timeline data for a request.
     *
     * @param string|null $requestId
     * @return array
     */
    protected function getTimelineData(?string $requestId): array
    {
        // Get the request data
        $requestData = null;
        
        if ($requestId) {
            $requestData = $this->storage->find($requestId);
        }
        
        if (!$requestData) {
            // If no valid request ID, use latest request
            $latestRequests = $this->storage->latest(1);
            if (!empty($latestRequests)) {
                $requestData = $latestRequests[0];
                $requestId = $requestData['id'];
            }
        }
        
        if (!$requestData) {
            return [
                'timeline' => [],
                'request_id' => null,
                'duration' => 0
            ];
        }
        
        // Build timeline from various collectors
        $timeline = [];
        $startTime = null;
        $endTime = null;
        
        // Request events
        if (isset($requestData['data'][RequestCollector::class])) {
            $requestInfo = $requestData['data'][RequestCollector::class];
            
            if (isset($requestInfo['time'])) {
                $requestTime = strtotime($requestInfo['time']);
                $startTime = $requestTime;
                
                // Request start event
                $timeline[] = [
                    'type' => 'request',
                    'name' => 'Request Started',
                    'description' => $requestInfo['method'] . ' ' . $requestInfo['uri'],
                    'time' => $requestTime * 1000, // Convert to milliseconds for JS
                    'category' => 'request',
                    'data' => [
                        'method' => $requestInfo['method'],
                        'uri' => $requestInfo['uri'],
                        'controller' => $requestInfo['controller'] ?? null
                    ]
                ];
                
                // Request end event
                if (isset($requestInfo['response_time'])) {
                    $responseTime = $requestTime + ($requestInfo['response_time'] / 1000);
                    $endTime = $responseTime;
                    
                    $timeline[] = [
                        'type' => 'request',
                        'name' => 'Request Completed',
                        'description' => 'Status ' . ($requestInfo['response_status'] ?? 'unknown'),
                        'time' => $responseTime * 1000, // Convert to milliseconds for JS
                        'category' => 'request',
                        'data' => [
                            'status' => $requestInfo['response_status'] ?? null,
                            'duration' => $requestInfo['response_time'] ?? null
                        ]
                    ];
                }
            }
        }
        
        // Application events
        if (isset($requestData['data'][EventCollector::class]['events'])) {
            foreach ($requestData['data'][EventCollector::class]['events'] as $event) {
                $eventTime = isset($event['time']) ? strtotime($event['time']) : null;
                
                if ($eventTime) {
                    $timeline[] = [
                        'type' => 'event',
                        'name' => $event['name'] ?? 'Unknown Event',
                        'description' => 'Application Event',
                        'time' => $eventTime * 1000, // Convert to milliseconds for JS
                        'category' => 'app_event',
                        'data' => [
                            'name' => $event['name'] ?? null,
                            'listeners' => $event['listeners'] ?? [],
                            'data' => $event['data'] ?? null
                        ]
                    ];
                    
                    if ($startTime === null || $eventTime < $startTime) {
                        $startTime = $eventTime;
                    }
                    
                    if ($endTime === null || $eventTime > $endTime) {
                        $endTime = $eventTime;
                    }
                }
            }
        }
        
        // Livewire events
        if (isset($requestData['data'][LivewireEventCollector::class]['events'])) {
            foreach ($requestData['data'][LivewireEventCollector::class]['events'] as $event) {
                $eventTime = isset($event['time']) ? strtotime($event['time']) : null;
                
                if ($eventTime) {
                    $timeline[] = [
                        'type' => 'livewire_event',
                        'name' => $event['name'] ?? 'Unknown Livewire Event',
                        'description' => 'Livewire Event',
                        'time' => $eventTime * 1000, // Convert to milliseconds for JS
                        'category' => 'livewire_event',
                        'data' => [
                            'name' => $event['name'] ?? null,
                            'component' => $event['component'] ?? null,
                            'params' => $event['params'] ?? []
                        ]
                    ];
                    
                    if ($startTime === null || $eventTime < $startTime) {
                        $startTime = $eventTime;
                    }
                    
                    if ($endTime === null || $eventTime > $endTime) {
                        $endTime = $eventTime;
                    }
                }
            }
        }
        
        // Sort timeline by time
        usort($timeline, function ($a, $b) {
            return $a['time'] <=> $b['time'];
        });
        
        // Calculate total duration
        $duration = 0;
        if ($startTime !== null && $endTime !== null) {
            $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
        }
        
        return [
            'timeline' => $timeline,
            'request_id' => $requestId,
            'duration' => $duration
        ];
    }

    /**
     * Get available requests for timeline selection.
     *
     * @return array
     */
    protected function getAvailableRequests(): array
    {
        $requests = $this->storage->latest(10);
        $result = [];
        
        foreach ($requests as $request) {
            if (isset($request['data'][RequestCollector::class])) {
                $requestInfo = $request['data'][RequestCollector::class];
                
                $result[] = [
                    'id' => $request['id'],
                    'uri' => $requestInfo['uri'] ?? 'Unknown URI',
                    'method' => $requestInfo['method'] ?? 'UNKNOWN',
                    'time' => $requestInfo['time'] ?? null,
                    'has_events' => (
                        isset($request['data'][EventCollector::class]['events']) ||
                        isset($request['data'][LivewireEventCollector::class]['events'])
                    )
                ];
            }
        }
        
        return $result;
    }
}
