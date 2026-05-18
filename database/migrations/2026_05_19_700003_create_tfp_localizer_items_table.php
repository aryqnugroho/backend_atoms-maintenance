<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tfp_localizer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('localizer_record_id');
            $table->foreign('localizer_record_id')->references('id')->on('tfp_localizer_records')->cascadeOnDelete();
            $table->string('parameter_number', 10)->nullable();
            $table->string('parameter_name', 200);
            $table->string('unit', 30)->nullable();
            // Panel LZ 01 (single)
            $table->string('panel_lz01', 50)->nullable();
            // Panel COS (LZ 02) — Input/Output
            $table->string('panel_cos_lz02_input', 50)->nullable();
            $table->string('panel_cos_lz02_output', 50)->nullable();
            // Panel LZ 03 (single)
            $table->string('panel_lz03', 50)->nullable();
            // Panel COS (LZ 04) — Input/Output
            $table->string('panel_cos_lz04_input', 50)->nullable();
            $table->string('panel_cos_lz04_output', 50)->nullable();
            // Panel MLAT RU 04 (single)
            $table->string('panel_mlat_ru04', 50)->nullable();
            $table->jsonb('is_disabled_map')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('localizer_record_id');
        });
    }
    public function down(): void { Schema::dropIfExists('tfp_localizer_items'); }
};
