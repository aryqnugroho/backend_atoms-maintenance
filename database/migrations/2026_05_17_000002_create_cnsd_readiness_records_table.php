<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cnsd_readiness_records — header table for CNSD Equipment Readiness forms.
 *
 * Scope (Phase 4 pilot): only Form EQ-1 ("Kesiapan Peralatan CNSD").
 *   - One record per (form_type, date, shift_type, facility) — uniqueness enforced
 *     via a partial unique index that ignores soft-deleted rows.
 *   - Manager Teknik & Supervisor are nullable (resolved from rostering at create
 *     time). When the roster has no MT/SVP for the shift, the column stays null.
 *   - Signature columns mirror Work Order conventions and use HasSignature trait.
 *
 * Future-proofing: form_type is a string (default 'EQ-1') so additional CNSD
 * readiness forms (e.g. 'RADAR-001', 'RECORDER-001') can reuse the same table.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_readiness_records', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('form_number', 80)->unique();
            $table->string('form_type', 30)->default('EQ-1');
            $table->string('facility', 20)->default('CNSD');

            // Time + place
            $table->date('date');
            $table->string('shift_type', 10); // pagi | siang | malam
            $table->string('location', 100)->default('CABANG SURABAYA');
            $table->string('room', 100)->nullable(); // e.g. "Main Equipment Room"

            // Lifecycle
            $table->string('status', 20)->default('ongoing'); // ongoing | on_hold | completed

            // Manager Teknik (nullable — may not be on duty)
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('manager_name')->nullable();
            $table->longText('manager_signature')->nullable();
            $table->unsignedBigInteger('manager_signed_by')->nullable();
            $table->timestamp('manager_signed_at')->nullable();

            // Supervisor CNSD (nullable — supervisor may not be on duty)
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

            // Foreign keys (use local_users.id, lazily resolved by LocalUserResolver)
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
        // (form_type, facility, date, shift_type). Soft-deleted rows are excluded
        // so a record can be re-created after delete.
        \Illuminate\Support\Facades\DB::statement(
            'CREATE UNIQUE INDEX cnsd_readiness_records_unique_form_per_shift '
            . 'ON cnsd_readiness_records (form_type, facility, date, shift_type) '
            . 'WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement(
            'DROP INDEX IF EXISTS cnsd_readiness_records_unique_form_per_shift'
        );
        Schema::dropIfExists('cnsd_readiness_records');
    }
};
