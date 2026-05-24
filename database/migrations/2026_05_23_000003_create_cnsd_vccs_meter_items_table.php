<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cnsd_vccs_meter_items — item rows for CNSD VCCS Meter Reading forms.
 *
 * Columns are designed to accommodate all 4 sections of the VCCS form:
 *   - FRONT PANEL (dual_adaptive): hasil_a + hasil_b adapt per-row to nominal
 *     (Normal/Alrm toggle, √/- toggle, or free-text number for "48 V"-style)
 *   - MSC & RCMS (dual_toggle_nf): hasil_a = Normal toggle, hasil_b = Fault toggle
 *   - CWP (dual_toggle_nf): same as MSC & RCMS
 *   - LINGKUNGAN KERJA (environment): hasil = single result
 *
 * is_blocked / block_reason reserved for future U/S items.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_vccs_meter_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vccs_meter_record_id');

            // Section / group classification
            $table->string('section_code', 10)->nullable();
            $table->string('section_name', 100);
            $table->integer('group_number')->nullable();
            $table->string('group_name', 100)->nullable();
            $table->string('item_number', 30)->nullable();
            $table->string('item_name', 200);

            // Standard / expected value from paper form
            $table->string('nominal', 100)->nullable();

            // FRONT PANEL adaptive + MSC/CWP dual toggles share these columns
            $table->string('hasil_a', 100)->nullable();
            $table->string('hasil_b', 100)->nullable();

            // LINGKUNGAN KERJA single result
            $table->string('hasil', 100)->nullable();

            $table->text('keterangan')->nullable();

            $table->boolean('is_blocked')->default(false);
            $table->string('block_reason', 50)->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('vccs_meter_record_id')
                ->references('id')
                ->on('cnsd_vccs_meter_records')
                ->cascadeOnDelete();

            $table->index('vccs_meter_record_id');
            $table->index('section_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_vccs_meter_items');
    }
};
