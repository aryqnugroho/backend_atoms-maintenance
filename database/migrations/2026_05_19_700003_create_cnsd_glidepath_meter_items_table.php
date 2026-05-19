<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cnsd_glidepath_meter_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('glidepath_meter_record_id');

            // Section / group structure
            $table->string('section_code');   // 'A' = METER READING, 'B' = LINGKUNGAN KERJA
            $table->string('section_name');
            $table->unsignedInteger('group_number')->nullable();
            $table->string('group_name')->nullable();

            // Item identity
            $table->string('item_name')->nullable();

            // Nominal / standard value from paper form
            $table->string('nominal')->nullable();

            // Result columns — layout depends on group:
            //   'single'  → only hasil_1 used (Front Panel, Power Supply groups)
            //   'dual'    → hasil_1 = TX1/M1, hasil_2 = TX2/M2 (CL, DS, CLR, Near Field)
            $table->string('hasil_layout')->default('single'); // 'single' | 'dual'
            $table->string('hasil_1')->nullable();  // TX1 / M1 / single result
            $table->string('hasil_2')->nullable();  // TX2 / M2 (dual only)
            $table->text('keterangan')->nullable();

            // Row flags
            $table->boolean('is_header')->default(false);  // group header row
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->foreign('glidepath_meter_record_id')
                ->references('id')
                ->on('cnsd_glidepath_meter_records')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_glidepath_meter_items');
    }
};
