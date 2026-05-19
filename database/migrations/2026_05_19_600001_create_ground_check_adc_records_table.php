<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ground_check_adc_records', function (Blueprint $table) {
            $table->id();
            $table->string('form_number')->unique();
            $table->string('form_type')->default('GC-ADC');
            $table->string('report_month')->nullable();
            $table->string('airport')->default('JUANDA SURABAYA');
            $table->string('equipment_name')->default('ADC');
            $table->string('equipment_location')->default('GEDUNG TX, RX DAN TOWER');
            $table->text('equipment_function')->nullable();
            $table->text('technical_data')->nullable();
            $table->text('last_calibration')->nullable();
            $table->date('date');
            $table->string('time_filled')->nullable();
            $table->string('day_name')->nullable();
            $table->string('shift_type');
            $table->string('status')->default('ongoing');

            // Manager Teknik
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('manager_name')->nullable();
            $table->longText('manager_signature')->nullable();
            $table->unsignedBigInteger('manager_signed_by')->nullable();
            $table->timestamp('manager_signed_at')->nullable();

            // Supervisor TFP
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

        // Partial unique index: prevent duplicate active records per date+shift
        DB::statement("
            CREATE UNIQUE INDEX ground_check_adc_records_unique_form_per_shift
            ON ground_check_adc_records (form_type, date, shift_type)
            WHERE deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('ground_check_adc_records');
    }
};
