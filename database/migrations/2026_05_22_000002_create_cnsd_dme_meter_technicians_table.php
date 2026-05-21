<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cnsd_dme_meter_technicians', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dme_meter_record_id');
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->string('technician_name');
            $table->longText('technician_signature')->nullable();
            $table->unsignedBigInteger('technician_signed_by')->nullable();
            $table->timestamp('technician_signed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('dme_meter_record_id')
                ->references('id')->on('cnsd_dme_meter_records')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_dme_meter_technicians');
    }
};
