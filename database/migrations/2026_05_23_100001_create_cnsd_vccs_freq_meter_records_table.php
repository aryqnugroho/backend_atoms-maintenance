<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cnsd_vccs_freq_meter_records — header for CNSD VCCS Frequentis Meter Reading.
 *
 * Distinct from cnsd_vccs_meter_records (VCCS LES, CNSD-014) so each brand has
 * its own data namespace, paper-form defaults, and partial unique constraint.
 *
 * Defaults: merk=FREQUENTIS, type=null, sn=null per the paper-form header.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_vccs_freq_meter_records', function (Blueprint $table) {
            $table->id();

            $table->string('form_number', 80)->unique();
            $table->string('form_type', 30)->default('VCCS-FREQ-METER');
            $table->string('facility', 20)->default('VCCS');

            $table->date('date');
            $table->string('shift_type', 10);
            $table->string('day_name', 20)->nullable();
            $table->string('time_filled', 10)->nullable();
            $table->string('location', 100)->default('Kantor Cabang Surabaya / Cabang Surabaya');

            // Equipment metadata (Frequentis-specific defaults per paper form)
            $table->string('merk', 60)->default('FREQUENTIS');
            $table->string('type', 60)->nullable();
            $table->string('serial_number', 60)->nullable();

            $table->string('status', 20)->default('ongoing');

            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('manager_name')->nullable();
            $table->longText('manager_signature')->nullable();
            $table->unsignedBigInteger('manager_signed_by')->nullable();
            $table->timestamp('manager_signed_at')->nullable();

            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->string('supervisor_name')->nullable();
            $table->longText('supervisor_signature')->nullable();
            $table->unsignedBigInteger('supervisor_signed_by')->nullable();
            $table->timestamp('supervisor_signed_at')->nullable();

            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Explicit short FK names — auto-gen would exceed PG 63-char limit
            $table->foreign('manager_id',         'cnsd_vccs_freq_mgr_fk')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('supervisor_id',      'cnsd_vccs_freq_spv_fk')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('manager_signed_by',  'cnsd_vccs_freq_mgr_sign_fk')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('supervisor_signed_by','cnsd_vccs_freq_spv_sign_fk')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('created_by_id',      'cnsd_vccs_freq_creator_fk')->references('id')->on('local_users')->nullOnDelete();

            $table->index(['date', 'shift_type']);
            $table->index(['form_type', 'date']);
            $table->index('status');
            $table->index('facility');
        });

        \Illuminate\Support\Facades\DB::statement(
            'CREATE UNIQUE INDEX cnsd_vccs_freq_meter_records_unique_form_per_shift '
            . 'ON cnsd_vccs_freq_meter_records (form_type, facility, date, shift_type) '
            . 'WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement(
            'DROP INDEX IF EXISTS cnsd_vccs_freq_meter_records_unique_form_per_shift'
        );
        Schema::dropIfExists('cnsd_vccs_freq_meter_records');
    }
};
