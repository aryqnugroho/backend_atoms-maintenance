<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cnsd_recorder_meter_items — flexible per-item rows for the Recorder Meter
 * Reading form.
 *
 * Each row represents one line on the Recorder Meter Reading paper form. Items
 * are seeded from CnsdRecorderMeterTemplate at create-time; users only update
 * the inputs (hasil_server_a/hasil_server_b for section A, hasil for section
 * B, keterangan).
 *
 * Why generic columns:
 *   - `hasil_server_a` / `hasil_server_b` cover the technical part of the form
 *     (Server A / Server B columns: A. PERALATAN).
 *   - `hasil` covers the Lingkungan Kerja section ("HASIL PEMERIKSAAN" column).
 *   - `nominal` is the official threshold value seeded from the template
 *     (e.g. "Normal / Alrm", "√ / -", "Ground Primary", "Max 22°C", "√").
 *   - `section_code` is the alphabet header on the form (A, B).
 *   - `group_number` / `group_name` capture sub-groupings inside section A
 *     (1: KVM, 2: SERVER, 3: POWER, 4: CHANNEL).
 *   - `is_blocked` flags U/S (Un-Serviceable) channels that are physically
 *     unavailable on the form. Blocked items render as a red strip with
 *     "U/S" label and have all input fields disabled.
 *   - `block_reason` carries the U/S reason ("U/S" by default).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_recorder_meter_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recorder_meter_record_id')
                ->constrained('cnsd_recorder_meter_records')
                ->cascadeOnDelete();

            // Hierarchy
            $table->string('section_code', 8)->nullable();   // 'A', 'B'
            $table->string('section_name', 80);              // 'PERALATAN', 'LINGKUNGAN KERJA'
            $table->unsignedTinyInteger('group_number')->nullable();
            $table->string('group_name', 80)->nullable();    // 'KVM', 'SERVER', 'POWER', 'CHANNEL'
            $table->string('item_number', 16)->nullable();   // visible numbering on paper (e.g. "Channel 7")
            $table->string('item_name', 200);                // 'All Status Indikator', 'Ground Primary', etc.

            // Standard / nominal value seeded from template
            $table->string('nominal', 120)->nullable();

            // User-filled fields
            // For section A (PERALATAN): hasil_server_a + hasil_server_b
            $table->string('hasil_server_a', 120)->nullable();
            $table->string('hasil_server_b', 120)->nullable();
            // For section B (LINGKUNGAN KERJA): hasil
            $table->string('hasil', 120)->nullable();
            $table->string('keterangan', 255)->nullable();

            // Blocked / U/S items
            $table->boolean('is_blocked')->default(false);
            $table->string('block_reason', 60)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['recorder_meter_record_id', 'sort_order']);
            $table->index(['recorder_meter_record_id', 'section_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_recorder_meter_items');
    }
};
