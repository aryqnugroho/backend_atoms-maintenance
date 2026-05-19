<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('logbook_tfp_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logbook_tfp_id');
            $table->unsignedBigInteger('tfp_equipment_id');

            // Status per shift: S = Serviceable, US = Unserviceable, null = not checked
            $table->string('status_pagi', 5)->nullable();   // S | US | null
            $table->string('status_siang', 5)->nullable();
            $table->string('status_malam', 5)->nullable();

            $table->timestamps();

            $table->foreign('logbook_tfp_id', 'ltfp_items_logbook_fk')
                ->references('id')->on('logbook_tfps')->cascadeOnDelete();
            $table->foreign('tfp_equipment_id', 'ltfp_items_equip_fk')
                ->references('id')->on('tfp_equipments')->restrictOnDelete();

            $table->unique(['logbook_tfp_id', 'tfp_equipment_id'], 'ltfp_items_unique');
            $table->index('logbook_tfp_id', 'ltfp_items_logbook_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logbook_tfp_items');
    }
};
