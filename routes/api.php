<?php

use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\WorkOrderController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InventoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Public and authenticated API routes for the SmartFix backend.
|
*/

// Public route — no authentication required
Route::post('/login', [AuthController::class, 'login']);

// Protected routes — require a valid Sanctum token
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('v1/ai')->group(function () {
        Route::post('/vision-diagnose', [AiController::class, 'visionDiagnose']);
        Route::post('/text-chat', [AiController::class, 'textChat']);
    });

    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory', [InventoryController::class, 'store']);
    Route::get('/work-orders', [WorkOrderController::class, 'index']);
    Route::post('/work-orders', [WorkOrderController::class, 'store']);
    Route::post('/tickets', [WorkOrderController::class, 'store']);
    Route::post('/work-orders/{id}/complete', [WorkOrderController::class, 'complete']);
    Route::get('/technician/stats', [WorkOrderController::class, 'stats']);
});
