@extends('tapped::layouts.app')

@section('content')
<div class="space-y-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Database Queries</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Monitor and analyze database queries and model operations</p>
    </div>

    <!-- Filter Controls -->
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
        <form action="{{ route('tapped.database') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search Query</label>
                <input type="text" name="filter" id="filter" value="{{ $filter ?? '' }}" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
            <div>
                <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Query Type</label>
                <select name="type" id="type" class="mt-1 block w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm text-gray-900 dark:text-gray-100">
                    <option value="">All Types</option>
                    <option value="SELECT" {{ ($type ?? '') === 'SELECT' ? 'selected' : '' }}>SELECT</option>
                    <option value="INSERT" {{ ($type ?? '') === 'INSERT' ? 'selected' : '' }}>INSERT</option>
                    <option value="UPDATE" {{ ($type ?? '') === 'UPDATE' ? 'selected' : '' }}>UPDATE</option>
                    <option value="DELETE" {{ ($type ?? '') === 'DELETE' ? 'selected' : '' }}>DELETE</option>
                </select>
            </div>
            <div>
                <label for="model" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Model</label>
                <input type="text" name="model" id="model" value="{{ $model ?? '' }}" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
            <div class="flex items-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Database Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-indigo-100 dark:bg-indigo-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">Total Queries</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['total_queries'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-indigo-100 dark:bg-indigo-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">Average Duration</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['avg_duration'] ?? 0 }}ms</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-yellow-100 dark:bg-yellow-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">N+1 Issues</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['n_plus_one_count'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-indigo-100 dark:bg-indigo-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">Models</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['unique_models'] ?? 0 }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- N+1 Query Issues Alert -->
    @if(($stats['n_plus_one_count'] ?? 0) > 0)
    <div class="bg-yellow-50 dark:bg-yellow-900 border-l-4 border-yellow-400 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-100">N+1 Query Issues Detected</h3>
                <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-200">
                    <p>{{ $stats['n_plus_one_count'] ?? 0 }} potential N+1
                    {{ ($stats['n_plus_one_count'] ?? 0) == 1 ? 'issue' : 'issues' }} detected. These inefficient database access patterns can cause performance problems. Click on highlighted queries below to see details.</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Queries Table -->
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Database Queries</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">SQL</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Duration</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Connection</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Model</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Request</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($queries as $query)
                        @php
                            $isNPlusOne = $query['is_n_plus_one'] ?? false;
                            $rowClass = $isNPlusOne ? 'bg-yellow-50 dark:bg-yellow-900' : '';
                            $queryTime = $query['time'] ?? 0;
                            $timeClass = 'text-green-600 dark:text-green-400';
                            
                            if ($queryTime > 500) {
                                $timeClass = 'text-red-600 dark:text-red-400';
                            } elseif ($queryTime > 100) {
                                $timeClass = 'text-yellow-600 dark:text-yellow-400';
                            }
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 {{ $rowClass }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200">
                                    {{ $query['type'] ?? 'UNKNOWN' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-200">
                                <div class="max-w-md break-words">
                                    {{ $query['query'] ?? 'Unknown SQL' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm {{ $timeClass }}">
                                {{ $queryTime }}ms
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $query['connection'] ?? 'default' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $query['model'] ?? 'None' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                @if(isset($query['request_id']))
                                <a href="{{ route('tapped.requests.show', $query['request_id']) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">
                                    View
                                </a>
                                @else
                                -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                No queries found.
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
                <a href="{{ route('tapped.database', ['page' => $pagination['current'] - 1, 'filter' => $filter ?? '', 'type' => $type ?? '', 'model' => $model ?? '']) }}" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                    Previous
                </a>
                @endif
                @if($pagination['current'] < $pagination['total'])
                <a href="{{ route('tapped.database', ['page' => $pagination['current'] + 1, 'filter' => $filter ?? '', 'type' => $type ?? '', 'model' => $model ?? '']) }}" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
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
                        <a href="{{ route('tapped.database', ['page' => $pagination['current'] - 1, 'filter' => $filter ?? '', 'type' => $type ?? '', 'model' => $model ?? '']) }}" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <span class="sr-only">Previous</span>
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                        @endif
                        
                        @for($i = 1; $i <= $pagination['total']; $i++)
                            <a href="{{ route('tapped.database', ['page' => $i, 'filter' => $filter ?? '', 'type' => $type ?? '', 'model' => $model ?? '']) }}" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium {{ $i === $pagination['current'] ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                                {{ $i }}
                            </a>
                        @endfor
                        
                        @if($pagination['current'] < $pagination['total'])
                        <a href="{{ route('tapped.database', ['page' => $pagination['current'] + 1, 'filter' => $filter ?? '', 'type' => $type ?? '', 'model' => $model ?? '']) }}" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
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

    <!-- N+1 Detection Explanation -->
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-3">About N+1 Query Detection</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
            The N+1 query problem occurs when your code executes one query to retrieve the parent record, and then executes N additional queries (where N is the number of children) to retrieve related data. This can significantly impact performance.
        </p>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Tapped automatically detects potential N+1 query issues by analyzing query patterns. Queries highlighted in yellow above may be part of an N+1 pattern. To fix N+1 issues, consider using eager loading with the <code class="text-xs bg-gray-100 dark:bg-gray-900 px-1 py-0.5 rounded">with()</code> method on your Eloquent models.
        </p>
    </div>
</div>

<script>
    // Add click handlers to expand SQL queries on mobile
    document.addEventListener('DOMContentLoaded', function() {
        const sqlCells = document.querySelectorAll('td:nth-child(2)');
        
        sqlCells.forEach(cell => {
            cell.addEventListener('click', function() {
                this.classList.toggle('max-w-md');
                this.classList.toggle('max-w-none');
            });
        });
    });
</script>
@endsection
