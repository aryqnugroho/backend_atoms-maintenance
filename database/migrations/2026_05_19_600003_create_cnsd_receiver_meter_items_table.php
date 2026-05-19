<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cnsd_receiver_meter_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiver_meter_record_id');

            // Section / group structure
            $table->string('section_code');   // '1' = RECEIVER, '2' = LINGKUNGAN KERJA
            $table->string('section_name');
            $table->unsignedInteger('group_number')->nullable();
            $table->string('group_name')->nullable();

            // Item identity
            $table->string('item_name')->nullable();   // frequency label or kegiatan name

            // Section 1 (RECEIVER) columns
            $table->string('status_a')->nullable();    // ON LINE / OFF LINE
            $table->string('status_b')->nullable();    // ON LINE / OFF LINE
            $table->string('sequelsh_on')->nullable(); // free text
            $table->text('keterangan')->nullable();

            // Section 2 (LINGKUNGAN KERJA) columns
            $table->string('nominal')->nullable();     // expected value
            $table->string('hasil')->nullable();       // actual result

            // Row flags
            $table->boolean('is_header')->default(false);  // group header row
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->foreign('receiver_meter_record_id')
                ->references('id')
                ->on('cnsd_receiver_meter_records')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_receiver_meter_items');
    }
};
