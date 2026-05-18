<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cnsd_transmitter_meter_items — item rows for Transmitter Meter Reading.
 *
 * Covers both Section I (TRANSMITTER / TX RADIO) and Section II (LINGKUNGAN KERJA).
 * Each row represents one TX line or one environment check item.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_transmitter_meter_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transmitter_meter_record_id');

            // Section info
            $table->string('section_code', 10)->nullable();   // '1' or '2'
            $table->string('section_name', 100);

            // Group info (for TX Radio: Ground, ADC, CDU, APP, TMA West, TMA East, ER Makassar, ATIS, Back Up Radio)
            $table->integer('group_number')->nullable();
            $table->string('group_name', 100)->nullable();

            // Frequency/item identification
            $table->string('frequency_label', 100)->nullable();
            $table->string('merk', 60)->nullable();
            $table->string('tx_label', 30)->nullable();       // 'TX 1', 'TX 2', or null for env items

            // Values
            $table->string('status_value', 30)->nullable();   // On Air, STBY, Online, Offline
            $table->string('power_output', 60)->nullable();
            $table->string('modulasi', 60)->nullable();
            $table->string('keterangan')->nullable();

            // For environment items
            $table->string('nominal', 60)->nullable();        // <22°C, √
            $table->string('hasil', 60)->nullable();          // result value

            // Row type flags
            $table->boolean('is_header')->default(false);     // group header row (green)
            $table->boolean('is_blocked')->default(false);    // disabled/grey cell (Back Up Radio status)
            $table->string('block_reason', 60)->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('transmitter_meter_record_id', 'cnsd_tx_meter_item_record_fk')
                ->references('id')
                ->on('cnsd_transmitter_meter_records')
                ->cascadeOnDelete();

            $table->index('transmitter_meter_record_id', 'cnsd_tx_meter_item_record_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_transmitter_meter_items');
    }
};
