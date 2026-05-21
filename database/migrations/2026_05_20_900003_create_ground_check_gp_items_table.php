<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Items for GP Ground Check Form 1 — "Pengujian Berkala di Darat".
 *
 * Four hierarchy/state flags:
 *   - is_header        : top section banner ("BUILT IN TEST", "ADDITIONAL TEST EQUIPMENT")
 *   - is_subheader     : sub-section banner or info row ("(90 Hz + 150 Hz)")
 *   - is_disabled      : grey row, no measurement (MONITORING: parent row)
 *   - is_check_only    : Hasil PD column hidden; only IN/OUT TOLERANCE toggles active
 *                        (INTERCONECTION, ANTENA, INDICATOR LAMP, REMOTE CONTROL, SYSTEM)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ground_check_gp_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ground_check_gp_record_id');
            $table->string('section_name');
            $table->string('subsection_name')->nullable();
            $table->string('item_code')->nullable();
            $table->string('parameter_name');
            $table->string('input_type', 30)->default('text');
            $table->string('calibration_result')->nullable();
            $table->text('tolerance')->nullable(); // text — multi-line for FREQUENCY etc.
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
            $table->boolean('is_check_only')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('ground_check_gp_record_id', 'gcg_items_record_fk')
                ->references('id')
                ->on('ground_check_gp_records')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ground_check_gp_items');
    }
};
