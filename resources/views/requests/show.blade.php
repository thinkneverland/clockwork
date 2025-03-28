@extends('tapped::layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Request Details</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $request['uri'] ?? 'Unknown Request' }}</p>
        </div>
        <a href="{{ route('tapped.requests') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Requests
        </a>
    </div>

    <!-- Request Overview -->
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Method</h3>
                <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                    {{ isset($request['method']) && in_array($request['method'], ['POST', 'PUT', 'DELETE', 'PATCH']) ? 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200' : 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' }}">
                        {{ $request['method'] ?? 'UNKNOWN' }}
                    </span>
                </p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</h3>
                <p class="mt-1 text-lg font-semibold 
                    @php
                        $statusCode = $request['response_status'] ?? 0;
                        $statusClass = 'text-green-600 dark:text-green-400';
                        
                        if ($statusCode >= 400 && $statusCode < 500) {
                            $statusClass = 'text-yellow-600 dark:text-yellow-400';
                        } elseif ($statusCode >= 500) {
                            $statusClass = 'text-red-600 dark:text-red-400';
                        } elseif ($statusCode >= 300 && $statusCode < 400) {
                            $statusClass = 'text-blue-600 dark:text-blue-400';
                        }
                    @endphp
                    {{ $statusClass }}">
                    {{ $statusCode ?? 'Unknown' }}
                </p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Response Time</h3>
                <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                    {{ number_format(($request['response_time'] ?? 0) * 1000, 2) }} ms
                </p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Timestamp</h3>
                <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $request['time'] ?? 'Unknown' }}
                </p>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8">
            <button id="tab-overview" class="tab-button whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-indigo-500 text-indigo-600 dark:text-indigo-400" aria-current="page">
                Overview
            </button>
            <button id="tab-headers" class="tab-button whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300">
                Headers
            </button>
            <button id="tab-session" class="tab-button whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300">
                Session
            </button>
            <button id="tab-livewire" class="tab-button whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300">
                Livewire
            </button>
            <button id="tab-queries" class="tab-button whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300">
                Database Queries
            </button>
        </nav>
    </div>

    <!-- Tab Content -->
    <div class="tab-content-container">
        <!-- Overview Tab -->
        <div id="content-overview" class="tab-content">
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Request Details</h3>
                <div class="overflow-hidden bg-gray-50 dark:bg-gray-700 shadow-sm rounded-lg">
                    <div class="px-4 py-4 sm:px-6">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">General Information</h3>
                    </div>
                    <div class="border-t border-gray-200 dark:border-gray-600 px-4 py-5 sm:p-0">
                        <dl class="sm:divide-y sm:divide-gray-200 dark:sm:divide-gray-700">
                            <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">URI</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">{{ $request['uri'] ?? 'N/A' }}</dd>
                            </div>
                            <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Method</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">{{ $request['method'] ?? 'N/A' }}</dd>
                            </div>
                            <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">{{ $request['response_status'] ?? 'N/A' }}</dd>
                            </div>
                            <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">IP Address</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">{{ $request['ip'] ?? 'N/A' }}</dd>
                            </div>
                            <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">User Agent</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">{{ $request['user_agent'] ?? 'N/A' }}</dd>
                            </div>
                            <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Response Time</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">{{ number_format(($request['response_time'] ?? 0) * 1000, 2) }} ms</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Headers Tab -->
        <div id="content-headers" class="tab-content hidden">
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Request Headers</h3>
                @if(isset($request['headers']) && !empty($request['headers']))
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Header</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Value</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($request['headers'] as $header => $value)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ $header }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            @if(is_array($value))
                                                <ul class="list-disc list-inside space-y-1">
                                                    @foreach($value as $val)
                                                        <li>{{ $val }}</li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                {{ $value }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-4 text-gray-500 dark:text-gray-400 text-center">No header information available.</div>
                @endif
            </div>
        </div>

        <!-- Session Tab -->
        <div id="content-session" class="tab-content hidden">
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Session Data</h3>
                @if(isset($request['session']) && !empty($request['session']))
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Key</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Value</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($request['session'] as $key => $value)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ $key }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            @if(is_array($value) || is_object($value))
                                                <pre class="text-xs bg-gray-100 dark:bg-gray-900 p-2 rounded overflow-x-auto">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            @else
                                                {{ $value }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-4 text-gray-500 dark:text-gray-400 text-center">No session data available.</div>
                @endif
            </div>
        </div>

        <!-- Livewire Tab -->
        <div id="content-livewire" class="tab-content hidden">
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Livewire Components</h3>
                @if(isset($livewireComponents) && !empty($livewireComponents))
                    <div class="space-y-4">
                        @foreach($livewireComponents as $component)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-2 flex justify-between items-center">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">{{ $component['name'] ?? 'Unknown Component' }}</h4>
                                    <a href="{{ route('tapped.livewire.component', $component['id'] ?? '') }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 text-sm">
                                        View Details
                                    </a>
                                </div>
                                <div class="p-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">ID</h5>
                                            <p class="text-sm text-gray-900 dark:text-white">{{ $component['id'] ?? 'N/A' }}</p>
                                        </div>
                                        <div>
                                            <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Class</h5>
                                            <p class="text-sm text-gray-900 dark:text-white">{{ $component['class'] ?? 'N/A' }}</p>
                                        </div>
                                    </div>
                                    
                                    @if(isset($component['events']) && !empty($component['events']))
                                        <div class="mt-4">
                                            <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Events</h5>
                                            <div class="space-y-2">
                                                @foreach($component['events'] as $event)
                                                    <div class="bg-gray-100 dark:bg-gray-800 p-2 rounded-md text-sm">
                                                        <span class="font-medium">{{ $event['name'] ?? 'Unknown Event' }}</span>
                                                        @if(isset($event['payload']))
                                                            <pre class="text-xs mt-1 bg-gray-200 dark:bg-gray-900 p-1 rounded">{{ json_encode($event['payload'], JSON_PRETTY_PRINT) }}</pre>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-4 text-gray-500 dark:text-gray-400 text-center">No Livewire components detected in this request.</div>
                @endif
            </div>
        </div>

        <!-- Database Queries Tab -->
        <div id="content-queries" class="tab-content hidden">
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Database Queries</h3>
                @if(isset($queries) && !empty($queries))
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Query</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Time</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Connection</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($queries as $query)
                                    @php
                                        $queryTime = $query['time'] ?? 0;
                                        $timeClass = 'text-green-600 dark:text-green-400';
                                        
                                        if ($queryTime > 500) {
                                            $timeClass = 'text-red-600 dark:text-red-400';
                                        } elseif ($queryTime > 100) {
                                            $timeClass = 'text-yellow-600 dark:text-yellow-400';
                                        }
                                        
                                        $isNPlusOne = $query['is_n_plus_one'] ?? false;
                                        $rowClass = $isNPlusOne ? 'bg-yellow-50 dark:bg-yellow-900' : '';
                                    @endphp
                                    <tr class="{{ $rowClass }}">
                                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                            <div class="max-w-lg break-words">
                                                {{ $query['query'] ?? 'Unknown SQL' }}
                                                @if(!empty($query['bindings']))
                                                    <div class="mt-2">
                                                        <details class="text-xs">
                                                            <summary class="font-medium text-indigo-600 dark:text-indigo-400 cursor-pointer">View Bindings</summary>
                                                            <pre class="mt-1 bg-gray-100 dark:bg-gray-900 p-2 rounded overflow-x-auto">{{ json_encode($query['bindings'], JSON_PRETTY_PRINT) }}</pre>
                                                        </details>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm {{ $timeClass }}">
                                            {{ $queryTime }}ms
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $query['connection'] ?? 'default' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-4 text-gray-500 dark:text-gray-400 text-center">No database queries detected in this request.</div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching functionality
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Get the tab ID from the button ID
                const tabId = button.id.replace('tab-', '');
                
                // Hide all tab contents
                tabContents.forEach(content => {
                    content.classList.add('hidden');
                });
                
                // Show the selected tab content
                document.getElementById(`content-${tabId}`).classList.remove('hidden');
                
                // Update button styles
                tabButtons.forEach(btn => {
                    btn.classList.remove('border-indigo-500', 'text-indigo-600', 'dark:text-indigo-400');
                    btn.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400', 'hover:text-gray-700', 'dark:hover:text-gray-300', 'hover:border-gray-300');
                });
                
                // Style the active button
                button.classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400', 'hover:text-gray-700', 'dark:hover:text-gray-300', 'hover:border-gray-300');
                button.classList.add('border-indigo-500', 'text-indigo-600', 'dark:text-indigo-400');
            });
        });
        
        // Expand SQL queries on click
        const sqlCells = document.querySelectorAll('td:first-child');
        sqlCells.forEach(cell => {
            cell.addEventListener('click', function() {
                const maxWidthDiv = this.querySelector('div');
                if (maxWidthDiv) {
                    maxWidthDiv.classList.toggle('max-w-lg');
                    maxWidthDiv.classList.toggle('max-w-none');
                }
            });
        });
    });
</script>
@endsection
