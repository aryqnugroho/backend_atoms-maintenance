<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tfp_transmitter_tx_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tx_record_id');
            $table->foreign('tx_record_id')
                ->references('id')
                ->on('tfp_transmitter_tx_records')
                ->cascadeOnDelete();

            $table->string('parameter_number', 10)->nullable();
            $table->string('parameter_name', 200);
            $table->string('unit', 30)->nullable();

            // Panel TX 01 (single column)
            $table->string('panel_tx01', 50)->nullable();
            // Panel TX 02 (single column)
            $table->string('panel_tx02', 50)->nullable();
            // Panel COS (TX 03) — Input/Output
            $table->string('panel_cos_tx03_input', 50)->nullable();
            $table->string('panel_cos_tx03_output', 50)->nullable();
            // Panel Output UPS TX 04 (single column)
            $table->string('panel_output_ups_tx04', 50)->nullable();
            // Panel UPS (TX 07) — Input/Output
            $table->string('panel_ups_tx07_input', 50)->nullable();
            $table->string('panel_ups_tx07_output', 50)->nullable();
            // Panel AC TX 06 (single column)
            $table->string('panel_ac_tx06', 50)->nullable();
            // UPS PILLER — Input/Output
            $table->string('ups_piller_input', 50)->nullable();
            $table->string('ups_piller_output', 50)->nullable();
            // Panel MILAT RU 11 (single column)
            $table->string('panel_milat_ru11', 50)->nullable();

            $table->jsonb('is_disabled_map')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('tx_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tfp_transmitter_tx_items');
    }
};
