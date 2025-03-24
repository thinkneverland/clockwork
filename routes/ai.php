<?php

use Illuminate\Support\Facades\Route;
use ThinkNeverland\Tapped\Http\Controllers\AiController;

Route::middleware(['api', 'auth:sanctum'])->prefix('tapped/ai')->group(function () {
    Route::get('debug-info', [AiController::class, 'getDebugInfo']);
    Route::get('state', [AiController::class, 'getState']);
    Route::get('logs', [AiController::class, 'getLogs']);
    Route::post('screenshots', [AiController::class, 'storeScreenshot']);
});
