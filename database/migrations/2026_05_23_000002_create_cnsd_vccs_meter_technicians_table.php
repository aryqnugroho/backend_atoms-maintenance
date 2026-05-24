<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cnsd_vccs_meter_technicians — per-row technician snapshot + signature
 * for CNSD VCCS Meter Reading forms. Mirrors cnsd_amsc_meter_technicians.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_vccs_meter_technicians', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vccs_meter_record_id');
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->string('technician_name');
            $table->longText('technician_signature')->nullable();
            $table->unsignedBigInteger('technician_signed_by')->nullable();
            $table->timestamp('technician_signed_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('vccs_meter_record_id')
                ->references('id')
                ->on('cnsd_vccs_meter_records')
                ->cascadeOnDelete();

            $table->foreign('technician_id')
                ->references('id')
                ->on('local_users')
                ->nullOnDelete();

            $table->foreign('technician_signed_by')
                ->references('id')
                ->on('local_users')
                ->nullOnDelete();

            $table->index('vccs_meter_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_vccs_meter_technicians');
    }
};
