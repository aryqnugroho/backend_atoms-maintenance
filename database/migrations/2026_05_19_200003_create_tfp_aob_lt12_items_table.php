<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tfp_aob_lt12_items — per-parameter rows for the TFP AOB Lantai 1 & 2
 * Performance Check form.
 *
 * Each row represents one measurement parameter on the form. Items are seeded
 * from TfpAobLt12Template at create-time. Users fill in the 6 panel columns.
 *
 * Columns map to the physical form columns (6 panels, each single value):
 *   - panel_a05_app_room   → Panel A 05 APP Room
 *   - panel_a06_app_room   → Panel A 06 APP Room
 *   - panel_a07_app_room   → Panel A 07 APP Room
 *   - panel_a08_gudang_lt1 → Panel A 08 Gudang Lt 1
 *   - panel_a22_gudang_lt1 → Panel A 22 Gudang Lt 1
 *   - panel_a09_amsc_room  → Panel A 09 AMSC Room
 *
 * is_disabled_map (jsonb) marks which columns are greyed-out / disabled for
 * a given parameter row.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tfp_aob_lt12_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('aob_lt12_record_id');
            $table->foreign('aob_lt12_record_id')
                ->references('id')
                ->on('tfp_aob_lt12_records')
                ->cascadeOnDelete();

            // Parameter identity
            $table->string('parameter_number', 10)->nullable();
            $table->string('parameter_name', 200);
            $table->string('unit', 30)->nullable();

            // Panel A 05 APP Room
            $table->string('panel_a05_app_room', 50)->nullable();

            // Panel A 06 APP Room
            $table->string('panel_a06_app_room', 50)->nullable();

            // Panel A 07 APP Room
            $table->string('panel_a07_app_room', 50)->nullable();

            // Panel A 08 Gudang Lt 1
            $table->string('panel_a08_gudang_lt1', 50)->nullable();

            // Panel A 22 Gudang Lt 1
            $table->string('panel_a22_gudang_lt1', 50)->nullable();

            // Panel A 09 AMSC Room
            $table->string('panel_a09_amsc_room', 50)->nullable();

            // Disabled cell map (jsonb) — keys are column names, value true = disabled
            $table->jsonb('is_disabled_map')->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('aob_lt12_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tfp_aob_lt12_items');
    }
};
