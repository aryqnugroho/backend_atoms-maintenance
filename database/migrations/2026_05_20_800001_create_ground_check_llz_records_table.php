<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ground_check_llz_records', function (Blueprint $table) {
            $table->id();
            $table->string('form_number')->unique();
            $table->string('form_type')->default('GC-LLZ');
            $table->string('report_month')->nullable();
            $table->string('airport')->default('JUANDA SURABAYA');
            $table->string('equipment_name')->default('ILS LOCALIZER');
            $table->string('equipment_location')->default('SHELTER LOCALIZER');
            $table->text('equipment_function')->nullable();
            $table->text('technical_data')->nullable();
            // LLZ-specific: identification call sign (e.g. "ISBY")
            $table->string('identification')->nullable();
            $table->text('last_calibration')->nullable();

            // Form 2 metadata (Performance Curve page)
            $table->string('curve_facility')->default('Instrument Landing System');
            $table->string('curve_merk')->nullable();         // e.g. "Mopiens 520"
            $table->string('curve_ident_freq')->nullable();   // e.g. "ISBY - 110.10 MHz"
            $table->string('curve_jarak_ant')->default('300 M'); // "JARAK DARI ANT"

            $table->date('date');
            $table->string('time_filled')->nullable();
            $table->string('day_name')->nullable();
            $table->string('shift_type');
            $table->string('status')->default('ongoing');

            // Manager Teknik (PH MANAGER TEKNIK 1)
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('manager_name')->nullable();
            $table->longText('manager_signature')->nullable();
            $table->unsignedBigInteger('manager_signed_by')->nullable();
            $table->timestamp('manager_signed_at')->nullable();

            // Supervisor CNSD
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

        DB::statement("
            CREATE UNIQUE INDEX ground_check_llz_records_unique_form_per_shift
            ON ground_check_llz_records (form_type, date, shift_type)
            WHERE deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('ground_check_llz_records');
    }
};
