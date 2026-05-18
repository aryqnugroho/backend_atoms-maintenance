<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cnsd_transmitter_meter_technicians — per-row technician snapshot + signature.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_transmitter_meter_technicians', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transmitter_meter_record_id');
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->string('technician_name');
            $table->longText('technician_signature')->nullable();
            $table->unsignedBigInteger('technician_signed_by')->nullable();
            $table->timestamp('technician_signed_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('transmitter_meter_record_id', 'cnsd_tx_meter_tech_record_fk')
                ->references('id')
                ->on('cnsd_transmitter_meter_records')
                ->cascadeOnDelete();
            $table->foreign('technician_id', 'cnsd_tx_meter_tech_user_fk')
                ->references('id')
                ->on('local_users')
                ->nullOnDelete();
            $table->foreign('technician_signed_by', 'cnsd_tx_meter_tech_signer_fk')
                ->references('id')
                ->on('local_users')
                ->nullOnDelete();

            $table->index('transmitter_meter_record_id', 'cnsd_tx_meter_tech_record_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_transmitter_meter_technicians');
    }
};
