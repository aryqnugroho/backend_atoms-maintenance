<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cnsd_tdme_meter_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tdme_meter_record_id');
            $table->string('section_code');
            $table->string('section_name');
            $table->unsignedInteger('group_number')->nullable();
            $table->string('group_name')->nullable();
            $table->string('item_name')->nullable();
            $table->string('nominal')->nullable();
            $table->string('hasil_layout')->default('single'); // 'single' | 'dual'
            $table->string('hasil_1')->nullable();
            $table->string('hasil_2')->nullable();
            $table->text('keterangan')->nullable();
            $table->boolean('is_header')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tdme_meter_record_id')
                ->references('id')->on('cnsd_tdme_meter_records')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_tdme_meter_items');
    }
};
