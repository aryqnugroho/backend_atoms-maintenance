<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('logbook_cnsd_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logbook_cnsd_id');
            $table->unsignedBigInteger('cnsd_equipment_id');

            // Status per shift: S = Serviceable, US = Unserviceable, null = not checked
            $table->string('status_pagi', 5)->nullable();
            $table->string('status_siang', 5)->nullable();
            $table->string('status_malam', 5)->nullable();

            // Numeric / measurement value per shift (e.g. temperature in °C).
            // Stored as short string to keep formatting (e.g. "24.5") flexible.
            $table->string('value_pagi', 30)->nullable();
            $table->string('value_siang', 30)->nullable();
            $table->string('value_malam', 30)->nullable();

            $table->timestamps();

            $table->foreign('logbook_cnsd_id', 'lcnsd_items_logbook_fk')
                ->references('id')->on('logbook_cnsds')->cascadeOnDelete();
            $table->foreign('cnsd_equipment_id', 'lcnsd_items_equip_fk')
                ->references('id')->on('cnsd_equipments')->restrictOnDelete();

            $table->unique(['logbook_cnsd_id', 'cnsd_equipment_id'], 'lcnsd_items_unique');
            $table->index('logbook_cnsd_id', 'lcnsd_items_logbook_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logbook_cnsd_items');
    }
};
