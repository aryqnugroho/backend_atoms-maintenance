<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PersonnelController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\WorkOrder\WorkOrderController;
use App\Http\Controllers\Api\V1\Cnsd\CnsdReadinessController;

Route::prefix('v1')->group(function () {
    // ─── Public Auth Routes ────────────────────────────────────────────────
    // login: mock dev only (production login is at atoms-rostering)
    Route::post('/auth/login', [AuthController::class, 'login']);
    // verify: called by frontend on load to validate a rostering Sanctum token
    Route::get('/auth/verify', [AuthController::class, 'verify']);
    
    // Protected Routes (Mock Auth or Sanctum)
    Route::middleware(['mockauth'])->group(function () {
        // Auth
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // ─── Work Orders ───────────────────────────────────────
        // All authenticated users can read (Teknisi visibility filtered in service)
        Route::get('/work-orders', [WorkOrderController::class, 'index']);
        Route::get('/work-orders/years', [WorkOrderController::class, 'years']);
        Route::get('/work-orders/{id}', [WorkOrderController::class, 'show']);

        // Create: Admin, Manager, Supervisors only
        Route::post('/work-orders', [WorkOrderController::class, 'store'])
            ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');

        // Update: all roles can attempt (policy enforces per-resource rules)
        Route::put('/work-orders/{id}', [WorkOrderController::class, 'update']);
        Route::post('/work-orders/{id}/sign', [WorkOrderController::class, 'sign']);
        Route::get('/work-orders/{id}/print', [WorkOrderController::class, 'print']);

        // Delete: Admin and Manager only
        Route::delete('/work-orders/{id}', [WorkOrderController::class, 'destroy'])
            ->middleware('role:Admin,Manager Teknik');

        // ─── CNSD Equipment Readiness (Form EQ-1) ──────────────
        // Pilot module — only "Kesiapan Peralatan CNSD" is wired up. Other
        // CNSD equipment cards (Radar, Recorder, AMSC, …) remain Coming Soon
        // and have no backend.
        Route::prefix('cnsd/readiness')->group(function () {
            // Template + year filter must be declared BEFORE the {id} route so
            // /template and /years aren't captured by the int parameter.
            Route::get('/template', [CnsdReadinessController::class, 'template']);
            Route::get('/years',    [CnsdReadinessController::class, 'years']);

            Route::get('/',         [CnsdReadinessController::class, 'index']);
            Route::post('/', [CnsdReadinessController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Teknisi CNSD');

            Route::get('/{id}',     [CnsdReadinessController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [CnsdReadinessController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdReadinessController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [CnsdReadinessController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik');
        });

        // ─── Personnel ─────────────────────────────────────────
        Route::get('/personnel', [PersonnelController::class, 'index']);
        // Real shift context from atoms-rostering (read-only DB query)
        Route::get('/personnel/shift-today', [PersonnelController::class, 'shiftToday']);

        // ─── Notifications ─────────────────────────────────────
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    });
});
