<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Livewire Test</title>
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.8.2/dist/alpine.min.js" defer></script>
    <script src="{{ asset('js/livewire.js') }}" defer></script>
    <style>
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            line-height: 1.5;
            padding: 2rem;
        }
        .container {
            max-width: 32rem;
            margin: 0 auto;
            padding: 2rem;
            background: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        button {
            background: #4f46e5;
            color: white;
            font-weight: bold;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background: #4338ca;
        }
        .counter {
            font-size: 2rem;
            font-weight: bold;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Livewire Counter Component</h1>
        
        <div class="livewire-component" 
             wire:id="counter"
             x-data="{ count: 0 }"
             x-init="window.livewireTest = { getComponentData: () => { return { id: 'counter', count: count, name: 'counter' } } }">
            
            <p>Current Count: <span x-text="count" class="counter">0</span></p>
            
            <button x-on:click="count++" dusk="increment-button">Increment</button>
            
            <button x-on:click="window.dispatchEvent(new CustomEvent('livewire:emitEvent', { detail: { name: 'counter-changed', params: [count] } }))" 
                    dusk="emit-event-button">
                Emit Event
            </button>
        </div>
    </div>

    <script>
        // Simulate Livewire for testing purposes
        document.addEventListener('DOMContentLoaded', function() {
            window.Livewire = {
                components: {
                    counter: {
                        id: 'counter',
                        name: 'counter',
                        data: { count: 0 },
                        events: []
                    }
                },
                emit: function(event, ...params) {
                    console.log('Livewire emitted event:', event, params);
                    window.dispatchEvent(new CustomEvent('livewire:emitEvent', { 
                        detail: { name: event, params: params } 
                    }));
                }
            };
        });
    </script>
</body>
</html>
