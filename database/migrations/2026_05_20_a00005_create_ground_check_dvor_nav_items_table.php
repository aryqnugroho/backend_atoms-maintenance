<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NAV-analyzer measurement items for DVOR Ground Check Form 4:
 *   "Ground Check DVOR dengan PIR Rohde & Schwarz".
 *
 * 4 sections, each with a small set of parameters:
 *   - Section "BEARING"           : Bearing (degree), FMI
 *   - Section "MODULASI"          : 30 Hz, 9960 Hz, RF Level
 *   - Section "IDENT INFORMATION" : Ident Modulation, Ident Frequency
 *   - Section "MEASUREMENT FREQ"  : 9960 Hz Freq, Carrier Freq (KHz),
 *                                   30 Hz Subcarrier, 30 Hz Freq
 *
 * Each row records 4 measurement columns:
 *   REFF TOWER     : ref_tx1_value, ref_tx2_value
 *   PERALATAN DVOR : eq_tx1_value, eq_tx2_value
 *
 * All four columns are fillable (the paper form occasionally leaves the
 * REFF TOWER TX1 column empty due to field conditions — see `note` on the record).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ground_check_dvor_nav_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ground_check_dvor_record_id');
            $table->string('section_code', 30)->nullable();      // 'bearing' | 'modulasi' | 'ident' | 'meas_freq'
            $table->string('section_label')->nullable();         // 'BEARING' | 'MODULASI' | ...
            $table->string('item_code')->nullable();             // '1', '2', ...
            $table->string('parameter_name');
            $table->string('ref_tx1_value')->nullable();
            $table->string('ref_tx2_value')->nullable();
            $table->string('eq_tx1_value')->nullable();
            $table->string('eq_tx2_value')->nullable();
            $table->boolean('is_section_header')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('ground_check_dvor_record_id', 'gcd_nav_record_fk')
                ->references('id')
                ->on('ground_check_dvor_records')
                ->onDelete('cascade');
            $table->index(['ground_check_dvor_record_id', 'sort_order'], 'gcd_nav_record_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ground_check_dvor_nav_items');
    }
};
