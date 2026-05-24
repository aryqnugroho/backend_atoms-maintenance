<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cnsd_vccs_freq_meter_items — item rows for CNSD VCCS Frequentis Meter Reading.
 *
 * Layouts handled (per section inputs_layout):
 *   - FRONT PANEL (single_adaptive): hasil_a adapts per nominal (Normal/Alrm,
 *     √/-, or numeric like "220 V"); hasil_b unused.
 *   - MSC & RCMS / CWP (dual_toggle_nf): hasil_a = Normal toggle, hasil_b = Fault toggle.
 *   - LINGKUNGAN KERJA (environment): hasil = single result.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_vccs_freq_meter_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vccs_freq_meter_record_id');

            $table->string('section_code', 10)->nullable();
            $table->string('section_name', 100);
            $table->integer('group_number')->nullable();
            $table->string('group_name', 100)->nullable();
            $table->string('item_number', 30)->nullable();
            $table->string('item_name', 200);

            $table->string('nominal', 100)->nullable();
            $table->string('hasil_a', 100)->nullable();
            $table->string('hasil_b', 100)->nullable();
            $table->string('hasil', 100)->nullable();

            $table->text('keterangan')->nullable();

            $table->boolean('is_blocked')->default(false);
            $table->string('block_reason', 50)->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('vccs_freq_meter_record_id', 'cnsd_vccs_freq_item_rec_fk')
                ->references('id')->on('cnsd_vccs_freq_meter_records')->cascadeOnDelete();

            $table->index('vccs_freq_meter_record_id', 'cnsd_vccs_freq_item_rec_idx');
            $table->index('section_code',              'cnsd_vccs_freq_item_sec_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_vccs_freq_meter_items');
    }
};
