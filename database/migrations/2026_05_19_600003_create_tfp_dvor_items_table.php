<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tfp_dvor_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dvor_record_id');
            $table->foreign('dvor_record_id')->references('id')->on('tfp_dvor_records')->cascadeOnDelete();
            $table->string('parameter_number', 10)->nullable();
            $table->string('parameter_name', 200);
            $table->string('unit', 30)->nullable();
            // Panel D.01 (single)
            $table->string('panel_d01', 50)->nullable();
            // Panel D.03 CCTV Indoor (single)
            $table->string('panel_d03', 50)->nullable();
            // Panel D.04 CCTV Outdoor (single)
            $table->string('panel_d04', 50)->nullable();
            // Panel Input D.05 (single)
            $table->string('panel_d05', 50)->nullable();
            // Panel ATS/AMF (D.06) — Input/Output
            $table->string('panel_ats_d06_input', 50)->nullable();
            $table->string('panel_ats_d06_output', 50)->nullable();
            $table->jsonb('is_disabled_map')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('dvor_record_id');
        });
    }
    public function down(): void { Schema::dropIfExists('tfp_dvor_items'); }
};
