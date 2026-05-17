<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cnsd_radar_meter_items — flexible per-item rows for the Radar Meter Reading form.
 *
 * Each row represents one line on the Radar Meter Reading paper form. Items
 * are seeded from CnsdRadarMeterTemplate at create-time; users only update
 * the inputs (kondisi_teknis_tx1/tx2, hasil for environment, keterangan).
 *
 * Why generic columns:
 *   - `kondisi_teknis_tx1` / `kondisi_teknis_tx2` cover the technical part of
 *     the form (TX I / TX II columns: Termonitor di LCMS).
 *   - `hasil` covers the Lingkungan Kerja section ("HASIL" column on the form).
 *   - `standard` is the official threshold value seeded from the template
 *     (e.g. ">2500 W", "Green", "Max 22°C", "10 RPM / 15 RPM").
 *   - `section_code` is the alphabet header on the form (A, C). Section B
 *     ("Termonitor di RIM") is reserved for future expansion.
 *   - `group_number` / `group_name` capture sub-groupings inside section A
 *     (1: MSSR TRANSMITTER A/B, 2: SSR EXTRACTOR, 3: AILAN CH.A/CH.B,
 *     4: RADAR CONTROL).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_radar_meter_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('radar_meter_record_id')
                ->constrained('cnsd_radar_meter_records')
                ->cascadeOnDelete();

            // Hierarchy
            $table->string('section_code', 8)->nullable();   // 'A', 'C'
            $table->string('section_name', 80);              // 'TERMONITOR DI LCMS', 'LINGKUNGAN KERJA'
            $table->unsignedTinyInteger('group_number')->nullable();
            $table->string('group_name', 80)->nullable();    // 'MSSR TRANSMITTER A/B', etc.
            $table->string('item_number', 10)->nullable();   // visible numbering on paper
            $table->string('item_name', 200);                // 'Power SUM', 'GPS Receiver', etc.

            // Standard / threshold value seeded from template
            $table->string('standard', 120)->nullable();

            // User-filled fields
            // For section A (Termonitor): kondisi_teknis_tx1 + kondisi_teknis_tx2
            $table->string('kondisi_teknis_tx1', 120)->nullable();
            $table->string('kondisi_teknis_tx2', 120)->nullable();
            // For section C (Lingkungan Kerja): hasil
            $table->string('hasil', 120)->nullable();
            $table->string('keterangan', 255)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['radar_meter_record_id', 'sort_order']);
            $table->index(['radar_meter_record_id', 'section_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_radar_meter_items');
    }
};
