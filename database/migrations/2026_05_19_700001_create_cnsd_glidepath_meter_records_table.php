<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cnsd_glidepath_meter_records', function (Blueprint $table) {
            $table->id();

            // Form metadata
            $table->string('form_number')->unique();
            $table->string('form_type')->default('GLIDEPATH-METER');
            $table->string('facility')->default('GLIDEPATH');
            $table->string('form_code')->default('ILS-GP');

            // Equipment info
            $table->string('merk')->nullable();
            $table->string('type')->nullable();
            $table->string('serial_number')->nullable();

            // Shift context
            $table->date('date');
            $table->string('shift_type');
            $table->string('day_name')->nullable();
            $table->string('time_filled')->nullable();
            $table->string('location')->default('Kantor Cabang Surabaya');

            // Status
            $table->string('status')->default('ongoing');

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

            // Creator
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // Partial unique index: one active record per (form_type, facility, date, shift_type)
        DB::statement(
            'CREATE UNIQUE INDEX cnsd_glidepath_meter_records_unique_form_per_shift
             ON cnsd_glidepath_meter_records (form_type, facility, date, shift_type)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_glidepath_meter_records');
    }
};
