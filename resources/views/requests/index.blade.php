@extends('tapped::layouts.app')

@section('content')
<div class="space-y-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">HTTP Requests</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Monitor and analyze HTTP requests</p>
    </div>

    <!-- Filter Controls -->
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
        <form action="{{ route('tapped.requests') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                <input type="text" name="filter" id="filter" value="{{ $filter ?? '' }}" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
            <div>
                <label for="method" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Method</label>
                <select name="method" id="method" class="mt-1 block w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm text-gray-900 dark:text-gray-100">
                    <option value="">All Methods</option>
                    <option value="GET" {{ ($method ?? '') === 'GET' ? 'selected' : '' }}>GET</option>
                    <option value="POST" {{ ($method ?? '') === 'POST' ? 'selected' : '' }}>POST</option>
                    <option value="PUT" {{ ($method ?? '') === 'PUT' ? 'selected' : '' }}>PUT</option>
                    <option value="PATCH" {{ ($method ?? '') === 'PATCH' ? 'selected' : '' }}>PATCH</option>
                    <option value="DELETE" {{ ($method ?? '') === 'DELETE' ? 'selected' : '' }}>DELETE</option>
                </select>
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <select name="status" id="status" class="mt-1 block w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm text-gray-900 dark:text-gray-100">
                    <option value="">All Status Codes</option>
                    <option value="200" {{ ($status ?? '') === '200' ? 'selected' : '' }}>200 OK</option>
                    <option value="201" {{ ($status ?? '') === '201' ? 'selected' : '' }}>201 Created</option>
                    <option value="301" {{ ($status ?? '') === '301' ? 'selected' : '' }}>301 Moved</option>
                    <option value="302" {{ ($status ?? '') === '302' ? 'selected' : '' }}>302 Found</option>
                    <option value="400" {{ ($status ?? '') === '400' ? 'selected' : '' }}>400 Bad Request</option>
                    <option value="401" {{ ($status ?? '') === '401' ? 'selected' : '' }}>401 Unauthorized</option>
                    <option value="403" {{ ($status ?? '') === '403' ? 'selected' : '' }}>403 Forbidden</option>
                    <option value="404" {{ ($status ?? '') === '404' ? 'selected' : '' }}>404 Not Found</option>
                    <option value="500" {{ ($status ?? '') === '500' ? 'selected' : '' }}>500 Server Error</option>
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

    <!-- Request Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-green-100 dark:bg-green-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">2xx Responses</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $statusCodeStats['2xx'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 9l3 3m0 0l-3 3m3-3H8m13 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">3xx Responses</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $statusCodeStats['3xx'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-yellow-100 dark:bg-yellow-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">4xx Responses</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $statusCodeStats['4xx'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-red-100 dark:bg-red-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">5xx Responses</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $statusCodeStats['5xx'] ?? 0 }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Requests</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Method</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">URL</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Duration</th>
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
                                } elseif ($statusCode >= 300 && $statusCode < 400) {
                                    $statusClass = 'text-blue-600 dark:text-blue-400';
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $requestData['time'] ?? 'N/A' }}
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
                            <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                No requests found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if(isset($pagination) && $pagination['total'] > 1)
        <div class="bg-white dark:bg-gray-800 px-4 py-3 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 sm:px-6">
            <div class="flex-1 flex justify-between sm:hidden">
                @if($pagination['current'] > 1)
                <a href="{{ route('tapped.requests', ['page' => $pagination['current'] - 1, 'filter' => $filter ?? '', 'method' => $method ?? '', 'status' => $status ?? '']) }}" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                    Previous
                </a>
                @endif
                @if($pagination['current'] < $pagination['total'])
                <a href="{{ route('tapped.requests', ['page' => $pagination['current'] + 1, 'filter' => $filter ?? '', 'method' => $method ?? '', 'status' => $status ?? '']) }}" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                    Next
                </a>
                @endif
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700 dark:text-gray-200">
                        Showing
                        <span class="font-medium">{{ ($pagination['current'] - 1) * 20 + 1 }}</span>
                        to
                        <span class="font-medium">{{ min($pagination['current'] * 20, $pagination['count']) }}</span>
                        of
                        <span class="font-medium">{{ $pagination['count'] }}</span>
                        results
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        @if($pagination['current'] > 1)
                        <a href="{{ route('tapped.requests', ['page' => $pagination['current'] - 1, 'filter' => $filter ?? '', 'method' => $method ?? '', 'status' => $status ?? '']) }}" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <span class="sr-only">Previous</span>
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                        @endif
                        
                        @for($i = 1; $i <= $pagination['total']; $i++)
                            <a href="{{ route('tapped.requests', ['page' => $i, 'filter' => $filter ?? '', 'method' => $method ?? '', 'status' => $status ?? '']) }}" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium {{ $i === $pagination['current'] ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                                {{ $i }}
                            </a>
                        @endfor
                        
                        @if($pagination['current'] < $pagination['total'])
                        <a href="{{ route('tapped.requests', ['page' => $pagination['current'] + 1, 'filter' => $filter ?? '', 'method' => $method ?? '', 'status' => $status ?? '']) }}" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <span class="sr-only">Next</span>
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                        @endif
                    </nav>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
