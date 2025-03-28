@extends('tapped::layouts.app')

@section('content')
<div class="space-y-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Event Timeline</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Chronological view of application events</p>
    </div>

    <!-- Filter Controls -->
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
        <form action="{{ route('tapped.timeline') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                <input type="text" name="filter" id="filter" value="{{ $filter ?? '' }}" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
            <div>
                <label for="event_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Event Type</label>
                <select name="event_type" id="event_type" class="mt-1 block w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm text-gray-900 dark:text-gray-100">
                    <option value="">All Types</option>
                    <option value="request" {{ ($event_type ?? '') === 'request' ? 'selected' : '' }}>HTTP Request</option>
                    <option value="livewire" {{ ($event_type ?? '') === 'livewire' ? 'selected' : '' }}>Livewire Action</option>
                    <option value="database" {{ ($event_type ?? '') === 'database' ? 'selected' : '' }}>Database Query</option>
                    <option value="event" {{ ($event_type ?? '') === 'event' ? 'selected' : '' }}>Application Event</option>
                    <option value="error" {{ ($event_type ?? '') === 'error' ? 'selected' : '' }}>Error</option>
                </select>
            </div>
            <div>
                <label for="time_range" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Time Range</label>
                <select name="time_range" id="time_range" class="mt-1 block w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm text-gray-900 dark:text-gray-100">
                    <option value="5" {{ ($time_range ?? '') === '5' ? 'selected' : '' }}>Last 5 minutes</option>
                    <option value="15" {{ ($time_range ?? '') === '15' ? 'selected' : '' }}>Last 15 minutes</option>
                    <option value="30" {{ ($time_range ?? '') === '30' ? 'selected' : '' }}>Last 30 minutes</option>
                    <option value="60" {{ ($time_range ?? '') === '60' ? 'selected' : '' }}>Last hour</option>
                    <option value="all" {{ ($time_range ?? '') === 'all' ? 'selected' : '' }}>All time</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-indigo-100 dark:bg-indigo-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">Total Events</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['total_events'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">HTTP Requests</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['request_count'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-purple-100 dark:bg-purple-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">Livewire Actions</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['livewire_count'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-green-100 dark:bg-green-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">Database Queries</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['query_count'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-red-100 dark:bg-red-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">Errors</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['error_count'] ?? 0 }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline View -->
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Event Timeline</h2>
            <div>
                <button id="refreshTimeline" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Refresh
                </button>
            </div>
        </div>
        <div class="relative min-h-[400px] p-4">
            <div class="absolute top-0 bottom-0 left-16 w-0.5 bg-gray-200 dark:bg-gray-700"></div>
            
            <div id="timeline-events" class="space-y-6">
                @forelse($events as $event)
                    @php
                        $eventIconBg = 'bg-indigo-100 dark:bg-indigo-900';
                        $eventIconColor = 'text-indigo-600 dark:text-indigo-400';
                        $eventIcon = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>';
                        
                        if ($event['type'] === 'request') {
                            $eventIconBg = 'bg-blue-100 dark:bg-blue-900';
                            $eventIconColor = 'text-blue-600 dark:text-blue-400';
                            $eventIcon = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>';
                        } elseif ($event['type'] === 'livewire') {
                            $eventIconBg = 'bg-purple-100 dark:bg-purple-900';
                            $eventIconColor = 'text-purple-600 dark:text-purple-400';
                            $eventIcon = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>';
                        } elseif ($event['type'] === 'database') {
                            $eventIconBg = 'bg-green-100 dark:bg-green-900';
                            $eventIconColor = 'text-green-600 dark:text-green-400';
                            $eventIcon = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>';
                        } elseif ($event['type'] === 'error') {
                            $eventIconBg = 'bg-red-100 dark:bg-red-900';
                            $eventIconColor = 'text-red-600 dark:text-red-400';
                            $eventIcon = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                        }
                    @endphp
                    
                    <div class="relative flex items-start group">
                        <div class="h-12 w-12 flex items-center justify-center rounded-full {{ $eventIconBg }} {{ $eventIconColor }} z-10 relative">
                            {!! $eventIcon !!}
                        </div>
                        <div class="min-w-0 flex-1 ml-6">
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow-sm hover:shadow transition-shadow duration-200">
                                <div class="flex justify-between items-center mb-2">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $event['name'] }}</h3>
                                    <time class="text-sm text-gray-500 dark:text-gray-400">{{ $event['time'] }}</time>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">{{ $event['description'] }}</div>
                                
                                @if(!empty($event['details']))
                                <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-600">
                                    <details class="text-sm">
                                        <summary class="font-medium text-indigo-600 dark:text-indigo-400 cursor-pointer">View Details</summary>
                                        <div class="mt-2 bg-gray-100 dark:bg-gray-800 p-3 rounded text-gray-800 dark:text-gray-200 overflow-x-auto text-xs">
                                            <pre>{{ print_r($event['details'], true) }}</pre>
                                        </div>
                                    </details>
                                </div>
                                @endif
                                
                                @if(!empty($event['links']))
                                <div class="mt-2 flex space-x-4">
                                    @foreach($event['links'] as $link)
                                    <a href="{{ $link['url'] }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">
                                        {{ $link['label'] }}
                                    </a>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex justify-center items-center py-12">
                        <div class="text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No events found</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try changing your filters or time range.</p>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
        
        <!-- Load More Button -->
        @if(isset($has_more) && $has_more)
        <div class="px-4 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-center">
            <button id="loadMoreEvents" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Load More
            </button>
        </div>
        @endif
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const refreshButton = document.getElementById('refreshTimeline');
        const loadMoreButton = document.getElementById('loadMoreEvents');
        
        if (refreshButton) {
            refreshButton.addEventListener('click', function() {
                fetchTimelineEvents(true);
            });
        }
        
        if (loadMoreButton) {
            loadMoreButton.addEventListener('click', function() {
                // Get the timestamp of the last event in the timeline
                const timelineEvents = document.getElementById('timeline-events');
                const lastEvent = timelineEvents.lastElementChild;
                const lastEventTime = lastEvent ? lastEvent.getAttribute('data-time') : null;
                
                fetchTimelineEvents(false, lastEventTime);
            });
        }
        
        // Setup auto-refresh every 30 seconds
        setInterval(function() {
            fetchTimelineEvents(true, null, true);
        }, 30000);
    });
    
    function fetchTimelineEvents(refresh = false, before = null, silent = false) {
        // Build the URL with current filters
        const currentUrl = new URL(window.location.href);
        const params = new URLSearchParams(currentUrl.search);
        
        if (before) {
            params.append('before', before);
        }
        
        if (refresh) {
            params.append('refresh', '1');
        }
        
        if (silent) {
            params.append('silent', '1');
        }
        
        // AJAX request for timeline events
        fetch(`${currentUrl.pathname}?${params.toString()}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.html) {
                const timelineEvents = document.getElementById('timeline-events');
                
                if (refresh && !silent) {
                    timelineEvents.innerHTML = data.html;
                } else if (!silent) {
                    // Append new events
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = data.html;
                    while (tempDiv.firstChild) {
                        timelineEvents.appendChild(tempDiv.firstChild);
                    }
                } else if (data.newEvents > 0) {
                    // Just show a notification for silent updates
                    showNotification(`${data.newEvents} new events available. Click refresh to view.`);
                }
                
                // Update the load more button visibility
                const loadMoreButton = document.getElementById('loadMoreEvents');
                if (loadMoreButton) {
                    loadMoreButton.style.display = data.has_more ? 'inline-flex' : 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error fetching timeline events:', error);
        });
    }
    
    function showNotification(message) {
        // Simple notification display
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-indigo-600 text-white px-4 py-2 rounded-md shadow-lg';
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('opacity-0', 'transition-opacity', 'duration-500');
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 500);
        }, 3000);
    }
</script>
@endsection
