<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Items for LLZ Ground Check Form 1 — "Pengujian Berkala di Darat".
 *
 * Hierarchical layout with 3 flags:
 *   - is_header     : top section banner ("PARAMETER PERALATAN", "ADDITIONAL TEST EQUIPMENT")
 *   - is_subheader  : sub-section banner ("BUILT IN TEST", "SENSITIVITY", "RADIATION")
 *   - is_disabled   : grey rows with no measurement (INTERCONECTION, ANTENA, MONITORING:, etc.)
 *
 * `subsection_name` (nullable) carries the grouping label for indentation/print fidelity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ground_check_llz_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ground_check_llz_record_id');
            $table->string('section_name');
            $table->string('subsection_name')->nullable();
            $table->string('item_code')->nullable();
            $table->string('parameter_name');
            $table->string('input_type', 30)->default('text');
            $table->string('calibration_result')->nullable();
            $table->text('tolerance')->nullable(); // text — multi-line for FREQUENCY
            $table->string('tx1_hasil_pd')->nullable();
            $table->string('tx1_in_tolerance')->nullable();
            $table->string('tx1_out_of_tolerance')->nullable();
            $table->string('tx2_hasil_pd')->nullable();
            $table->string('tx2_in_tolerance')->nullable();
            $table->string('tx2_out_of_tolerance')->nullable();
            $table->text('keterangan')->nullable();
            $table->boolean('is_header')->default(false);
            $table->boolean('is_subheader')->default(false);
            $table->boolean('is_disabled')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('ground_check_llz_record_id', 'gcl_items_record_fk')
                ->references('id')
                ->on('ground_check_llz_records')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ground_check_llz_items');
    }
};
