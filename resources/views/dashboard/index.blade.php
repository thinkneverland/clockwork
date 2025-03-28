@extends('tapped::layouts.app')

@section('content')
<div class="space-y-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Tapped debugging toolkit overview</p>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-indigo-100 dark:bg-indigo-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">Total Requests</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['total_requests'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $stats['request_count_today'] }} today</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-indigo-100 dark:bg-indigo-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">Livewire Components</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['livewire_components'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Tracked across requests</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-indigo-100 dark:bg-indigo-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">Database Queries</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['database_queries'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Avg response time: {{ $stats['average_response_time'] }}ms</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Requests -->
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Recent Requests</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Method</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">URL</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Time</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($requests as $request)
                        @if(isset($request['data'][\ThinkNeverland\Tapped\DataCollectors\RequestCollector::class]))
                            @php
                                $requestData = $request['data'][\ThinkNeverland\Tapped\DataCollectors\RequestCollector::class];
                                $statusCode = $requestData['response_status'] ?? 0;
                                $statusClass = 'text-green-600 dark:text-green-400';
                                
                                if ($statusCode >= 400 && $statusCode < 500) {
                                    $statusClass = 'text-yellow-600 dark:text-yellow-400';
                                } elseif ($statusCode >= 500) {
                                    $statusClass = 'text-red-600 dark:text-red-400';
                                }
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200">
                                        {{ $requestData['method'] ?? 'UNKNOWN' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200 max-w-xs truncate">
                                    {{ $requestData['uri'] ?? 'Unknown URL' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm {{ $statusClass }}">
                                    {{ $requestData['response_status'] ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ isset($requestData['response_time']) ? $requestData['response_time'] . 'ms' : 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('tapped.requests.show', $request['id']) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                No requests recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('tapped.requests') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">
                View all requests
            </a>
        </div>
    </div>
    
    <!-- Error Overview -->
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Error Overview</h2>
        </div>
        <div class="p-6">
            @if($stats['error_count'] > 0)
                <div class="flex items-center">
                    <div class="bg-red-100 dark:bg-red-900 p-3 rounded-full">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $stats['error_count'] }} Error{{ $stats['error_count'] > 1 ? 's' : '' }} Detected</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Check the requests page for more details.</p>
                    </div>
                </div>
            @else
                <div class="flex items-center">
                    <div class="bg-green-100 dark:bg-green-900 p-3 rounded-full">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">No Errors Detected</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">All systems operating normally.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
