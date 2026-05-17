<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cnsd_radar_meter_records — header table for CNSD Radar Meter Reading forms.
 *
 * Mirrors the cnsd_readiness_records pattern but lives in its own table so that
 * Radar's domain-specific fields (merk, type, serial_number) stay clean and
 * EQ-1 readiness data is unaffected.
 *
 * Scope (Phase 4.2): only Form RADAR-METER ("METER READING — RADAR").
 *   - One record per (form_type, facility, date, shift_type) — uniqueness
 *     enforced via partial unique index that ignores soft-deleted rows.
 *   - Manager Teknik & Supervisor are nullable (resolved from rostering at
 *     create time). When the roster has no MT/SVP for the shift, the column
 *     stays null and that role's signature is not required.
 *   - Signature columns mirror EQ-1 readiness and use HasSignature trait.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_radar_meter_records', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('form_number', 80)->unique();
            $table->string('form_type', 30)->default('RADAR-METER');
            $table->string('facility', 20)->default('RADAR');

            // Time + place
            $table->date('date');
            $table->string('shift_type', 10); // pagi | siang | malam
            $table->string('location', 100)->default('CABANG SURABAYA');

            // Equipment metadata (radar specific)
            $table->string('merk', 60)->default('ELDIS');
            $table->string('type', 60)->default('MSSR-1 / RL2000');
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

            // Foreign keys to local_users (lazily resolved by LocalUserResolver)
            $table->foreign('manager_id')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('supervisor_id')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('manager_signed_by')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('supervisor_signed_by')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('created_by_id')->references('id')->on('local_users')->nullOnDelete();

            // Search / list filters
            $table->index(['date', 'shift_type']);
            $table->index(['form_type', 'date']);
            $table->index('status');
            $table->index('facility');
        });

        // PostgreSQL partial unique index — one *active* form per
        // (form_type, facility, date, shift_type). Soft-deleted rows excluded
        // so a record can be re-created after delete.
        \Illuminate\Support\Facades\DB::statement(
            'CREATE UNIQUE INDEX cnsd_radar_meter_records_unique_form_per_shift '
            . 'ON cnsd_radar_meter_records (form_type, facility, date, shift_type) '
            . 'WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement(
            'DROP INDEX IF EXISTS cnsd_radar_meter_records_unique_form_per_shift'
        );
        Schema::dropIfExists('cnsd_radar_meter_records');
    }
};
