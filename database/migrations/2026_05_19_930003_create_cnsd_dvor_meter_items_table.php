<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cnsd_dvor_meter_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dvor_meter_record_id');
            $table->string('section_code');   // 'I' = PERALATAN, 'II' = LINGKUNGAN KERJA
            $table->string('section_name');
            $table->string('group_code')->nullable();   // A, B, C, D, E, F, G
            $table->string('group_name')->nullable();
            $table->string('item_name')->nullable();
            // DVOR uses 'limit' not 'nominal'
            $table->string('limit_value')->nullable();
            // Single result column for DVOR
            $table->string('hasil_pemeriksaan')->nullable();
            $table->text('keterangan')->nullable();
            $table->boolean('is_header')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('dvor_meter_record_id')
                ->references('id')->on('cnsd_dvor_meter_records')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_dvor_meter_items');
    }
};
