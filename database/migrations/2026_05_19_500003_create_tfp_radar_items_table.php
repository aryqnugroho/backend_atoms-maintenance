<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tfp_radar_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('radar_record_id');
            $table->foreign('radar_record_id')->references('id')->on('tfp_radar_records')->cascadeOnDelete();
            $table->string('parameter_number', 10)->nullable();
            $table->string('parameter_name', 200);
            $table->string('unit', 30)->nullable();
            $table->string('panel_rd01', 50)->nullable();
            $table->string('panel_rd02', 50)->nullable();
            $table->string('panel_cos_rd03_input', 50)->nullable();
            $table->string('panel_cos_rd03_output', 50)->nullable();
            $table->string('ups_topaz_input', 50)->nullable();
            $table->string('ups_topaz_output', 50)->nullable();
            $table->string('panel_rd04', 50)->nullable();
            $table->string('panel_rd05', 50)->nullable();
            $table->string('panel_rd06', 50)->nullable();
            $table->string('panel_rd07', 50)->nullable();
            $table->string('panel_rd08', 50)->nullable();
            $table->jsonb('is_disabled_map')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('radar_record_id');
        });
    }
    public function down(): void { Schema::dropIfExists('tfp_radar_items'); }
};
