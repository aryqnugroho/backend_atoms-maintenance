<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Items for DVOR Ground Check Form 3 — "Pengujian Berkala di Darat".
 *
 * Four hierarchy/state flags (same set as GP):
 *   - is_header     : top section banner ("BUILT IN TEST")
 *   - is_subheader  : sub-section banner
 *   - is_disabled   : grey row, no measurement (MONITOR: parent row)
 *   - is_check_only : Hasil PD column hidden; only IN/OUT TOLERANCE toggles active
 *                     (items 8-12: MANUAL CHANGE OVER, INTERKONEKSI, CHANGE OVER TIME,
 *                     INDICATOR LAMP & METERING, REMOTE MONITORING)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ground_check_dvor_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ground_check_dvor_record_id');
            $table->string('section_name');
            $table->string('subsection_name')->nullable();
            $table->string('item_code')->nullable();
            $table->string('parameter_name');
            $table->string('input_type', 30)->default('text');
            $table->string('calibration_result')->nullable();
            $table->text('tolerance')->nullable();
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

            $table->foreign('ground_check_dvor_record_id', 'gcd_items_record_fk')
                ->references('id')
                ->on('ground_check_dvor_records')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ground_check_dvor_items');
    }
};
