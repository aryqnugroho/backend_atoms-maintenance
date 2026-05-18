<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tfp_tower_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tower_record_id');
            $table->foreign('tower_record_id')->references('id')->on('tfp_tower_records')->cascadeOnDelete();

            $table->string('parameter_number', 10)->nullable();
            $table->string('parameter_name', 200);
            $table->string('unit', 30)->nullable();

            // Panel A 10 Tower Lt 11 (single)
            $table->string('panel_a10', 50)->nullable();
            // Panel A 11 Tower Lt 11 (single)
            $table->string('panel_a11', 50)->nullable();
            // Panel ATS (A 13) — Input/Output
            $table->string('panel_ats_a13_input', 50)->nullable();
            $table->string('panel_ats_a13_output', 50)->nullable();
            // Panel A 14 Tower Lt 11 (single)
            $table->string('panel_a14', 50)->nullable();
            // Panel A 16 ADC Room (single)
            $table->string('panel_a16', 50)->nullable();
            // Panel A 17 Ruang Lift (single)
            $table->string('panel_a17', 50)->nullable();
            // Panel A 18 Ruang RX (single)
            $table->string('panel_a18', 50)->nullable();
            // Panel A 19 Roof Top Power (single)
            $table->string('panel_a19', 50)->nullable();
            // Panel A 20 Power CCTV (single)
            $table->string('panel_a20', 50)->nullable();
            // Panel MILAT RU 12 & 13 (single)
            $table->string('panel_milat_ru1213', 50)->nullable();

            $table->jsonb('is_disabled_map')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('tower_record_id');
        });
    }

    public function down(): void { Schema::dropIfExists('tfp_tower_items'); }
};
