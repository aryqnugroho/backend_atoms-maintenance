<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NAV-analyzer measurement items for GP Ground Check Form 2:
 *   "Ground Check Glide Path dengan PIR Rohde & Schwarz EVSG1000 NAV Analyzer".
 *
 * Two grouped sections, each with a small set of parameters:
 *   - Section "NF" (Near Field)              : DDM (%), SDM (%), RF Level (dBm)
 *   - Section "RCMS Transmitter Setting"     : DDM (%), SDM (%), COU SBO(V), CLR MOD BALANCE (%)
 *
 * Columns mirror paper form: NO | PARAMETER | TRANSMITER 1 | TRANSMITER 2 | KETERANGAN.
 * `section_keterangan` holds the shared section-level note ("PEMBACAAN DI RCMS TRANSMITTER SETTING").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ground_check_gp_nav_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ground_check_gp_record_id');
            $table->string('section_code', 20)->nullable();      // 'nf' | 'rcms'
            $table->string('section_label')->nullable();         // 'NF' | 'RCMS Transmitter Setting'
            $table->text('section_keterangan')->nullable();      // 'PEMBACAAN DI RCMS TRANSMITTER SETTING'
            $table->string('item_code')->nullable();             // '1', '2', '3', '4'
            $table->string('parameter_name');                    // 'DDM (%)' / 'SDM (%)' / etc.
            $table->string('tx1_value')->nullable();
            $table->string('tx2_value')->nullable();
            $table->text('keterangan')->nullable();
            $table->boolean('is_section_header')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('ground_check_gp_record_id', 'gcg_nav_record_fk')
                ->references('id')
                ->on('ground_check_gp_records')
                ->onDelete('cascade');
            $table->index(['ground_check_gp_record_id', 'sort_order'], 'gcg_nav_record_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ground_check_gp_nav_items');
    }
};
