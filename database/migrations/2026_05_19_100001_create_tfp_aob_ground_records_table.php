<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tfp_aob_ground_records — header table for TFP Performance Check AOB Lantai Ground forms.
 *
 * One record per (form_type, date, shift_type) — uniqueness enforced via
 * partial unique index that ignores soft-deleted rows.
 *
 * Manager Teknik & Supervisor TFP are nullable (resolved from rostering at
 * create time). When the roster has no MT/SVP for the shift, the column
 * stays null and that role's signature is not required.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tfp_aob_ground_records', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('form_number', 80)->unique();
            $table->string('form_type', 30)->default('AOB-GROUND');

            // Time + place
            $table->date('date');
            $table->string('day_name', 20)->nullable();   // auto-filled from date (Indonesian)
            $table->string('time_filled', 10)->nullable(); // HH:MM, set at create time
            $table->string('shift_type', 10);              // pagi | siang | malam
            $table->string('location', 100)->default('AOB LANTAI GROUND');

            // Lifecycle
            $table->string('status', 20)->default('ongoing'); // ongoing | on_hold | completed

            // Manager Teknik (nullable)
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('manager_name')->nullable();
            $table->longText('manager_signature')->nullable();
            $table->unsignedBigInteger('manager_signed_by')->nullable();
            $table->timestamp('manager_signed_at')->nullable();

            // Supervisor TFP (nullable)
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
        });

        // PostgreSQL partial unique index — one *active* form per
        // (form_type, date, shift_type). Soft-deleted rows excluded
        // so a record can be re-created after delete.
        \Illuminate\Support\Facades\DB::statement(
            'CREATE UNIQUE INDEX tfp_aob_ground_records_unique_form_per_shift '
            . 'ON tfp_aob_ground_records (form_type, date, shift_type) '
            . 'WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement(
            'DROP INDEX IF EXISTS tfp_aob_ground_records_unique_form_per_shift'
        );
        Schema::dropIfExists('tfp_aob_ground_records');
    }
};
