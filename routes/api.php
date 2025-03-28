<?php

use Illuminate\Support\Facades\Route;
use ThinkNeverland\Tapped\Http\Controllers\Api\ErrorReportController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Register API routes for the Tapped package
|
*/

// Error reporting endpoint for JavaScript clients
Route::post('/errors/report', [ErrorReportController::class, 'store'])
    ->name('tapped.errors.report');

// Add other API routes below
