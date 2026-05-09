<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;

Route::prefix('v1')->group(function () {
    // Auth Routes
    Route::post('/auth/login', [AuthController::class, 'login']);
    
    // Protected Routes (Mock Auth or Sanctum)
    Route::middleware(['mockauth'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        
        // Work Orders (Role-protected skeleton)
        Route::middleware(['role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP'])->group(function () {
            Route::get('/work-orders', [\App\Http\Controllers\Api\V1\WorkOrder\WorkOrderController::class, 'index']);
            Route::get('/work-orders/{id}', [\App\Http\Controllers\Api\V1\WorkOrder\WorkOrderController::class, 'show']);
            Route::post('/work-orders', [\App\Http\Controllers\Api\V1\WorkOrder\WorkOrderController::class, 'store']);
            Route::put('/work-orders/{id}', [\App\Http\Controllers\Api\V1\WorkOrder\WorkOrderController::class, 'update']);
            Route::delete('/work-orders/{id}', [\App\Http\Controllers\Api\V1\WorkOrder\WorkOrderController::class, 'destroy']);
        });
    });
});
