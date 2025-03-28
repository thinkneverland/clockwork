<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Livewire N+1 Test</title>
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.8.2/dist/alpine.min.js" defer></script>
    <script src="{{ asset('js/livewire.js') }}" defer></script>
    <style>
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            line-height: 1.5;
            padding: 2rem;
        }
        .container {
            max-width: 48rem;
            margin: 0 auto;
            padding: 2rem;
            background: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .user-card {
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .post {
            background: #f9fafb;
            padding: 0.75rem;
            margin: 0.5rem 0;
            border-radius: 0.25rem;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Livewire Posts Component</h1>
        
        <div class="livewire-component" 
             wire:id="posts"
             x-data="{ 
                users: [
                    { id: 1, name: 'User 1', posts: [{ title: 'Post 1-1' }, { title: 'Post 1-2' }] },
                    { id: 2, name: 'User 2', posts: [{ title: 'Post 2-1' }] },
                    { id: 3, name: 'User 3', posts: [{ title: 'Post 3-1' }, { title: 'Post 3-2' }, { title: 'Post 3-3' }] }
                ],
                showN1Warning: false
             }">
            
            <button x-on:click="showN1Warning = !showN1Warning">
                Toggle N+1 Warning
            </button>
            
            <div x-show="showN1Warning" class="user-card" style="background: #fee2e2; border-color: #ef4444; margin-top: 1rem; padding: 1rem;">
                <strong>N+1 Query Detected:</strong> User posts are being loaded inefficiently. Consider using eager loading.
            </div>
            
            <template x-for="user in users" :key="user.id">
                <div class="user-card">
                    <h3 x-text="user.name"></h3>
                    <p>Posts:</p>
                    <template x-for="post in user.posts" :key="post.title">
                        <div class="post">
                            <p x-text="post.title"></p>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <script>
        // Simulate Livewire for testing purposes
        document.addEventListener('DOMContentLoaded', function() {
            window.Livewire = {
                components: {
                    posts: {
                        id: 'posts',
                        name: 'posts',
                        data: {
                            users: [
                                { id: 1, name: 'User 1', posts: [{ title: 'Post 1-1' }, { title: 'Post 1-2' }] },
                                { id: 2, name: 'User 2', posts: [{ title: 'Post 2-1' }] },
                                { id: 3, name: 'User 3', posts: [{ title: 'Post 3-1' }, { title: 'Post 3-2' }, { title: 'Post 3-3' }] }
                            ]
                        },
                        events: []
                    }
                }
            };
            
            // Simulate queries for testing
            console.log('SELECT * FROM users');
            setTimeout(() => {
                console.log('SELECT * FROM posts WHERE user_id = 1');
                console.log('SELECT * FROM posts WHERE user_id = 2');
                console.log('SELECT * FROM posts WHERE user_id = 3');
            }, 100);
        });
    </script>
</body>
</html>
