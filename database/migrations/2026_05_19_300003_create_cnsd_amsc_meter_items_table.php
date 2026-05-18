<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cnsd_amsc_meter_items — item rows for CNSD AMSC Meter Reading forms.
 *
 * Columns are designed to accommodate all 4 sections of the AMSC form:
 *   - FRONT PANEL: uses hasil_a + hasil_b (A/B columns) + keterangan
 *   - POWER SUPPLY UNIT: uses hasil + keterangan
 *   - CHANNEL AMSC: uses address + status_value + cct + keterangan
 *   - LINGKUNGAN KERJA: uses hasil + keterangan
 *
 * is_blocked / block_reason reserved for future U/S items if needed.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_amsc_meter_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('amsc_meter_record_id');

            // Section / group classification
            $table->string('section_code', 10)->nullable();
            $table->string('section_name', 100);
            $table->integer('group_number')->nullable();
            $table->string('group_name', 100)->nullable();
            $table->string('item_number', 30)->nullable();
            $table->string('item_name', 200);

            // Standard / expected value from paper form
            $table->string('nominal', 100)->nullable();

            // FRONT PANEL: dual result columns (A / B)
            $table->string('hasil_a', 100)->nullable();
            $table->string('hasil_b', 100)->nullable();

            // POWER SUPPLY UNIT + LINGKUNGAN KERJA: single result column
            $table->string('hasil', 100)->nullable();

            // CHANNEL AMSC: address, status, cct
            $table->string('address', 100)->nullable();
            $table->string('status_value', 50)->nullable();
            $table->string('cct', 100)->nullable();

            // Common
            $table->text('keterangan')->nullable();

            // U/S blocking (reserved)
            $table->boolean('is_blocked')->default(false);
            $table->string('block_reason', 50)->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('amsc_meter_record_id')
                ->references('id')
                ->on('cnsd_amsc_meter_records')
                ->cascadeOnDelete();

            $table->index('amsc_meter_record_id');
            $table->index('section_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_amsc_meter_items');
    }
};
