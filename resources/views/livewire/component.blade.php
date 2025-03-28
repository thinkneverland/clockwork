@extends('tapped::layouts.app')

@section('content')
<div class="space-y-6">
    <div class="mb-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Component: {{ $component['name'] ?? 'Unknown' }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $component['class'] ?? 'Unknown Class' }}</p>
            </div>
            <div>
                <a href="{{ route('tapped.livewire') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to Components
                </a>
            </div>
        </div>
    </div>

    <!-- Component Info Tabs -->
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex" aria-label="Tabs">
                <button id="tab-properties" class="tab-button active w-1/4 py-4 px-1 text-center border-b-2 border-indigo-500 font-medium text-sm text-indigo-600 dark:text-indigo-400">
                    Properties
                </button>
                <button id="tab-history" class="tab-button w-1/4 py-4 px-1 text-center border-b-2 border-transparent font-medium text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300">
                    State History
                </button>
                <button id="tab-methods" class="tab-button w-1/4 py-4 px-1 text-center border-b-2 border-transparent font-medium text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300">
                    Methods
                </button>
                <button id="tab-branches" class="tab-button w-1/4 py-4 px-1 text-center border-b-2 border-transparent font-medium text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300">
                    Branches
                </button>
            </nav>
        </div>

        <!-- Tab Contents -->
        <div class="p-4">
            <!-- Properties Tab -->
            <div id="content-properties" class="tab-content">
                <div class="mb-4 flex justify-between items-center">
                    <div class="relative w-64">
                        <input type="text" id="propertySearch" placeholder="Search properties" class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <button id="refreshProperties" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            Refresh
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700" id="propertiesTable">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Property</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Value</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" id="propertiesTableBody">
                            <!-- Properties will be loaded here via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- State History Tab (will be populated via JavaScript) -->
            <div id="content-history" class="tab-content hidden">
                <div class="mb-4 flex justify-between items-center">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Time travel through component state changes</span>
                    </div>
                    <div class="flex space-x-2">
                        <button id="timelinePlayPause" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Play
                        </button>
                        <button id="createSnapshot" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            Snapshot
                        </button>
                    </div>
                </div>
                
                <!-- Timeline Scrubber -->
                <div class="mb-6">
                    <div id="timelineScrubber" class="w-full h-6 bg-gray-200 dark:bg-gray-700 rounded-full relative">
                        <div id="timelineProgress" class="h-full bg-indigo-500 rounded-full" style="width: 0%"></div>
                        <div id="timelineHandle" class="absolute top-0 w-6 h-6 mt-[-3px] bg-white dark:bg-gray-200 rounded-full shadow-md border-2 border-indigo-500 cursor-pointer" style="left: 0%"></div>
                    </div>
                    <div class="flex justify-between mt-1 text-xs text-gray-500 dark:text-gray-400">
                        <span id="timelineStart">Start</span>
                        <span id="timelineCurrent">Current</span>
                        <span id="timelineEnd">Latest</span>
                    </div>
                </div>
                
                <!-- State Changes List -->
                <div class="overflow-y-auto max-h-96">
                    <div id="stateChangesList" class="space-y-2">
                        <!-- State changes will be loaded here via JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Methods Tab (will be populated via JavaScript) -->
            <div id="content-methods" class="tab-content hidden">
                <div class="mb-4">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Execute component methods</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700" id="methodsTable">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Method</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Parameters</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" id="methodsTableBody">
                            <!-- Methods will be loaded here via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Branches Tab (will be populated via JavaScript) -->
            <div id="content-branches" class="tab-content hidden">
                <div class="mb-4 flex justify-between items-center">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Explore alternative state branches</span>
                    </div>
                    <div>
                        <button id="createBranch" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            New Branch
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="branchesList">
                    <!-- Branches will be loaded here via JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>
