<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ground_check_dvor_records', function (Blueprint $table) {
            $table->id();
            $table->string('form_number')->unique();
            $table->string('form_type')->default('GC-DVOR');
            $table->string('report_month')->nullable();
            $table->string('airport')->default('JUANDA SURABAYA');
            $table->string('equipment_name')->default('DVOR');
            $table->string('equipment_location')->default('SHELTER VOR');
            $table->text('equipment_function')->nullable();
            $table->text('technical_data')->nullable();
            // DVOR identification call sign (e.g. "SBR")
            $table->string('identification')->nullable();
            $table->text('last_calibration')->nullable();

            // Form 1 (Ground Check VOR — bearing table) metadata
            $table->string('vor_equipment_name')->default('DVOR AWA VRB 52 D');
            $table->string('vor_frequency')->default('113.4 MHZ');
            $table->string('vor_station')->default('SBR');

            // Form 2 (Error Curve) metadata
            $table->string('curve_organization')->default('PERUM LPPNPI KANTOR CABANG BANDARA JUANDA SURABAYA');

            // Form 4 (NAV analyzer / PIR Rohde & Schwarz) metadata
            $table->string('nav_analyzer_title')->default('GROUND CHECK DVOR DENGAN PIR ROHDE & SCHWARZ');
            $table->text('note')->nullable(); // bottom note on Form 4

            $table->date('date');
            $table->string('time_filled')->nullable();
            $table->string('day_name')->nullable();
            $table->string('shift_type');
            $table->string('status')->default('ongoing');

            // Manager Teknik (footer label: "MANAGER TEKNIK")
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
            CREATE UNIQUE INDEX ground_check_dvor_records_unique_form_per_shift
            ON ground_check_dvor_records (form_type, date, shift_type)
            WHERE deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('ground_check_dvor_records');
    }
};
