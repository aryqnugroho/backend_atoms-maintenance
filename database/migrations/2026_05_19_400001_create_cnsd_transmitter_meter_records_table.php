<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cnsd_transmitter_meter_records — header table for CNSD Transmitter Meter Reading forms.
 *
 * Mirrors the cnsd_amsc_meter_records pattern. Lives in its own table so that
 * Transmitter-specific fields stay isolated from other CNSD modules.
 *
 * Scope: only Form TRANSMITTER-METER ("METER READING — TRANSMITTER", FORM C-1).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_transmitter_meter_records', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('form_number', 80)->unique();
            $table->string('form_type', 30)->default('TRANSMITTER-METER');
            $table->string('facility', 20)->default('TRANSMITTER');
            $table->string('form_code', 20)->default('FORM C-1');

            // Time + place
            $table->date('date');
            $table->string('shift_type', 10); // pagi | siang | malam
            $table->string('day_name', 20)->nullable();
            $table->string('time_filled', 10)->nullable();
            $table->string('location', 100)->default('Kantor Cabang Surabaya');

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

            // Foreign keys to local_users
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
        // (form_type, facility, date, shift_type). Soft-deleted rows excluded.
        \Illuminate\Support\Facades\DB::statement(
            'CREATE UNIQUE INDEX cnsd_transmitter_meter_records_unique_form_per_shift '
            . 'ON cnsd_transmitter_meter_records (form_type, facility, date, shift_type) '
            . 'WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement(
            'DROP INDEX IF EXISTS cnsd_transmitter_meter_records_unique_form_per_shift'
        );
        Schema::dropIfExists('cnsd_transmitter_meter_records');
    }
};
