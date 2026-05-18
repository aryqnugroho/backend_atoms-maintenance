<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cnsd_amsc_meter_technicians — per-row technician snapshot + signature
 * for CNSD AMSC Meter Reading forms.
 *
 * Mirrors cnsd_recorder_meter_technicians pattern.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_amsc_meter_technicians', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('amsc_meter_record_id');
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->string('technician_name');
            $table->longText('technician_signature')->nullable();
            $table->unsignedBigInteger('technician_signed_by')->nullable();
            $table->timestamp('technician_signed_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('amsc_meter_record_id')
                ->references('id')
                ->on('cnsd_amsc_meter_records')
                ->cascadeOnDelete();

            $table->foreign('technician_id')
                ->references('id')
                ->on('local_users')
                ->nullOnDelete();

            $table->foreign('technician_signed_by')
                ->references('id')
                ->on('local_users')
                ->nullOnDelete();

            $table->index('amsc_meter_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_amsc_meter_technicians');
    }
};
