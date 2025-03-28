<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Tapped - Laravel Livewire Debugger</title>
    <link rel="stylesheet" href="{{ asset('vendor/tapped/css/app.css') }}">
    <script src="{{ asset('vendor/tapped/js/app.js') }}" defer></script>
    <script>
        // Theme handling
        function setTheme(theme) {
            if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
                localStorage.theme = 'dark';
            } else {
                document.documentElement.classList.remove('dark');
                localStorage.theme = 'light';
            }
        }

        // Initialize theme
        if (localStorage.theme === 'dark' || (!localStorage.theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            setTheme('dark');
        } else {
            setTheme('light');
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen">
    <div id="app" class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 bg-white dark:bg-gray-800 shadow-md flex-shrink-0 hidden md:block">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h1 class="text-xl font-bold text-indigo-600 dark:text-indigo-400">Tapped</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Livewire Debugger</p>
            </div>
            <nav class="p-2">
                <ul>
                    <li>
                        <a href="{{ route('tapped.dashboard') }}" class="flex items-center p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 @if(request()->routeIs('tapped.dashboard')) bg-indigo-50 text-indigo-600 dark:bg-gray-700 dark:text-indigo-400 @endif">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('tapped.requests') }}" class="flex items-center p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 @if(request()->routeIs('tapped.requests')) bg-indigo-50 text-indigo-600 dark:bg-gray-700 dark:text-indigo-400 @endif">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                            Requests
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('tapped.livewire') }}" class="flex items-center p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 @if(request()->routeIs('tapped.livewire')) bg-indigo-50 text-indigo-600 dark:bg-gray-700 dark:text-indigo-400 @endif">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            Livewire
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('tapped.database') }}" class="flex items-center p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 @if(request()->routeIs('tapped.database')) bg-indigo-50 text-indigo-600 dark:bg-gray-700 dark:text-indigo-400 @endif">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path></svg>
                            Database
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('tapped.timeline') }}" class="flex items-center p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 @if(request()->routeIs('tapped.timeline')) bg-indigo-50 text-indigo-600 dark:bg-gray-700 dark:text-indigo-400 @endif">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg>
                            Timeline
                        </a>
                    </li>
                </ul>
            </nav>
            <div class="absolute bottom-0 w-64 p-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Theme</span>
                    <div class="flex items-center space-x-2">
                        <button onclick="setTheme('light')" class="p-1 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700" title="Light Mode">
                            <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path></svg>
                        </button>
                        <button onclick="setTheme('dark')" class="p-1 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700" title="Dark Mode">
                            <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                        </button>
                        <button onclick="setTheme('system')" class="p-1 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700" title="System Theme">
                            <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd"></path></svg>
                        </button>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main content area -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm z-10">
                <div class="px-4 py-3 flex justify-between items-center">
                    <button class="md:hidden p-2 text-gray-500 dark:text-gray-400 focus:outline-none" id="sidebarToggle">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <div class="flex items-center">
                        <span class="text-sm text-gray-500 dark:text-gray-400 mr-4">{{ config('app.env') }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ now()->format('Y-m-d H:i:s') }}</span>
                    </div>
                </div>
            </header>

            <!-- Main content -->
            <main class="flex-1 overflow-y-auto p-4 bg-gray-50 dark:bg-gray-900">
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        // Mobile sidebar toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.querySelector('aside');
            sidebar.classList.toggle('hidden');
        });

        // Handle keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Allow Ctrl+K for search
            if (e.ctrlKey && e.key === 'k') {
                e.preventDefault();
                // Implement search functionality
            }
            
            // Allow / for search
            if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault();
                // Implement search functionality
            }
            
            // Implement other shortcuts
        });
    </script>
</body>
</html>
