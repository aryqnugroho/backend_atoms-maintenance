<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tfp_equipments', function (Blueprint $table) {
            $table->id();
            $table->string('category', 100);          // e.g. "POWER CNS & OTOMASI"
            $table->string('name', 200);              // e.g. "Power Tx"
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();

            $table->index(['category', 'order']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tfp_equipments');
    }
};
