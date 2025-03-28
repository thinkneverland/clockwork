<?php

use Illuminate\Support\Facades\Route;
use ThinkNeverland\Tapped\Http\Controllers\DashboardController;
use ThinkNeverland\Tapped\Http\Controllers\RequestController;
use ThinkNeverland\Tapped\Http\Controllers\LivewireController;
use ThinkNeverland\Tapped\Http\Controllers\DatabaseController;
use ThinkNeverland\Tapped\Http\Controllers\TimelineController;

Route::middleware('web')->group(function () {
    // Only register routes if debugging is enabled
    if (config('tapped.enabled', false)) {
        Route::prefix(config('tapped.path', 'tapped'))->name('tapped.')->group(function () {
            // Dashboard
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
            
            // Requests
            Route::get('/requests', [RequestController::class, 'index'])->name('requests');
            Route::get('/requests/{id}', [RequestController::class, 'show'])->name('requests.show');
            
            // Livewire
            Route::get('/livewire', [LivewireController::class, 'index'])->name('livewire');
            Route::get('/livewire/components', [LivewireController::class, 'components'])->name('livewire.components');
            Route::get('/livewire/components/{id}', [LivewireController::class, 'component'])->name('livewire.component');
            Route::post('/livewire/components/{id}/update', [LivewireController::class, 'updateComponent'])->name('livewire.update-component');
            Route::post('/livewire/components/{id}/method', [LivewireController::class, 'executeMethod'])->name('livewire.execute-method');
            
            // Database
            Route::get('/database', [DatabaseController::class, 'index'])->name('database');
            Route::get('/database/queries', [DatabaseController::class, 'queries'])->name('database.queries');
            Route::get('/database/models', [DatabaseController::class, 'models'])->name('database.models');
            
            // Timeline
            Route::get('/timeline', [TimelineController::class, 'index'])->name('timeline');
            Route::get('/timeline/events', [TimelineController::class, 'events'])->name('timeline.events');
        });
    }
});
