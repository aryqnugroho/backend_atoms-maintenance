<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add delegation audit trail fields to all signature-bearing tables.
 *
 * These fields record WHO actually signed (the delegate), separate from the
 * target signer name. This allows role-based delegation while preserving
 * the original assignment.
 *
 * Fields added per signature slot:
 *   - *_signed_by_name  (string, nullable) — actual signer's name
 *   - *_signed_by_role  (string, nullable) — actual signer's role at sign time
 *
 * Note: *_signed_by (user ID) already exists on most tables.
 * For technician rows (separate tables), we add signed_by_name and signed_by_role.
 */
return new class extends Migration {
    public function up(): void
    {
        // ─── Work Orders ───────────────────────────────────────
        Schema::table('work_orders', function (Blueprint $table) {
            $table->string('mt_signed_by_name', 120)->nullable()->after('mt_signed_at');
            $table->string('mt_signed_by_role', 50)->nullable()->after('mt_signed_by_name');
            $table->string('supervisor_signed_by_name', 120)->nullable()->after('supervisor_signed_at');
            $table->string('supervisor_signed_by_role', 50)->nullable()->after('supervisor_signed_by_name');
            $table->string('technician_signed_by_name', 120)->nullable()->after('technician_signed_at');
            $table->string('technician_signed_by_role', 50)->nullable()->after('technician_signed_by_name');
        });

        // ─── Grounding Report Records ──────────────────────────
        Schema::table('grounding_report_records', function (Blueprint $table) {
            $table->string('manager_signed_by_name', 120)->nullable()->after('manager_signed_at');
            $table->string('manager_signed_by_role', 50)->nullable()->after('manager_signed_by_name');
            $table->string('supervisor_signed_by_name', 120)->nullable()->after('supervisor_signed_at');
            $table->string('supervisor_signed_by_role', 50)->nullable()->after('supervisor_signed_by_name');
        });

        // ─── Grounding Report Technicians ──────────────────────
        Schema::table('grounding_report_technicians', function (Blueprint $table) {
            $table->string('technician_signed_by_name', 120)->nullable()->after('technician_signed_at');
            $table->string('technician_signed_by_role', 50)->nullable()->after('technician_signed_by_name');
        });

        // ─── Reporting Damage Reports ──────────────────────────
        Schema::table('reporting_damage_reports', function (Blueprint $table) {
            $table->string('manager_signed_by_name', 120)->nullable()->after('manager_signed_at');
            $table->string('manager_signed_by_role', 50)->nullable()->after('manager_signed_by_name');
        });

        // ─── Reporting Damage Repairers ────────────────────────
        Schema::table('reporting_damage_repairers', function (Blueprint $table) {
            $table->string('signed_by_name', 120)->nullable()->after('signed_at');
            $table->string('signed_by_role', 50)->nullable()->after('signed_by_name');
        });

        // ─── CNSD Records (all modules share same pattern) ─────
        $cnsdRecordTables = [
            'cnsd_readiness_records',
            'cnsd_radar_meter_records',
            'cnsd_recorder_meter_records',
            'cnsd_amsc_meter_records',
            'cnsd_transmitter_meter_records',
            'cnsd_receiver_meter_records',
        ];

        foreach ($cnsdRecordTables as $tbl) {
            if (Schema::hasTable($tbl)) {
                Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                    if (!Schema::hasColumn($tbl, 'manager_signed_by_name')) {
                        $table->string('manager_signed_by_name', 120)->nullable();
                        $table->string('manager_signed_by_role', 50)->nullable();
                    }
                    if (!Schema::hasColumn($tbl, 'supervisor_signed_by_name')) {
                        $table->string('supervisor_signed_by_name', 120)->nullable();
                        $table->string('supervisor_signed_by_role', 50)->nullable();
                    }
                });
            }
        }

        // ─── CNSD Technician tables ────────────────────────────
        $cnsdTechTables = [
            'cnsd_readiness_technicians',
            'cnsd_radar_meter_technicians',
            'cnsd_recorder_meter_technicians',
            'cnsd_amsc_meter_technicians',
            'cnsd_transmitter_meter_technicians',
            'cnsd_receiver_meter_technicians',
        ];

        foreach ($cnsdTechTables as $tbl) {
            if (Schema::hasTable($tbl)) {
                Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                    if (!Schema::hasColumn($tbl, 'technician_signed_by_name')) {
                        $table->string('technician_signed_by_name', 120)->nullable();
                        $table->string('technician_signed_by_role', 50)->nullable();
                    }
                });
            }
        }

        // ─── TFP Records ──────────────────────────────────────
        $tfpRecordTables = [
            'tfp_aob_ground_records',
            'tfp_aob_lt12_records',
            'tfp_transmitter_tx_records',
            'tfp_tower_records',
        ];

        foreach ($tfpRecordTables as $tbl) {
            if (Schema::hasTable($tbl)) {
                Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                    if (!Schema::hasColumn($tbl, 'manager_signed_by_name')) {
                        $table->string('manager_signed_by_name', 120)->nullable();
                        $table->string('manager_signed_by_role', 50)->nullable();
                    }
                    if (!Schema::hasColumn($tbl, 'supervisor_signed_by_name')) {
                        $table->string('supervisor_signed_by_name', 120)->nullable();
                        $table->string('supervisor_signed_by_role', 50)->nullable();
                    }
                });
            }
        }

        // ─── TFP Technician tables ────────────────────────────
        $tfpTechTables = [
            'tfp_aob_ground_technicians',
            'tfp_aob_lt12_technicians',
            'tfp_transmitter_tx_technicians',
            'tfp_tower_technicians',
        ];

        foreach ($tfpTechTables as $tbl) {
            if (Schema::hasTable($tbl)) {
                Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                    if (!Schema::hasColumn($tbl, 'technician_signed_by_name')) {
                        $table->string('technician_signed_by_name', 120)->nullable();
                        $table->string('technician_signed_by_role', 50)->nullable();
                    }
                });
            }
        }

        // ─── Ground Check ADC ─────────────────────────────────
        if (Schema::hasTable('ground_check_adc_records')) {
            Schema::table('ground_check_adc_records', function (Blueprint $table) {
                if (!Schema::hasColumn('ground_check_adc_records', 'manager_signed_by_name')) {
                    $table->string('manager_signed_by_name', 120)->nullable();
                    $table->string('manager_signed_by_role', 50)->nullable();
                }
                if (!Schema::hasColumn('ground_check_adc_records', 'supervisor_signed_by_name')) {
                    $table->string('supervisor_signed_by_name', 120)->nullable();
                    $table->string('supervisor_signed_by_role', 50)->nullable();
                }
            });
        }

        if (Schema::hasTable('ground_check_adc_technicians')) {
            Schema::table('ground_check_adc_technicians', function (Blueprint $table) {
                if (!Schema::hasColumn('ground_check_adc_technicians', 'technician_signed_by_name')) {
                    $table->string('technician_signed_by_name', 120)->nullable();
                    $table->string('technician_signed_by_role', 50)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Work Orders
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn([
                'mt_signed_by_name', 'mt_signed_by_role',
                'supervisor_signed_by_name', 'supervisor_signed_by_role',
                'technician_signed_by_name', 'technician_signed_by_role',
            ]);
        });

        Schema::table('grounding_report_records', function (Blueprint $table) {
            $table->dropColumn(['manager_signed_by_name', 'manager_signed_by_role', 'supervisor_signed_by_name', 'supervisor_signed_by_role']);
        });

        Schema::table('grounding_report_technicians', function (Blueprint $table) {
            $table->dropColumn(['technician_signed_by_name', 'technician_signed_by_role']);
        });

        Schema::table('reporting_damage_reports', function (Blueprint $table) {
            $table->dropColumn(['manager_signed_by_name', 'manager_signed_by_role']);
        });

        Schema::table('reporting_damage_repairers', function (Blueprint $table) {
            $table->dropColumn(['signed_by_name', 'signed_by_role']);
        });
    }
};
