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
use App\Http\Controllers\Api\V1\Reporting\ReportingDamageReportController;
use App\Http\Controllers\Api\V1\Reporting\ReportingPersonController;

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

        // ─── CNSD Radar Meter Reading (Form RADAR-METER) ───────
        // Second CNSD module — "Meter Reading Radar".
        Route::prefix('cnsd/radar-meter')->group(function () {
            // Template + year filter must be declared BEFORE the {id} route so
            // /template and /years aren't captured by the int parameter.
            Route::get('/template', [CnsdRadarMeterController::class, 'template']);
            Route::get('/years',    [CnsdRadarMeterController::class, 'years']);

            Route::get('/',         [CnsdRadarMeterController::class, 'index']);
            Route::post('/', [CnsdRadarMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Teknisi CNSD');

            Route::get('/{id}',     [CnsdRadarMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [CnsdRadarMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdRadarMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [CnsdRadarMeterController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik');
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
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Teknisi CNSD');

            Route::get('/{id}',     [CnsdRecorderMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [CnsdRecorderMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdRecorderMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [CnsdRecorderMeterController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik');
        });

        // ─── CNSD AMSC Meter Reading (Form AMSC-METER) ───────────
        // Fourth CNSD module — "Meter Reading AMSC".
        Route::prefix('cnsd/amsc-meter')->group(function () {
            Route::get('/template', [CnsdAmscMeterController::class, 'template']);
            Route::get('/years',    [CnsdAmscMeterController::class, 'years']);

            Route::get('/',         [CnsdAmscMeterController::class, 'index']);
            Route::post('/', [CnsdAmscMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Teknisi CNSD');

            Route::get('/{id}',     [CnsdAmscMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [CnsdAmscMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdAmscMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [CnsdAmscMeterController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik');
        });

        // ─── CNSD Transmitter Meter Reading (Form TRANSMITTER-METER / FORM C-1) ───
        // Fifth CNSD module — "Meter Reading Transmitter".
        Route::prefix('cnsd/transmitter-meter')->group(function () {
            Route::get('/template', [CnsdTransmitterMeterController::class, 'template']);
            Route::get('/years',    [CnsdTransmitterMeterController::class, 'years']);

            Route::get('/',         [CnsdTransmitterMeterController::class, 'index']);
            Route::post('/', [CnsdTransmitterMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Teknisi CNSD');

            Route::get('/{id}',     [CnsdTransmitterMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [CnsdTransmitterMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdTransmitterMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [CnsdTransmitterMeterController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik');
        });

        // ─── CNSD Receiver Meter Reading (Form RECEIVER-METER / FORM C-2) ───
        // Sixth CNSD module — "Meter Reading Receiver".
        Route::prefix('cnsd/receiver-meter')->group(function () {
            Route::get('/template', [CnsdReceiverMeterController::class, 'template']);
            Route::get('/years',    [CnsdReceiverMeterController::class, 'years']);

            Route::get('/',         [CnsdReceiverMeterController::class, 'index']);
            Route::post('/', [CnsdReceiverMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Teknisi CNSD');

            Route::get('/{id}',     [CnsdReceiverMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [CnsdReceiverMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdReceiverMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [CnsdReceiverMeterController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik');
        });

        // ─── CNSD Glide Path Meter Reading (Form GLIDEPATH-METER / ILS-GP) ───
        // Seventh CNSD module — "Meter Reading Glide Path".
        Route::prefix('cnsd/glidepath-meter')->group(function () {
            Route::get('/template', [CnsdGlidepathMeterController::class, 'template']);
            Route::get('/years',    [CnsdGlidepathMeterController::class, 'years']);
            Route::get('/',         [CnsdGlidepathMeterController::class, 'index']);
            Route::post('/', [CnsdGlidepathMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Teknisi CNSD');
            Route::get('/{id}',       [CnsdGlidepathMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',       [CnsdGlidepathMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdGlidepathMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}',    [CnsdGlidepathMeterController::class, 'destroy'])
                ->whereNumber('id')->middleware('role:Admin,Manager Teknik');
        });

        // ─── CNSD Localizer Meter Reading (Form LOCALIZER-METER / ILS-LLZ) ───
        // Eighth CNSD module — "Meter Reading Localizer".
        Route::prefix('cnsd/localizer-meter')->group(function () {
            Route::get('/template', [CnsdLocalizerMeterController::class, 'template']);
            Route::get('/years',    [CnsdLocalizerMeterController::class, 'years']);
            Route::get('/',         [CnsdLocalizerMeterController::class, 'index']);
            Route::post('/', [CnsdLocalizerMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Teknisi CNSD');
            Route::get('/{id}',       [CnsdLocalizerMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',       [CnsdLocalizerMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdLocalizerMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}',    [CnsdLocalizerMeterController::class, 'destroy'])
                ->whereNumber('id')->middleware('role:Admin,Manager Teknik');
        });

        // ─── CNSD T-DME Meter Reading (Form TDME-METER / FORM N-5) ───
        // Ninth CNSD module — "Meter Reading T-DME".
        Route::prefix('cnsd/tdme-meter')->group(function () {
            Route::get('/template', [CnsdTdmeMeterController::class, 'template']);
            Route::get('/years',    [CnsdTdmeMeterController::class, 'years']);
            Route::get('/',         [CnsdTdmeMeterController::class, 'index']);
            Route::post('/', [CnsdTdmeMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Teknisi CNSD');
            Route::get('/{id}',       [CnsdTdmeMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',       [CnsdTdmeMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdTdmeMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}',    [CnsdTdmeMeterController::class, 'destroy'])
                ->whereNumber('id')->middleware('role:Admin,Manager Teknik');
        });

        // ─── CNSD DVOR Meter Reading (Form DVOR-METER / FORM N-5) ───
        // Tenth CNSD module — "Meter Reading DVOR".
        Route::prefix('cnsd/dvor-meter')->group(function () {
            Route::get('/template', [CnsdDvorMeterController::class, 'template']);
            Route::get('/years',    [CnsdDvorMeterController::class, 'years']);
            Route::get('/',         [CnsdDvorMeterController::class, 'index']);
            Route::post('/', [CnsdDvorMeterController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Teknisi CNSD');
            Route::get('/{id}',       [CnsdDvorMeterController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',       [CnsdDvorMeterController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [CnsdDvorMeterController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}',    [CnsdDvorMeterController::class, 'destroy'])
                ->whereNumber('id')->middleware('role:Admin,Manager Teknik');
        });

        // ─── TFP Performance Check AOB Lantai Ground ───────────
        Route::prefix('tfp/aob-ground')->group(function () {
            Route::get('/template', [TfpAobGroundController::class, 'template']);
            Route::get('/years',    [TfpAobGroundController::class, 'years']);
            Route::get('/',         [TfpAobGroundController::class, 'index']);
            Route::post('/', [TfpAobGroundController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor TFP,Teknisi TFP');
            Route::get('/{id}',     [TfpAobGroundController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [TfpAobGroundController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [TfpAobGroundController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [TfpAobGroundController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik');
        });

        // ─── TFP Performance Check AOB Lantai 1 & 2 ───────────
        Route::prefix('tfp/aob-lt12')->group(function () {
            Route::get('/template', [TfpAobLt12Controller::class, 'template']);
            Route::get('/years',    [TfpAobLt12Controller::class, 'years']);
            Route::get('/',         [TfpAobLt12Controller::class, 'index']);
            Route::post('/', [TfpAobLt12Controller::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor TFP,Teknisi TFP');
            Route::get('/{id}',     [TfpAobLt12Controller::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [TfpAobLt12Controller::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [TfpAobLt12Controller::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [TfpAobLt12Controller::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik');
        });

        // ─── TFP Performance Check Transmitter TX ──────────────
        Route::prefix('tfp/transmitter-tx')->group(function () {
            Route::get('/template', [TfpTransmitterTxController::class, 'template']);
            Route::get('/years',    [TfpTransmitterTxController::class, 'years']);
            Route::get('/',         [TfpTransmitterTxController::class, 'index']);
            Route::post('/', [TfpTransmitterTxController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor TFP,Teknisi TFP');
            Route::get('/{id}',     [TfpTransmitterTxController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [TfpTransmitterTxController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [TfpTransmitterTxController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [TfpTransmitterTxController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik');
        });

        // ─── TFP Performance Check Gedung Tower ────────────────
        Route::prefix('tfp/tower')->group(function () {
            Route::get('/template', [TfpTowerController::class, 'template']);
            Route::get('/years',    [TfpTowerController::class, 'years']);
            Route::get('/',         [TfpTowerController::class, 'index']);
            Route::post('/', [TfpTowerController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor TFP,Teknisi TFP');
            Route::get('/{id}',     [TfpTowerController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [TfpTowerController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [TfpTowerController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [TfpTowerController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik');
        });

        // ─── TFP Performance Check Gedung Radar ────────────────────
        Route::prefix('tfp/radar')->group(function () {
            Route::get('/template', [TfpRadarController::class, 'template']);
            Route::get('/years',    [TfpRadarController::class, 'years']);
            Route::get('/',         [TfpRadarController::class, 'index']);
            Route::post('/', [TfpRadarController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor TFP,Teknisi TFP');
            Route::get('/{id}',     [TfpRadarController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [TfpRadarController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [TfpRadarController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [TfpRadarController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik');
        });

        // ─── TFP Performance Check Gedung DVOR (VOR) ───────────────
        Route::prefix('tfp/dvor')->group(function () {
            Route::get('/template', [TfpDvorController::class, 'template']);
            Route::get('/years',    [TfpDvorController::class, 'years']);
            Route::get('/',         [TfpDvorController::class, 'index']);
            Route::post('/', [TfpDvorController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor TFP,Teknisi TFP');
            Route::get('/{id}',     [TfpDvorController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [TfpDvorController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [TfpDvorController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [TfpDvorController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik');
        });

        // ─── TFP Performance Check Gedung Localizer ─────────────────
        Route::prefix('tfp/localizer')->group(function () {
            Route::get('/template', [TfpLocalizerController::class, 'template']);
            Route::get('/years',    [TfpLocalizerController::class, 'years']);
            Route::get('/',         [TfpLocalizerController::class, 'index']);
            Route::post('/', [TfpLocalizerController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor TFP,Teknisi TFP');
            Route::get('/{id}',     [TfpLocalizerController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [TfpLocalizerController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [TfpLocalizerController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [TfpLocalizerController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik');
        });

        // ─── TFP Performance Check Gedung Glide Path ────────────────
        Route::prefix('tfp/glidepath')->group(function () {
            Route::get('/template', [TfpGlidepathController::class, 'template']);
            Route::get('/years',    [TfpGlidepathController::class, 'years']);
            Route::get('/',         [TfpGlidepathController::class, 'index']);
            Route::post('/', [TfpGlidepathController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor TFP,Teknisi TFP');
            Route::get('/{id}',     [TfpGlidepathController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [TfpGlidepathController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [TfpGlidepathController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [TfpGlidepathController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik');
        });

        // ─── Grounding Report ───────────────────────────────────────
        Route::prefix('grounding/reports')->group(function () {
            Route::get('/template', [GroundingReportController::class, 'template']);
            Route::get('/years',    [GroundingReportController::class, 'years']);
            Route::get('/',         [GroundingReportController::class, 'index']);
            Route::post('/', [GroundingReportController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor TFP,Teknisi TFP');
            Route::get('/{id}',     [GroundingReportController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [GroundingReportController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [GroundingReportController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [GroundingReportController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik');
        });

        // ─── Ground Check ADC ───────────────────────────────────────
        Route::prefix('ground-check/adc')->group(function () {
            Route::get('/template', [GroundCheckAdcController::class, 'template']);
            Route::get('/years',    [GroundCheckAdcController::class, 'years']);
            Route::get('/',         [GroundCheckAdcController::class, 'index']);
            Route::post('/', [GroundCheckAdcController::class, 'store'])
                ->middleware('role:Admin,Manager Teknik,Supervisor CNSD,Teknisi CNSD');
            Route::get('/{id}',     [GroundCheckAdcController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',     [GroundCheckAdcController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/sign', [GroundCheckAdcController::class, 'sign'])->whereNumber('id');
            Route::delete('/{id}', [GroundCheckAdcController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('role:Admin,Manager Teknik');
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
