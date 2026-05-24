<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cnsd_asmgcs_meter_items — item rows for CNSD ASMGCS Meter Reading.
 *
 * Layouts:
 *   - FRONT PANEL (single_adaptive): hasil_a adapts per nominal
 *   - CENTRAL SERVER (dual_toggle_nf): hasil_a = Normal, hasil_b = Fault
 *   - LINGKUNGAN KERJA (environment): hasil = single
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_asmgcs_meter_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asmgcs_meter_record_id');

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

            $table->foreign('asmgcs_meter_record_id')
                ->references('id')->on('cnsd_asmgcs_meter_records')->cascadeOnDelete();

            $table->index('asmgcs_meter_record_id');
            $table->index('section_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_asmgcs_meter_items');
    }
};
