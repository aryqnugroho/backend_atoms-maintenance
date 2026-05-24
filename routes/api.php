<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PersonnelController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\WorkOrder\WorkOrderController;
use App\Http\Controllers\Api\V1\Cnsd\CnsdReadinessController;
use App\Http\Controllers\Api\V1\Cnsd\CnsdRadarMeterController;
use App\Http\Controllers\Api\V1\Cnsd\CnsdRecorderMeterController;
use App\Http\Controllers\Api\V1\Cnsd\CnsdAmscMeterController;
use App\Http\Controllers\Api\V1\Cnsd\CnsdTransmitterMeterController;
use App\Http\Controllers\Api\V1\Cnsd\CnsdReceiverMeterController;
use App\Http\Controllers\Api\V1\Cnsd\CnsdGlidepathMeterController;
use App\Http\Controllers\Api\V1\Cnsd\CnsdLocalizerMeterController;
use App\Http\Controllers\Api\V1\Cnsd\CnsdTdmeMeterController;
use App\Http\Controllers\Api\V1\Cnsd\CnsdDvorMeterController;
use App\Http\Controllers\Api\V1\Cnsd\CnsdDmeMeterController;
use App\Http\Controllers\Api\V1\Cnsd\CnsdAtisMeterController;
use App\Http\Controllers\Api\V1\Cnsd\CnsdAtcSystemMeterController;
use App\Http\Controllers\Api\V1\Cnsd\CnsdVccsMeterController;
use App\Http\Controllers\Api\V1\Cnsd\CnsdVccsFreqMeterController;
use App\Http\Controllers\Api\V1\Cnsd\CnsdAsmgcsMeterController;
use App\Http\Controllers\Api\V1\Tfp\TfpAobGroundController;
use App\Http\Controllers\Api\V1\Tfp\TfpAobLt12Controller;
use App\Http\Controllers\Api\V1\Tfp\TfpTransmitterTxController;
use App\Http\Controllers\Api\V1\Tfp\TfpTowerController;
use App\Http\Controllers\Api\V1\Tfp\TfpRadarController;
use App\Http\Controllers\Api\V1\Tfp\TfpDvorController;
use App\Http\Controllers\Api\V1\Tfp\TfpLocalizerController;
use App\Http\Controllers\Api\V1\Tfp\TfpGlidepathController;
use App\Http\Controllers\Api\V1\Grounding\GroundingReportController;
use App\Http\Controllers\Api\V1\GroundCheck\GroundCheckAdcController;
use App\Http\Controllers\Api\V1\GroundCheck\GroundCheckVhfController;
use App\Http\Controllers\Api\V1\GroundCheck\GroundCheckLlzController;
use App\Http\Controllers\Api\V1\GroundCheck\GroundCheckGpController;
use App\Http\Controllers\Api\V1\GroundCheck\GroundCheckDvorController;
use App\Http\Controllers\Api\V1\Reporting\ReportingDamageReportController;
use App\Http\Controllers\Api\V1\Reporting\ReportingPersonController;
use App\Http\Controllers\Api\V1\Logbook\LogbookTfpController;
use App\Http\Controllers\Api\V1\Logbook\LogbookCnsdController;

Route::prefix('v1')->group(function () {
    // ─── Public Auth Routes ────────────────────────────────────────────────
    // login: mock dev only (production login is at atoms-rostering)
    Route::post('/auth/login', [AuthController::class, 'login']);
    // verify: called by frontend on load to validate a rostering Sanctum token
    Route::get('/auth/verify', [AuthController::class, 'verify']);

    // ─── Public Monitor (kiosk TV) ─────────────────────────────────────────
    // These routes serve the workshop TV at /monitor and intentionally have
    // NO auth middleware. The password modal in the kiosk is a UI gate, and
    // the snapshot only exposes data already visible to authenticated users
    // (no PII beyond names, no signatures, no tokens).
    Route::prefix('public/monitor')->group(function () {
        Route::post('/verify',   [\App\Http\Controllers\Api\V1\MonitorController::class, 'verify']);
        Route::get('/snapshot',  [\App\Http\Controllers\Api\V1\MonitorController::class, 'snapshot']);
    });
    
    // Protected Routes (Mock Auth or Sanctum)
    Route::middleware(['mockauth'])->group(function () {
        // Auth
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // ─── Dashboard helpers ─────────────────────────────────
        // Read-only roll-ups powering the dashboard widgets (welcome modal,
        // Pengingat Pengecekan Harian card, Ringkasan Logbook card).
        Route::get('/dashboard/shift-checklist',  [\App\Http\Controllers\Api\V1\DashboardController::class, 'shiftChecklist']);
        Route::get('/dashboard/logbook-summary', [\App\Http\Controllers\Api\V1\DashboardController::class, 'logbookSummary']);

        // Dashboard checklist settings — editable catalog backing the
        // Pengingat card. Read open (so the dashboard can render); mutations
        // gated to MT / Supervisor / Admin.
        Route::prefix('dashboard/checklist')->group(function () {
            $checklistEditRoles = 'role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP';

            Route::get('/modules', [\App\Http\Controllers\Api\V1\Dashboard\DashboardChecklistController::class, 'modules']);
            Route::get('/items',   [\App\Http\Controllers\Api\V1\Dashboard\DashboardChecklistController::class, 'index']);

            Route::post('/items',   [\App\Http\Controllers\Api\V1\Dashboard\DashboardChecklistController::class, 'store'])
                ->middleware($checklistEditRoles);
            Route::post('/items/reorder', [\App\Http\Controllers\Api\V1\Dashboard\DashboardChecklistController::class, 'reorder'])
                ->middleware($checklistEditRoles);
            Route::put('/items/{id}', [\App\Http\Controllers\Api\V1\Dashboard\DashboardChecklistController::class, 'update'])
                ->whereNumber('id')->middleware($checklistEditRoles);
            Route::delete('/items/{id}', [\App\Http\Controllers\Api\V1\Dashboard\DashboardChecklistController::class, 'destroy'])
                ->whereNumber('id')->middleware($checklistEditRoles);
        });

        // Dashboard monthly checklist — periodic (per-month) targets like
        // "Ground Check VHF minimum 2x per month". Read open, mutations
        // gated to MT / Supervisor / Admin.
        Route::prefix('dashboard/monthly')->group(function () {
            $monthlyEditRoles = 'role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP';

            Route::get('/summary',  [\App\Http\Controllers\Api\V1\Dashboard\DashboardMonthlyController::class, 'summary']);
            Route::get('/targets',  [\App\Http\Controllers\Api\V1\Dashboard\DashboardMonthlyController::class, 'index']);

            Route::post('/targets', [\App\Http\Controllers\Api\V1\Dashboard\DashboardMonthlyController::class, 'store'])
                ->middleware($monthlyEditRoles);
            Route::post('/targets/reorder', [\App\Http\Controllers\Api\V1\Dashboard\DashboardMonthlyController::class, 'reorder'])
                ->middleware($monthlyEditRoles);
            Route::put('/targets/{id}', [\App\Http\Controllers\Api\V1\Dashboard\DashboardMonthlyController::class, 'update'])
                ->whereNumber('id')->middleware($monthlyEditRoles);
            Route::delete('/targets/{id}', [\App\Http\Controllers\Api\V1\Dashboard\DashboardMonthlyController::class, 'destroy'])
                ->whereNumber('id')->middleware($monthlyEditRoles);
        });

        // ─── Work Orders ───────────────────────────────────────
        // All authenticated users can read (Teknisi visibility filtered in service)
        Route::get('/work-orders', [WorkOrderController::class, 'index']);
        Route::get('/work-orders/years', [WorkOrderController::class, 'years']);
        Route::get('/work-orders/{id}', [WorkOrderController::class, 'show']);

        // Create: Admin, Manager, Supervisors, and General Manager (for gm_directive WOs)
        Route::post('/work-orders', [WorkOrderController::class, 'store'])
            ->middleware('role:Admin,Manager Teknik,General Manager,Supervisor CNSD,Supervisor TFP');

        // Update: all roles can attempt (policy enforces per-resource rules)
        Route::put('/work-orders/{id}', [WorkOrderController::class, 'update']);
        Route::post('/work-orders/{id}/sign', [WorkOrderController::class, 'sign']);
        Route::get('/work-orders/{id}/print', [WorkOrderController::class, 'print']);

        // Delete: Admin, Manager, and General Manager (latter for own gm_directive WOs)
        Route::delete('/work-orders/{id}', [WorkOrderController::class, 'destroy'])
            ->middleware('role:Admin,Manager Teknik,General Manager,Supervisor CNSD,Supervisor TFP');

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
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');

            Route::get('/{id}',     [CnsdReadinessController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [CnsdReadinessController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdReadinessController::class, 'sign'])->whereNumber('id');

            // Structural edits — Manager Teknik / Supervisor CNSD only
            // (also enforced server-side in the controller method).
            Route::post('/{id}/items', [CnsdReadinessController::class, 'addItem'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
            Route::put('/{id}/items/{itemId}', [CnsdReadinessController::class, 'updateItem'])
                ->whereNumber('id')->whereNumber('itemId')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
            Route::delete('/{id}/items/{itemId}', [CnsdReadinessController::class, 'deleteItem'])
                ->whereNumber('id')->whereNumber('itemId')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
            Route::put('/{id}/sections', [CnsdReadinessController::class, 'renameSection'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');

            Route::delete('/{id}', [CnsdReadinessController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── CNSD Radar Meter Reading (Form RADAR-METER) ───────
        // Second CNSD module — "Meter Reading Radar".
        Route::prefix('cnsd/radar-meter')->group(function () {
            // Template + year filter must be declared BEFORE the {id} route so
            // /template and /years aren't captured by the int parameter.
            Route::get('/template', [CnsdRadarMeterController::class, 'template']);
            Route::get('/years',    [CnsdRadarMeterController::class, 'years']);

            Route::get('/',         [CnsdRadarMeterController::class, 'index']);
            Route::post('/', [CnsdRadarMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');

            Route::get('/{id}',     [CnsdRadarMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [CnsdRadarMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdRadarMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [CnsdRadarMeterController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── CNSD Recorder Meter Reading (Form RECORDER-METER / FORM C-3) ───
        // Third CNSD module — "Meter Reading Recorder". Other CNSD equipment
        // cards (AMSC, Transmitter, ...) remain Coming Soon and have no backend.
        Route::prefix('cnsd/recorder-meter')->group(function () {
            // Template + year filter must be declared BEFORE the {id} route so
            // /template and /years aren't captured by the int parameter.
            Route::get('/template', [CnsdRecorderMeterController::class, 'template']);
            Route::get('/years',    [CnsdRecorderMeterController::class, 'years']);

            Route::get('/',         [CnsdRecorderMeterController::class, 'index']);
            Route::post('/', [CnsdRecorderMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');

            Route::get('/{id}',     [CnsdRecorderMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [CnsdRecorderMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdRecorderMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [CnsdRecorderMeterController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── CNSD AMSC Meter Reading (Form AMSC-METER) ───────────
        // Fourth CNSD module — "Meter Reading AMSC".
        Route::prefix('cnsd/amsc-meter')->group(function () {
            Route::get('/template', [CnsdAmscMeterController::class, 'template']);
            Route::get('/years',    [CnsdAmscMeterController::class, 'years']);

            Route::get('/',         [CnsdAmscMeterController::class, 'index']);
            Route::post('/', [CnsdAmscMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');

            Route::get('/{id}',     [CnsdAmscMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [CnsdAmscMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdAmscMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [CnsdAmscMeterController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── CNSD Transmitter Meter Reading (Form TRANSMITTER-METER / FORM C-1) ───
        // Fifth CNSD module — "Meter Reading Transmitter".
        Route::prefix('cnsd/transmitter-meter')->group(function () {
            Route::get('/template', [CnsdTransmitterMeterController::class, 'template']);
            Route::get('/years',    [CnsdTransmitterMeterController::class, 'years']);

            Route::get('/',         [CnsdTransmitterMeterController::class, 'index']);
            Route::post('/', [CnsdTransmitterMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');

            Route::get('/{id}',     [CnsdTransmitterMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [CnsdTransmitterMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdTransmitterMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [CnsdTransmitterMeterController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── CNSD Receiver Meter Reading (Form RECEIVER-METER / FORM C-2) ───
        // Sixth CNSD module — "Meter Reading Receiver".
        Route::prefix('cnsd/receiver-meter')->group(function () {
            Route::get('/template', [CnsdReceiverMeterController::class, 'template']);
            Route::get('/years',    [CnsdReceiverMeterController::class, 'years']);

            Route::get('/',         [CnsdReceiverMeterController::class, 'index']);
            Route::post('/', [CnsdReceiverMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');

            Route::get('/{id}',     [CnsdReceiverMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [CnsdReceiverMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdReceiverMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [CnsdReceiverMeterController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── CNSD Glide Path Meter Reading (Form GLIDEPATH-METER / ILS-GP) ───
        // Seventh CNSD module — "Meter Reading Glide Path".
        Route::prefix('cnsd/glidepath-meter')->group(function () {
            Route::get('/template', [CnsdGlidepathMeterController::class, 'template']);
            Route::get('/years',    [CnsdGlidepathMeterController::class, 'years']);
            Route::get('/',         [CnsdGlidepathMeterController::class, 'index']);
            Route::post('/', [CnsdGlidepathMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');
            Route::get('/{id}',       [CnsdGlidepathMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',       [CnsdGlidepathMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdGlidepathMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}',    [CnsdGlidepathMeterController::class, 'destroy'])
                ->whereNumber('id')->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── CNSD Localizer Meter Reading (Form LOCALIZER-METER / ILS-LLZ) ───
        // Eighth CNSD module — "Meter Reading Localizer".
        Route::prefix('cnsd/localizer-meter')->group(function () {
            Route::get('/template', [CnsdLocalizerMeterController::class, 'template']);
            Route::get('/years',    [CnsdLocalizerMeterController::class, 'years']);
            Route::get('/',         [CnsdLocalizerMeterController::class, 'index']);
            Route::post('/', [CnsdLocalizerMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');
            Route::get('/{id}',       [CnsdLocalizerMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',       [CnsdLocalizerMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdLocalizerMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}',    [CnsdLocalizerMeterController::class, 'destroy'])
                ->whereNumber('id')->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── CNSD T-DME Meter Reading (Form TDME-METER / FORM N-5) ───
        // Ninth CNSD module — "Meter Reading T-DME".
        Route::prefix('cnsd/tdme-meter')->group(function () {
            Route::get('/template', [CnsdTdmeMeterController::class, 'template']);
            Route::get('/years',    [CnsdTdmeMeterController::class, 'years']);
            Route::get('/',         [CnsdTdmeMeterController::class, 'index']);
            Route::post('/', [CnsdTdmeMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');
            Route::get('/{id}',       [CnsdTdmeMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',       [CnsdTdmeMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdTdmeMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}',    [CnsdTdmeMeterController::class, 'destroy'])
                ->whereNumber('id')->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── CNSD DVOR Meter Reading (Form DVOR-METER / FORM N-5) ───
        // Tenth CNSD module — "Meter Reading DVOR".
        Route::prefix('cnsd/dvor-meter')->group(function () {
            Route::get('/template', [CnsdDvorMeterController::class, 'template']);
            Route::get('/years',    [CnsdDvorMeterController::class, 'years']);
            Route::get('/',         [CnsdDvorMeterController::class, 'index']);
            Route::post('/', [CnsdDvorMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');
            Route::get('/{id}',       [CnsdDvorMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',       [CnsdDvorMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdDvorMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}',    [CnsdDvorMeterController::class, 'destroy'])
                ->whereNumber('id')->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── CNSD DME Meter Reading (Form DME-METER / FORM N-5) ───
        // Eleventh CNSD module — "Meter Reading DME".
        Route::prefix('cnsd/dme-meter')->group(function () {
            Route::get('/template', [CnsdDmeMeterController::class, 'template']);
            Route::get('/years',    [CnsdDmeMeterController::class, 'years']);
            Route::get('/',         [CnsdDmeMeterController::class, 'index']);
            Route::post('/', [CnsdDmeMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');
            Route::get('/{id}',       [CnsdDmeMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',       [CnsdDmeMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdDmeMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}',    [CnsdDmeMeterController::class, 'destroy'])
                ->whereNumber('id')->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── CNSD ATIS Meter Reading (Form ATIS-METER, Reproducer ATIS) ───
        // Twelfth CNSD module — "Meter Reading ATIS".
        Route::prefix('cnsd/atis-meter')->group(function () {
            Route::get('/template', [CnsdAtisMeterController::class, 'template']);
            Route::get('/years',    [CnsdAtisMeterController::class, 'years']);
            Route::get('/',         [CnsdAtisMeterController::class, 'index']);
            Route::post('/', [CnsdAtisMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');
            Route::get('/{id}',       [CnsdAtisMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',       [CnsdAtisMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdAtisMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}',    [CnsdAtisMeterController::class, 'destroy'])
                ->whereNumber('id')->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── CNSD ATC SYSTEM Meter Reading (Form A-1, Approach System / Tern ATS System) ───
        // Thirteenth CNSD module — "Meter Reading ATC SYSTEM".
        Route::prefix('cnsd/atc-system-meter')->group(function () {
            Route::get('/template', [CnsdAtcSystemMeterController::class, 'template']);
            Route::get('/years',    [CnsdAtcSystemMeterController::class, 'years']);
            Route::get('/',         [CnsdAtcSystemMeterController::class, 'index']);
            Route::post('/', [CnsdAtcSystemMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');
            Route::get('/{id}',       [CnsdAtcSystemMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',       [CnsdAtcSystemMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdAtcSystemMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}',    [CnsdAtcSystemMeterController::class, 'destroy'])
                ->whereNumber('id')->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── CNSD VCCS Meter Reading (Form VCCS-METER, VCCS LES) ────
        // Fourteenth CNSD module — "Meter Reading VCCS" (brand LES).
        Route::prefix('cnsd/vccs-meter')->group(function () {
            Route::get('/template', [CnsdVccsMeterController::class, 'template']);
            Route::get('/years',    [CnsdVccsMeterController::class, 'years']);
            Route::get('/',         [CnsdVccsMeterController::class, 'index']);
            Route::post('/', [CnsdVccsMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');
            Route::get('/{id}',       [CnsdVccsMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',       [CnsdVccsMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdVccsMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}',    [CnsdVccsMeterController::class, 'destroy'])
                ->whereNumber('id')->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── CNSD VCCS Frequentis Meter Reading (Form VCCS-FREQ-METER) ────
        // Fifteenth CNSD module — "Meter Reading VCCS" (brand FREQUENTIS).
        // Sister module to CNSD-014 (LES) with the same UI conventions but
        // independent data and its own paper-form content.
        Route::prefix('cnsd/vccs-freq-meter')->group(function () {
            Route::get('/template', [CnsdVccsFreqMeterController::class, 'template']);
            Route::get('/years',    [CnsdVccsFreqMeterController::class, 'years']);
            Route::get('/',         [CnsdVccsFreqMeterController::class, 'index']);
            Route::post('/', [CnsdVccsFreqMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');
            Route::get('/{id}',       [CnsdVccsFreqMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',       [CnsdVccsFreqMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdVccsFreqMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}',    [CnsdVccsFreqMeterController::class, 'destroy'])
                ->whereNumber('id')->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── CNSD ASMGCS Meter Reading (Form ASMGCS-METER, SAAB) ────
        // Sixteenth CNSD module — "Meter Reading ASMGCS" (brand SAAB).
        Route::prefix('cnsd/asmgcs-meter')->group(function () {
            Route::get('/template', [CnsdAsmgcsMeterController::class, 'template']);
            Route::get('/years',    [CnsdAsmgcsMeterController::class, 'years']);
            Route::get('/',         [CnsdAsmgcsMeterController::class, 'index']);
            Route::post('/', [CnsdAsmgcsMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');
            Route::get('/{id}',       [CnsdAsmgcsMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',       [CnsdAsmgcsMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdAsmgcsMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}',    [CnsdAsmgcsMeterController::class, 'destroy'])
                ->whereNumber('id')->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── TFP Performance Check AOB Lantai Ground ───────────
        Route::prefix('tfp/aob-ground')->group(function () {
            Route::get('/template', [TfpAobGroundController::class, 'template']);
            Route::get('/years',    [TfpAobGroundController::class, 'years']);
            Route::get('/',         [TfpAobGroundController::class, 'index']);
            Route::post('/', [TfpAobGroundController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi TFP');
            Route::get('/{id}',     [TfpAobGroundController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [TfpAobGroundController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [TfpAobGroundController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [TfpAobGroundController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');

            // Structural edit (Edit Mode) — controller enforces role guard
            Route::put('/{id}/structure',              [TfpAobGroundController::class, 'saveStructure'])->whereNumber('id');
            Route::post('/{id}/parameters',            [TfpAobGroundController::class, 'addParameter'])->whereNumber('id');
            Route::put('/{id}/parameters/{paramId}',   [TfpAobGroundController::class, 'updateParameter'])->whereNumber(['id', 'paramId']);
            Route::delete('/{id}/parameters/{paramId}', [TfpAobGroundController::class, 'deleteParameter'])->whereNumber(['id', 'paramId']);
            Route::put('/{id}/parameters-reorder',     [TfpAobGroundController::class, 'reorderParameters'])->whereNumber('id');
            Route::post('/{id}/facilities',            [TfpAobGroundController::class, 'addFacility'])->whereNumber('id');
            Route::put('/{id}/facilities/{facilityId}', [TfpAobGroundController::class, 'updateFacility'])->whereNumber(['id', 'facilityId']);
            Route::delete('/{id}/facilities/{facilityId}', [TfpAobGroundController::class, 'deleteFacility'])->whereNumber(['id', 'facilityId']);
            Route::put('/{id}/facilities-reorder',     [TfpAobGroundController::class, 'reorderFacilities'])->whereNumber('id');
        });

        // ─── TFP Performance Check AOB Lantai 1 & 2 ───────────
        Route::prefix('tfp/aob-lt12')->group(function () {
            Route::get('/template', [TfpAobLt12Controller::class, 'template']);
            Route::get('/years',    [TfpAobLt12Controller::class, 'years']);
            Route::get('/',         [TfpAobLt12Controller::class, 'index']);
            Route::post('/', [TfpAobLt12Controller::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi TFP');
            Route::get('/{id}',     [TfpAobLt12Controller::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [TfpAobLt12Controller::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [TfpAobLt12Controller::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [TfpAobLt12Controller::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');

            // Structural edit (Edit Mode) — controller enforces role guard
            Route::put('/{id}/structure',                  [TfpAobLt12Controller::class, 'saveStructure'])->whereNumber('id');
            Route::post('/{id}/parameters',                [TfpAobLt12Controller::class, 'addParameter'])->whereNumber('id');
            Route::put('/{id}/parameters/{paramId}',       [TfpAobLt12Controller::class, 'updateParameter'])->whereNumber(['id', 'paramId']);
            Route::delete('/{id}/parameters/{paramId}',    [TfpAobLt12Controller::class, 'deleteParameter'])->whereNumber(['id', 'paramId']);
            Route::put('/{id}/parameters-reorder',         [TfpAobLt12Controller::class, 'reorderParameters'])->whereNumber('id');
            Route::post('/{id}/facilities',                [TfpAobLt12Controller::class, 'addFacility'])->whereNumber('id');
            Route::put('/{id}/facilities/{facilityId}',    [TfpAobLt12Controller::class, 'updateFacility'])->whereNumber(['id', 'facilityId']);
            Route::delete('/{id}/facilities/{facilityId}', [TfpAobLt12Controller::class, 'deleteFacility'])->whereNumber(['id', 'facilityId']);
            Route::put('/{id}/facilities-reorder',         [TfpAobLt12Controller::class, 'reorderFacilities'])->whereNumber('id');
        });

        // ─── TFP Performance Check Transmitter TX ──────────────
        Route::prefix('tfp/transmitter-tx')->group(function () {
            Route::get('/template', [TfpTransmitterTxController::class, 'template']);
            Route::get('/years',    [TfpTransmitterTxController::class, 'years']);
            Route::get('/',         [TfpTransmitterTxController::class, 'index']);
            Route::post('/', [TfpTransmitterTxController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi TFP');
            Route::get('/{id}',     [TfpTransmitterTxController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [TfpTransmitterTxController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [TfpTransmitterTxController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [TfpTransmitterTxController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');

            // Structural edit (Edit Mode) — controller enforces role guard
            Route::put('/{id}/structure',                  [TfpTransmitterTxController::class, 'saveStructure'])->whereNumber('id');
            Route::post('/{id}/parameters',                [TfpTransmitterTxController::class, 'addParameter'])->whereNumber('id');
            Route::put('/{id}/parameters/{paramId}',       [TfpTransmitterTxController::class, 'updateParameter'])->whereNumber(['id', 'paramId']);
            Route::delete('/{id}/parameters/{paramId}',    [TfpTransmitterTxController::class, 'deleteParameter'])->whereNumber(['id', 'paramId']);
            Route::put('/{id}/parameters-reorder',         [TfpTransmitterTxController::class, 'reorderParameters'])->whereNumber('id');
            Route::post('/{id}/facilities',                [TfpTransmitterTxController::class, 'addFacility'])->whereNumber('id');
            Route::put('/{id}/facilities/{facilityId}',    [TfpTransmitterTxController::class, 'updateFacility'])->whereNumber(['id', 'facilityId']);
            Route::delete('/{id}/facilities/{facilityId}', [TfpTransmitterTxController::class, 'deleteFacility'])->whereNumber(['id', 'facilityId']);
            Route::put('/{id}/facilities-reorder',         [TfpTransmitterTxController::class, 'reorderFacilities'])->whereNumber('id');
        });

        // ─── TFP Performance Check Gedung Tower ────────────────
        Route::prefix('tfp/tower')->group(function () {
            Route::get('/template', [TfpTowerController::class, 'template']);
            Route::get('/years',    [TfpTowerController::class, 'years']);
            Route::get('/',         [TfpTowerController::class, 'index']);
            Route::post('/', [TfpTowerController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi TFP');
            Route::get('/{id}',     [TfpTowerController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [TfpTowerController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [TfpTowerController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [TfpTowerController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');

            // Structural edit (Edit Mode) — controller enforces role guard
            Route::put('/{id}/structure',                  [TfpTowerController::class, 'saveStructure'])->whereNumber('id');
            Route::post('/{id}/parameters',                [TfpTowerController::class, 'addParameter'])->whereNumber('id');
            Route::put('/{id}/parameters/{paramId}',       [TfpTowerController::class, 'updateParameter'])->whereNumber(['id', 'paramId']);
            Route::delete('/{id}/parameters/{paramId}',    [TfpTowerController::class, 'deleteParameter'])->whereNumber(['id', 'paramId']);
            Route::put('/{id}/parameters-reorder',         [TfpTowerController::class, 'reorderParameters'])->whereNumber('id');
            Route::post('/{id}/facilities',                [TfpTowerController::class, 'addFacility'])->whereNumber('id');
            Route::put('/{id}/facilities/{facilityId}',    [TfpTowerController::class, 'updateFacility'])->whereNumber(['id', 'facilityId']);
            Route::delete('/{id}/facilities/{facilityId}', [TfpTowerController::class, 'deleteFacility'])->whereNumber(['id', 'facilityId']);
            Route::put('/{id}/facilities-reorder',         [TfpTowerController::class, 'reorderFacilities'])->whereNumber('id');
        });

        // ─── TFP Performance Check Gedung Radar ────────────────────
        Route::prefix('tfp/radar')->group(function () {
            Route::get('/template', [TfpRadarController::class, 'template']);
            Route::get('/years',    [TfpRadarController::class, 'years']);
            Route::get('/',         [TfpRadarController::class, 'index']);
            Route::post('/', [TfpRadarController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi TFP');
            Route::get('/{id}',     [TfpRadarController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [TfpRadarController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [TfpRadarController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [TfpRadarController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');

            // Structural edit (Edit Mode) — controller enforces role guard
            Route::put('/{id}/structure',                  [TfpRadarController::class, 'saveStructure'])->whereNumber('id');
            Route::post('/{id}/parameters',                [TfpRadarController::class, 'addParameter'])->whereNumber('id');
            Route::put('/{id}/parameters/{paramId}',       [TfpRadarController::class, 'updateParameter'])->whereNumber(['id', 'paramId']);
            Route::delete('/{id}/parameters/{paramId}',    [TfpRadarController::class, 'deleteParameter'])->whereNumber(['id', 'paramId']);
            Route::put('/{id}/parameters-reorder',         [TfpRadarController::class, 'reorderParameters'])->whereNumber('id');
            Route::post('/{id}/facilities',                [TfpRadarController::class, 'addFacility'])->whereNumber('id');
            Route::put('/{id}/facilities/{facilityId}',    [TfpRadarController::class, 'updateFacility'])->whereNumber(['id', 'facilityId']);
            Route::delete('/{id}/facilities/{facilityId}', [TfpRadarController::class, 'deleteFacility'])->whereNumber(['id', 'facilityId']);
            Route::put('/{id}/facilities-reorder',         [TfpRadarController::class, 'reorderFacilities'])->whereNumber('id');
        });

        // ─── TFP Performance Check Gedung DVOR (VOR) ───────────────
        Route::prefix('tfp/dvor')->group(function () {
            Route::get('/template', [TfpDvorController::class, 'template']);
            Route::get('/years',    [TfpDvorController::class, 'years']);
            Route::get('/',         [TfpDvorController::class, 'index']);
            Route::post('/', [TfpDvorController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi TFP');
            Route::get('/{id}',     [TfpDvorController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [TfpDvorController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [TfpDvorController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [TfpDvorController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');

            // Structural edit (Edit Mode) — controller enforces role guard
            Route::put('/{id}/structure',                  [TfpDvorController::class, 'saveStructure'])->whereNumber('id');
            Route::post('/{id}/parameters',                [TfpDvorController::class, 'addParameter'])->whereNumber('id');
            Route::put('/{id}/parameters/{paramId}',       [TfpDvorController::class, 'updateParameter'])->whereNumber(['id', 'paramId']);
            Route::delete('/{id}/parameters/{paramId}',    [TfpDvorController::class, 'deleteParameter'])->whereNumber(['id', 'paramId']);
            Route::put('/{id}/parameters-reorder',         [TfpDvorController::class, 'reorderParameters'])->whereNumber('id');
            Route::post('/{id}/facilities',                [TfpDvorController::class, 'addFacility'])->whereNumber('id');
            Route::put('/{id}/facilities/{facilityId}',    [TfpDvorController::class, 'updateFacility'])->whereNumber(['id', 'facilityId']);
            Route::delete('/{id}/facilities/{facilityId}', [TfpDvorController::class, 'deleteFacility'])->whereNumber(['id', 'facilityId']);
            Route::put('/{id}/facilities-reorder',         [TfpDvorController::class, 'reorderFacilities'])->whereNumber('id');
        });

        // ─── TFP Performance Check Gedung Localizer ─────────────────
        Route::prefix('tfp/localizer')->group(function () {
            Route::get('/template', [TfpLocalizerController::class, 'template']);
            Route::get('/years',    [TfpLocalizerController::class, 'years']);
            Route::get('/',         [TfpLocalizerController::class, 'index']);
            Route::post('/', [TfpLocalizerController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi TFP');
            Route::get('/{id}',     [TfpLocalizerController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [TfpLocalizerController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [TfpLocalizerController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [TfpLocalizerController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');

            // Structural edit (Edit Mode) — controller enforces role guard
            Route::put('/{id}/structure',                  [TfpLocalizerController::class, 'saveStructure'])->whereNumber('id');
            Route::post('/{id}/parameters',                [TfpLocalizerController::class, 'addParameter'])->whereNumber('id');
            Route::put('/{id}/parameters/{paramId}',       [TfpLocalizerController::class, 'updateParameter'])->whereNumber(['id', 'paramId']);
            Route::delete('/{id}/parameters/{paramId}',    [TfpLocalizerController::class, 'deleteParameter'])->whereNumber(['id', 'paramId']);
            Route::put('/{id}/parameters-reorder',         [TfpLocalizerController::class, 'reorderParameters'])->whereNumber('id');
            Route::post('/{id}/facilities',                [TfpLocalizerController::class, 'addFacility'])->whereNumber('id');
            Route::put('/{id}/facilities/{facilityId}',    [TfpLocalizerController::class, 'updateFacility'])->whereNumber(['id', 'facilityId']);
            Route::delete('/{id}/facilities/{facilityId}', [TfpLocalizerController::class, 'deleteFacility'])->whereNumber(['id', 'facilityId']);
            Route::put('/{id}/facilities-reorder',         [TfpLocalizerController::class, 'reorderFacilities'])->whereNumber('id');
        });

        // ─── TFP Performance Check Gedung Glide Path ────────────────
        Route::prefix('tfp/glidepath')->group(function () {
            Route::get('/template', [TfpGlidepathController::class, 'template']);
            Route::get('/years',    [TfpGlidepathController::class, 'years']);
            Route::get('/',         [TfpGlidepathController::class, 'index']);
            Route::post('/', [TfpGlidepathController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi TFP');
            Route::get('/{id}',     [TfpGlidepathController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [TfpGlidepathController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [TfpGlidepathController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [TfpGlidepathController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');

            // Structural edit (Edit Mode) — controller enforces role guard
            Route::put('/{id}/structure',                  [TfpGlidepathController::class, 'saveStructure'])->whereNumber('id');
            Route::post('/{id}/parameters',                [TfpGlidepathController::class, 'addParameter'])->whereNumber('id');
            Route::put('/{id}/parameters/{paramId}',       [TfpGlidepathController::class, 'updateParameter'])->whereNumber(['id', 'paramId']);
            Route::delete('/{id}/parameters/{paramId}',    [TfpGlidepathController::class, 'deleteParameter'])->whereNumber(['id', 'paramId']);
            Route::put('/{id}/parameters-reorder',         [TfpGlidepathController::class, 'reorderParameters'])->whereNumber('id');
            Route::post('/{id}/facilities',                [TfpGlidepathController::class, 'addFacility'])->whereNumber('id');
            Route::put('/{id}/facilities/{facilityId}',    [TfpGlidepathController::class, 'updateFacility'])->whereNumber(['id', 'facilityId']);
            Route::delete('/{id}/facilities/{facilityId}', [TfpGlidepathController::class, 'deleteFacility'])->whereNumber(['id', 'facilityId']);
            Route::put('/{id}/facilities-reorder',         [TfpGlidepathController::class, 'reorderFacilities'])->whereNumber('id');
        });

        // ─── Grounding Report ───────────────────────────────────────
        Route::prefix('grounding/reports')->group(function () {
            Route::get('/template', [GroundingReportController::class, 'template']);
            Route::get('/years',    [GroundingReportController::class, 'years']);
            Route::get('/',         [GroundingReportController::class, 'index']);
            Route::post('/', [GroundingReportController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi TFP');
            Route::get('/{id}',     [GroundingReportController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [GroundingReportController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [GroundingReportController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [GroundingReportController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── Ground Check ADC ───────────────────────────────────────
        Route::prefix('ground-check/adc')->group(function () {
            Route::get('/template', [GroundCheckAdcController::class, 'template']);
            Route::get('/years',    [GroundCheckAdcController::class, 'years']);
            Route::get('/',         [GroundCheckAdcController::class, 'index']);
            Route::post('/', [GroundCheckAdcController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');
            Route::get('/{id}',     [GroundCheckAdcController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [GroundCheckAdcController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [GroundCheckAdcController::class, 'sign'])->whereNumber('id');
            // Photo documentation
            Route::post('/{id}/photos', [GroundCheckAdcController::class, 'uploadPhoto'])->whereNumber('id');
            Route::put('/{id}/photos/{photoId}', [GroundCheckAdcController::class, 'updatePhoto'])->whereNumber('id')->whereNumber('photoId');
            Route::delete('/{id}/photos/{photoId}', [GroundCheckAdcController::class, 'deletePhoto'])->whereNumber('id')->whereNumber('photoId');
            Route::delete('/{id}', [GroundCheckAdcController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── Ground Check VHF ───────────────────────────────────────
        Route::prefix('ground-check/vhf')->group(function () {
            Route::get('/template', [GroundCheckVhfController::class, 'template']);
            Route::get('/years',    [GroundCheckVhfController::class, 'years']);
            Route::get('/',         [GroundCheckVhfController::class, 'index']);
            Route::post('/', [GroundCheckVhfController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');
            Route::get('/{id}',     [GroundCheckVhfController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [GroundCheckVhfController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [GroundCheckVhfController::class, 'sign'])->whereNumber('id');
            // Photo documentation
            Route::post('/{id}/photos', [GroundCheckVhfController::class, 'uploadPhoto'])->whereNumber('id');
            Route::put('/{id}/photos/{photoId}', [GroundCheckVhfController::class, 'updatePhoto'])->whereNumber('id')->whereNumber('photoId');
            Route::delete('/{id}/photos/{photoId}', [GroundCheckVhfController::class, 'deletePhoto'])->whereNumber('id')->whereNumber('photoId');
            Route::delete('/{id}', [GroundCheckVhfController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── Ground Check LLZ (ILS Localizer) ───────────────────────
        Route::prefix('ground-check/llz')->group(function () {
            Route::get('/template', [GroundCheckLlzController::class, 'template']);
            Route::get('/years',    [GroundCheckLlzController::class, 'years']);
            Route::get('/',         [GroundCheckLlzController::class, 'index']);
            Route::post('/', [GroundCheckLlzController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');
            Route::get('/{id}',     [GroundCheckLlzController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [GroundCheckLlzController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [GroundCheckLlzController::class, 'sign'])->whereNumber('id');
            // Photo documentation
            Route::post('/{id}/photos', [GroundCheckLlzController::class, 'uploadPhoto'])->whereNumber('id');
            Route::put('/{id}/photos/{photoId}', [GroundCheckLlzController::class, 'updatePhoto'])->whereNumber('id')->whereNumber('photoId');
            Route::delete('/{id}/photos/{photoId}', [GroundCheckLlzController::class, 'deletePhoto'])->whereNumber('id')->whereNumber('photoId');
            Route::delete('/{id}', [GroundCheckLlzController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── Ground Check GP (ILS Glide Path) ───────────────────────
        Route::prefix('ground-check/gp')->group(function () {
            Route::get('/template', [GroundCheckGpController::class, 'template']);
            Route::get('/years',    [GroundCheckGpController::class, 'years']);
            Route::get('/',         [GroundCheckGpController::class, 'index']);
            Route::post('/', [GroundCheckGpController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');
            Route::get('/{id}',     [GroundCheckGpController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [GroundCheckGpController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [GroundCheckGpController::class, 'sign'])->whereNumber('id');
            // Photo documentation
            Route::post('/{id}/photos', [GroundCheckGpController::class, 'uploadPhoto'])->whereNumber('id');
            Route::put('/{id}/photos/{photoId}', [GroundCheckGpController::class, 'updatePhoto'])->whereNumber('id')->whereNumber('photoId');
            Route::delete('/{id}/photos/{photoId}', [GroundCheckGpController::class, 'deletePhoto'])->whereNumber('id')->whereNumber('photoId');
            Route::delete('/{id}', [GroundCheckGpController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── Ground Check DVOR (Doppler VHF Omnidirectional Range) ──
        Route::prefix('ground-check/dvor')->group(function () {
            Route::get('/template', [GroundCheckDvorController::class, 'template']);
            Route::get('/years',    [GroundCheckDvorController::class, 'years']);
            Route::get('/',         [GroundCheckDvorController::class, 'index']);
            Route::post('/', [GroundCheckDvorController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD');
            Route::get('/{id}',     [GroundCheckDvorController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [GroundCheckDvorController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [GroundCheckDvorController::class, 'sign'])->whereNumber('id');
            // Photo documentation
            Route::post('/{id}/photos', [GroundCheckDvorController::class, 'uploadPhoto'])->whereNumber('id');
            Route::put('/{id}/photos/{photoId}', [GroundCheckDvorController::class, 'updatePhoto'])->whereNumber('id')->whereNumber('photoId');
            Route::delete('/{id}/photos/{photoId}', [GroundCheckDvorController::class, 'deletePhoto'])->whereNumber('id')->whereNumber('photoId');
            Route::delete('/{id}', [GroundCheckDvorController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── Reporting / Laporan Kerusakan ─────────────────────────
        // Form Laporan Kerusakan (Damage Report). Tidak menggunakan roster
        // otomatis — Manager Teknik dan Pelaksana Perbaikan dipilih manual.
        Route::prefix('reporting/personnel')->group(function () {
            Route::get('/', [ReportingPersonController::class, 'index']);
        });

        Route::prefix('reporting/damage-reports')->group(function () {
            Route::get('/years',    [ReportingDamageReportController::class, 'years']);

            Route::get('/',         [ReportingDamageReportController::class, 'index']);
            Route::post('/', [ReportingDamageReportController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD,Teknisi TFP');

            Route::get('/{id}',     [ReportingDamageReportController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [ReportingDamageReportController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [ReportingDamageReportController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [ReportingDamageReportController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');
        });

        // ─── Logbook TFP ───────────────────────────────────────────
        // Write endpoints are gated per-row so opposite-division technicians
        // (Teknisi CNSD here) can only READ TFP logbook, never modify it.
        // Equipment management + signing further excludes teknisi entirely.
        Route::prefix('logbook/tfp')->group(function () {
            // Read endpoints — open to any authenticated user
            Route::get('/years',      [LogbookTfpController::class, 'years']);
            Route::get('/equipments', [LogbookTfpController::class, 'equipments']);
            Route::get('/',           [LogbookTfpController::class, 'index']);
            Route::get('/{id}',       [LogbookTfpController::class, 'show'])->whereNumber('id');

            // Create logbook + fill notes/status — Teknisi TFP allowed
            $tfpFillRoles = 'role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi TFP';
            Route::post('/', [LogbookTfpController::class, 'store'])
                ->middleware($tfpFillRoles);
            Route::put('/{id}/items', [LogbookTfpController::class, 'updateItems'])
                ->whereNumber('id')->middleware($tfpFillRoles);
            Route::post('/{id}/bulk-status', [LogbookTfpController::class, 'bulkStatus'])
                ->whereNumber('id')->middleware($tfpFillRoles);
            Route::post('/{id}/notes', [LogbookTfpController::class, 'addNote'])
                ->whereNumber('id')->middleware($tfpFillRoles);
            Route::delete('/{id}/notes/{noteId}', [LogbookTfpController::class, 'deleteNote'])
                ->whereNumber('id')->whereNumber('noteId')->middleware($tfpFillRoles);

            // Equipment management + sign — Teknisi excluded
            $tfpManageRoles = 'role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP';
            Route::post('/{id}/equipments', [LogbookTfpController::class, 'addEquipment'])
                ->whereNumber('id')->middleware($tfpManageRoles);
            Route::put('/{id}/equipments/{itemId}', [LogbookTfpController::class, 'editEquipment'])
                ->whereNumber('id')->whereNumber('itemId')->middleware($tfpManageRoles);
            Route::delete('/{id}/equipments/{itemId}', [LogbookTfpController::class, 'removeEquipment'])
                ->whereNumber('id')->whereNumber('itemId')->middleware($tfpManageRoles);
            Route::post('/{id}/sign', [LogbookTfpController::class, 'sign'])
                ->whereNumber('id')->middleware($tfpManageRoles);
            Route::delete('/{id}', [LogbookTfpController::class, 'destroy'])
                ->whereNumber('id')->middleware($tfpManageRoles);
        });

        // ─── Logbook CNSD ──────────────────────────────────────────
        // Mirror of Logbook TFP — Teknisi CNSD can fill notes/status but not
        // manage equipment, Teknisi TFP is fully blocked from writes here.
        Route::prefix('logbook/cnsd')->group(function () {
            Route::get('/years',      [LogbookCnsdController::class, 'years']);
            Route::get('/equipments', [LogbookCnsdController::class, 'equipments']);
            Route::get('/',           [LogbookCnsdController::class, 'index']);
            Route::get('/{id}',       [LogbookCnsdController::class, 'show'])->whereNumber('id');

            $cnsdFillRoles = 'role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP,Teknisi CNSD';
            Route::post('/', [LogbookCnsdController::class, 'store'])
                ->middleware($cnsdFillRoles);
            Route::put('/{id}/items', [LogbookCnsdController::class, 'updateItems'])
                ->whereNumber('id')->middleware($cnsdFillRoles);
            Route::post('/{id}/bulk-status', [LogbookCnsdController::class, 'bulkStatus'])
                ->whereNumber('id')->middleware($cnsdFillRoles);
            Route::post('/{id}/notes', [LogbookCnsdController::class, 'addNote'])
                ->whereNumber('id')->middleware($cnsdFillRoles);
            Route::delete('/{id}/notes/{noteId}', [LogbookCnsdController::class, 'deleteNote'])
                ->whereNumber('id')->whereNumber('noteId')->middleware($cnsdFillRoles);

            $cnsdManageRoles = 'role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP';
            Route::post('/{id}/equipments', [LogbookCnsdController::class, 'addEquipment'])
                ->whereNumber('id')->middleware($cnsdManageRoles);
            Route::put('/{id}/equipments/{itemId}', [LogbookCnsdController::class, 'editEquipment'])
                ->whereNumber('id')->whereNumber('itemId')->middleware($cnsdManageRoles);
            Route::delete('/{id}/equipments/{itemId}', [LogbookCnsdController::class, 'removeEquipment'])
                ->whereNumber('id')->whereNumber('itemId')->middleware($cnsdManageRoles);
            Route::post('/{id}/sign', [LogbookCnsdController::class, 'sign'])
                ->whereNumber('id')->middleware($cnsdManageRoles);
            Route::delete('/{id}', [LogbookCnsdController::class, 'destroy'])
                ->whereNumber('id')->middleware($cnsdManageRoles);
        });

        // ─── Personnel ─────────────────────────────────────────
        Route::get('/personnel', [PersonnelController::class, 'index']);
        // Real shift context from atoms-rostering (read-only DB query)
        Route::get('/personnel/shift-today', [PersonnelController::class, 'shiftToday']);

        // ─── Monitor settings (authenticated) ──────────────────
        // Rotate the kiosk gate password. Manager Teknik / Supervisor / Admin only —
        // the controller validates the old password before persisting the new one.
        Route::put('/monitor/password', [\App\Http\Controllers\Api\V1\MonitorController::class, 'updatePassword'])
            ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Supervisor TFP');

        // ─── Notifications ─────────────────────────────────────
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    });
});
