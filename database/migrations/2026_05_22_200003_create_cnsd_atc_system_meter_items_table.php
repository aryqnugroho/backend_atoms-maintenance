<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * cnsd_atc_system_meter_items — supports 7 distinct layouts (sections A-L).
     * Generic value slots adapt per section_code on the frontend:
     *   - value_1..value_4: text inputs / toggle picks / HH:MM:SS times / number counts
     *   - status_flags: comma-separated multi-select (CPU STATUS letters C,M,F,O,A,N,L)
     *   - sub_item_label: used when an item lives under a header (e.g. "GPS Time" under "Clock")
     */
    public function up(): void
    {
        Schema::create('cnsd_atc_system_meter_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('atc_system_meter_record_id');
            $table->string('section_code', 4);
            $table->string('section_name');
            $table->unsignedInteger('group_number')->nullable();
            $table->string('group_name')->nullable();
            $table->string('item_name')->nullable();
            $table->string('sub_item_label')->nullable();
            $table->string('nominal')->nullable();
            $table->string('value_1')->nullable();
            $table->string('value_2')->nullable();
            $table->string('value_3')->nullable();
            $table->string('value_4')->nullable();
            $table->string('status_flags')->nullable();
            $table->text('keterangan')->nullable();
            $table->boolean('is_header')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('atc_system_meter_record_id', 'cnsd_atc_sys_item_record_fk')
                ->references('id')->on('cnsd_atc_system_meter_records')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_atc_system_meter_items');
    }
};
