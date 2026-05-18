<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tfp_glidepath_facilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('glidepath_record_id');
            $table->foreign('glidepath_record_id')->references('id')->on('tfp_glidepath_records')->cascadeOnDelete();
            $table->string('facility_name', 100);
            $table->string('kondisi', 20)->nullable();
            $table->text('keterangan')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('glidepath_record_id');
        });
    }
    public function down(): void { Schema::dropIfExists('tfp_glidepath_facilities'); }
};
