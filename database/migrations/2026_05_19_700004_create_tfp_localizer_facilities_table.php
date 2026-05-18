<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tfp_localizer_facilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('localizer_record_id');
            $table->foreign('localizer_record_id')->references('id')->on('tfp_localizer_records')->cascadeOnDelete();
            $table->string('facility_name', 100);
            $table->string('kondisi', 20)->nullable();
            $table->text('keterangan')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('localizer_record_id');
        });
    }
    public function down(): void { Schema::dropIfExists('tfp_localizer_facilities'); }
};
