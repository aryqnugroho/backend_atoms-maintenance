<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_equipments', function (Blueprint $table) {
            $table->id();
            $table->string('category', 150);           // e.g. "A. COMUNICATION - VHF Main"
            $table->string('name', 200);               // leaf item, e.g. "VHF Ground (Primary 118.9 MHz)"
            $table->boolean('is_measurement')->default(false); // true for Temperature items
            $table->string('unit', 20)->nullable();    // e.g. "°C" for measurement items
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();

            $table->index(['category', 'order']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_equipments');
    }
};
