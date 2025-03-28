# Tapped Test Application

This is a minimal Laravel Livewire application used for running end-to-end tests against the Tapped browser extension.

## Features

This test application includes various Livewire components specifically designed to test all features of the Tapped browser extension:

1. **Counter Component**: A simple component with numeric state that can be incremented/decremented
2. **Todo List Component**: A component with array state management for testing collection editing
3. **Nested Components**: Parent/child components for testing component hierarchy detection
4. **Event Emitter**: Components that emit events to test event monitoring
5. **Database Queries**: Components that perform various database queries, including some that trigger N+1 query patterns
6. **Long-Running Actions**: Components with actions that take time to complete for testing progress indicators

## Setup Instructions

1. Clone the repository
2. Run `composer install`
3. Copy `.env.example` to `.env` and configure your database
4. Run `php artisan migrate:fresh --seed` to create the database tables and seed with test data
5. Run `php artisan serve` to start the development server on http://localhost:8000

## Testing with Tapped

This application works with the Tapped browser extension end-to-end tests. To run the tests:

1. Start this application with `php artisan serve`
2. Build the Tapped extension with `pnpm build` (from the extension directory)
3. Run the end-to-end tests with `npm run test:e2e`

## Component Structure

The test application includes the following Livewire components:

- `App\Http\Livewire\Counter`: Basic counter with increment/decrement
- `App\Http\Livewire\TodoList`: Todo list with add/remove/toggle functionality
- `App\Http\Livewire\UserProfile`: Component with nested children and various property types
- `App\Http\Livewire\QueryDemo`: Component that performs various database queries
- `App\Http\Livewire\EventDemo`: Component that emits various events
