<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tfp_aob_ground_items — per-parameter rows for the TFP AOB Lantai Ground
 * Performance Check form.
 *
 * Each row represents one measurement parameter on the form. Items are seeded
 * from TfpAobGroundTemplate at create-time. Users fill in the panel/UPS
 * input/output columns.
 *
 * Columns map to the physical form columns:
 *   - panel_cos_a03_input / panel_cos_a03_output  → Panel COS A03
 *   - panel_ats_a12_input / panel_ats_a12_output  → Panel ATS A12
 *   - ups_tescom_a_input  / ups_tescom_a_output   → UPS TESCOM A
 *   - ups_tescom_b_input  / ups_tescom_b_output   → UPS TESCOM B
 *
 * is_disabled_map (jsonb) marks which columns are greyed-out / disabled for
 * a given parameter row (e.g. battery params disable the Panel columns).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tfp_aob_ground_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('aob_ground_record_id');
            $table->foreign('aob_ground_record_id')
                ->references('id')
                ->on('tfp_aob_ground_records')
                ->cascadeOnDelete();

            // Parameter identity
            $table->string('parameter_number', 10)->nullable();
            $table->string('parameter_name', 200);
            $table->string('unit', 30)->nullable();

            // Panel COS A03
            $table->string('panel_cos_a03_input', 50)->nullable();
            $table->string('panel_cos_a03_output', 50)->nullable();

            // Panel ATS A12
            $table->string('panel_ats_a12_input', 50)->nullable();
            $table->string('panel_ats_a12_output', 50)->nullable();

            // UPS TESCOM A
            $table->string('ups_tescom_a_input', 50)->nullable();
            $table->string('ups_tescom_a_output', 50)->nullable();

            // UPS TESCOM B
            $table->string('ups_tescom_b_input', 50)->nullable();
            $table->string('ups_tescom_b_output', 50)->nullable();

            // Disabled cell map (jsonb) — keys are column names, value true = disabled
            $table->jsonb('is_disabled_map')->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('aob_ground_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tfp_aob_ground_items');
    }
};
