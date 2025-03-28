@extends('tapped::layouts.app')

@section('content')
<div class="space-y-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Livewire Components</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Inspect and debug Livewire components</p>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-indigo-100 dark:bg-indigo-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">Total Components</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['total_components'] }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-indigo-100 dark:bg-indigo-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">Unique Components</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['unique_components'] }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-indigo-100 dark:bg-indigo-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">Events</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['event_count'] }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-indigo-100 dark:bg-indigo-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-300">Property Updates</h2>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['property_updates'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Component List -->
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Active Components</h2>
            <div>
                <div class="relative">
                    <input type="text" id="componentSearch" placeholder="Search components" class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        <div id="componentList" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Component ID</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Class</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Properties</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Updated</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" id="componentTableBody">
                    @forelse($requests as $request)
                        @if(isset($request['data'][\ThinkNeverland\Tapped\DataCollectors\LivewireCollector::class]['components']))
                            @foreach($request['data'][\ThinkNeverland\Tapped\DataCollectors\LivewireCollector::class]['components'] as $component)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 component-row" data-component-name="{{ $component['class'] ?? 'Unknown' }}">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                        <div class="truncate max-w-xs" title="{{ $component['id'] ?? 'Unknown' }}">{{ $component['id'] ?? 'Unknown' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                        {{ $component['class'] ?? 'Unknown' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ isset($component['data']) ? count((array) $component['data']) : 0 }} properties
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $component['last_updated'] ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('tapped.livewire.component', $component['id']) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                No components recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Component search functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('componentSearch');
        const componentRows = document.querySelectorAll('.component-row');
        
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            
            componentRows.forEach(row => {
                const componentName = row.getAttribute('data-component-name').toLowerCase();
                if (componentName.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Fetch active components via AJAX
        fetchActiveComponents();
    });
    
    function fetchActiveComponents() {
        fetch('{{ route("tapped.livewire.components") }}')
            .then(response => response.json())
            .then(data => {
                updateComponentList(data.components);
            })
            .catch(error => {
                console.error('Error fetching components:', error);
            });
    }
    
    function updateComponentList(components) {
        const tableBody = document.getElementById('componentTableBody');
        
        if (!components || components.length === 0) {
            return;
        }
        
        // Clear existing components if any
        tableBody.innerHTML = '';
        
        components.forEach(component => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50 dark:hover:bg-gray-700 component-row';
            row.setAttribute('data-component-name', component.class || 'Unknown');
            
            // Component ID
            let cell = document.createElement('td');
            cell.className = 'px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200';
            let div = document.createElement('div');
            div.className = 'truncate max-w-xs';
            div.title = component.id || 'Unknown';
            div.textContent = component.id || 'Unknown';
            cell.appendChild(div);
            row.appendChild(cell);
            
            // Class
            cell = document.createElement('td');
            cell.className = 'px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200';
            cell.textContent = component.class || 'Unknown';
            row.appendChild(cell);
            
            // Properties
            cell = document.createElement('td');
            cell.className = 'px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400';
            const propertyCount = component.data ? Object.keys(component.data).length : 0;
            cell.textContent = propertyCount + ' properties';
            row.appendChild(cell);
            
            // Last Updated
            cell = document.createElement('td');
            cell.className = 'px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400';
            cell.textContent = component.last_updated || 'N/A';
            row.appendChild(cell);
            
            // Actions
            cell = document.createElement('td');
            cell.className = 'px-6 py-4 whitespace-nowrap text-sm font-medium';
            const link = document.createElement('a');
            link.href = '/tapped/livewire/components/' + component.id;
            link.className = 'text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300';
            link.textContent = 'View';
            cell.appendChild(link);
            row.appendChild(cell);
            
            tableBody.appendChild(row);
        });
    }
</script>
@endsection
