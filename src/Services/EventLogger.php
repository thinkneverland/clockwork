<?php

namespace ThinkNeverland\Tapped\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class EventLogger
{
    protected const CACHE_PREFIX = 'tapped_event_';
    protected const MAX_EVENTS = 1000;

    public function log(string $event, array $data = []): void
    {
        $events = $this->getEvents();

        // Add new event to the beginning
        array_unshift($events, [
            'event' => $event,
            'data' => $data,
            'timestamp' => Carbon::now()->toIso8601String(),
        ]);

        // Trim to max events
        if (count($events) > self::MAX_EVENTS) {
            $events = array_slice($events, 0, self::MAX_EVENTS);
        }

        Cache::put('tapped_events', $events, Carbon::now()->addDay());
    }

    public function getEvents(int $limit = null): array
    {
        $events = Cache::get('tapped_events', []);

        if ($limit && count($events) > $limit) {
            return array_slice($events, 0, $limit);
        }

        return $events;
    }

    public function clear(): void
    {
        Cache::forget('tapped_events');
    }

    public function getEventsByType(string $type, int $limit = null): array
    {
        return array_filter(
            $this->getEvents($limit),
            fn($event) => $event['event'] === $type
        );
    }

    public function getEventsBetween(Carbon $start, Carbon $end, int $limit = null): array
    {
        return array_filter(
            $this->getEvents($limit),
            function ($event) use ($start, $end) {
                $timestamp = Carbon::parse($event['timestamp']);
                return $timestamp->between($start, $end);
            }
        );
    }
}
