<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PersonnelController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\WorkOrder\WorkOrderController;

Route::prefix('v1')->group(function () {
    // Auth Routes (public)
    Route::post('/auth/login', [AuthController::class, 'login']);
    
    // Protected Routes (Mock Auth or Sanctum)
    Route::middleware(['mockauth'])->group(function () {
        // Auth
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // ─── Work Orders ───────────────────────────────────────
        // All authenticated users can read (Teknisi visibility filtered in service)
        Route::get('/work-orders', [WorkOrderController::class, 'index']);
        Route::get('/work-orders/{id}', [WorkOrderController::class, 'show']);

        // Create: Admin, Manager, Supervisors only
        Route::post('/work-orders', [WorkOrderController::class, 'store'])
            ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');

        // Update: all roles can attempt (policy enforces per-resource rules)
        Route::put('/work-orders/{id}', [WorkOrderController::class, 'update']);

        // Delete: Admin and Manager only
        Route::delete('/work-orders/{id}', [WorkOrderController::class, 'destroy'])
            ->middleware('role:Admin,Manager Teknik');

        // ─── Personnel ─────────────────────────────────────────
        Route::get('/personnel', [PersonnelController::class, 'index']);

        // ─── Notifications ─────────────────────────────────────
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    });
});
