<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the four legacy CNSD scaffold tables that were created with only
 * id + timestamps. They were never wired up and are being replaced by the
 * real CNSD Equipment Readiness Form EQ-1 schema (cnsd_readiness_*).
 *
 * Old tables removed here:
 *   - cnsd_categories
 *   - cnsd_meter_readings
 *   - cnsd_sections
 *   - cnsd_section_rows
 *
 * Down recreates the empty scaffolds so a rollback returns the schema
 * exactly to its pre-2026-05-17 state.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('cnsd_section_rows');
        Schema::dropIfExists('cnsd_meter_readings');
        Schema::dropIfExists('cnsd_sections');
        Schema::dropIfExists('cnsd_categories');
    }

    public function down(): void
    {
        Schema::create('cnsd_categories', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('cnsd_meter_readings', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('cnsd_sections', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('cnsd_section_rows', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};
