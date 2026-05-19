<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ground_check_adc_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ground_check_adc_record_id');
            $table->string('section_name');
            $table->string('item_code')->nullable();
            $table->string('parameter_name');
            $table->string('calibration_result')->nullable();
            $table->string('tolerance')->nullable();
            $table->string('tx1_hasil_pd')->nullable();
            $table->string('tx1_in_tolerance')->nullable();
            $table->string('tx1_out_of_tolerance')->nullable();
            $table->string('tx2_hasil_pd')->nullable();
            $table->string('tx2_in_tolerance')->nullable();
            $table->string('tx2_out_of_tolerance')->nullable();
            $table->text('keterangan')->nullable();
            $table->boolean('is_header')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('ground_check_adc_record_id')
                ->references('id')
                ->on('ground_check_adc_records')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ground_check_adc_items');
    }
};
