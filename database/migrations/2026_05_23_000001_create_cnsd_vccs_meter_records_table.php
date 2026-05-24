<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cnsd_vccs_meter_records — header table for CNSD VCCS Meter Reading forms.
 *
 * Mirrors the cnsd_amsc_meter_records pattern but lives in its own table so
 * that VCCS LES domain-specific defaults (merk "LES", type null, sn null) and
 * future schema additions stay isolated from AMSC / Radar / Recorder / EQ-1.
 *
 * Scope: only Form VCCS-METER ("METER READING — VCCS").
 *   - One active record per (form_type, facility, date, shift_type) — enforced
 *     by a partial unique index that ignores soft-deleted rows.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_vccs_meter_records', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('form_number', 80)->unique();
            $table->string('form_type', 30)->default('VCCS-METER');
            $table->string('facility', 20)->default('VCCS');

            // Time + place
            $table->date('date');
            $table->string('shift_type', 10); // pagi | siang | malam
            $table->string('day_name', 20)->nullable();
            $table->string('time_filled', 10)->nullable();
            $table->string('location', 100)->default('Kantor Cabang Surabaya / Cabang Surabaya');

            // Equipment metadata (VCCS-specific — LES is the default brand per paper form)
            $table->string('merk', 60)->default('LES');
            $table->string('type', 60)->nullable();
            $table->string('serial_number', 60)->nullable();

            // Lifecycle
            $table->string('status', 20)->default('ongoing'); // ongoing | on_hold | completed

            // Manager Teknik (nullable)
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('manager_name')->nullable();
            $table->longText('manager_signature')->nullable();
            $table->unsignedBigInteger('manager_signed_by')->nullable();
            $table->timestamp('manager_signed_at')->nullable();

            // Supervisor CNSD (nullable)
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->string('supervisor_name')->nullable();
            $table->longText('supervisor_signature')->nullable();
            $table->unsignedBigInteger('supervisor_signed_by')->nullable();
            $table->timestamp('supervisor_signed_at')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('manager_id')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('supervisor_id')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('manager_signed_by')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('supervisor_signed_by')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('created_by_id')->references('id')->on('local_users')->nullOnDelete();

            $table->index(['date', 'shift_type']);
            $table->index(['form_type', 'date']);
            $table->index('status');
            $table->index('facility');
        });

        \Illuminate\Support\Facades\DB::statement(
            'CREATE UNIQUE INDEX cnsd_vccs_meter_records_unique_form_per_shift '
            . 'ON cnsd_vccs_meter_records (form_type, facility, date, shift_type) '
            . 'WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement(
            'DROP INDEX IF EXISTS cnsd_vccs_meter_records_unique_form_per_shift'
        );
        Schema::dropIfExists('cnsd_vccs_meter_records');
    }
};
