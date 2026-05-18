<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tfp_glidepath_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('glidepath_record_id');
            $table->foreign('glidepath_record_id')->references('id')->on('tfp_glidepath_records')->cascadeOnDelete();
            $table->string('parameter_number', 10)->nullable();
            $table->string('parameter_name', 200);
            $table->string('unit', 30)->nullable();
            // Panel GP 01 (single column — only panel)
            $table->string('panel_gp01', 50)->nullable();
            $table->jsonb('is_disabled_map')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('glidepath_record_id');
        });
    }
    public function down(): void { Schema::dropIfExists('tfp_glidepath_items'); }
};
