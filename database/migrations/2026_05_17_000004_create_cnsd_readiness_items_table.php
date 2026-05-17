<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cnsd_readiness_items — flexible per-item rows for any CNSD readiness form.
 *
 * Why generic columns (kondisi_operasional_1 / kondisi_operasional_2) instead
 * of named columns: each section uses different column headers
 * (e.g. KOMUNIKASI uses "SERVER AKTIF" / "DUAL STATE", RADIO uses
 * "DUAL STATUS" / "FREQUENCY", NAVIGASI uses "TX OPERASI" / "DUAL STATE").
 * The frontend EQ-1 template renders the right header per section while the DB
 * stays form-agnostic — making it easy to plug in other CNSD forms later.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_readiness_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('readiness_record_id')
                ->constrained('cnsd_readiness_records')
                ->cascadeOnDelete();

            // Hierarchy
            $table->string('section_name', 60); // e.g. KOMUNIKASI PENERBANGAN
            $table->string('item_number', 10)->nullable(); // e.g. "1", "2.1"
            $table->string('equipment_name'); // e.g. VCCS MERA FREQUENTIS, CDU
            $table->string('sub_equipment_name', 60)->nullable(); // e.g. PRIMARY, SECONDARY

            // Inputs (free-form strings so the form supports both dropdown and text)
            $table->string('status_peralatan', 60)->nullable(); // NORMAL / TIDAK NORMAL
            $table->string('kondisi_operasional_1', 80)->nullable(); // SERVER AKTIF / DUAL STATUS / TX OPERASI / CHANNEL AKTIF / SERVER STATE
            $table->string('kondisi_operasional_2', 80)->nullable(); // DUAL STATE / FREQUENCY / WORKSTATION STATE
            $table->string('keterangan', 255)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['readiness_record_id', 'sort_order']);
            $table->index(['readiness_record_id', 'section_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_readiness_items');
    }
};
