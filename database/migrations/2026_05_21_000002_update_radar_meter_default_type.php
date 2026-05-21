<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align Radar Meter Reading `type` field with the paper form 002_Radar.jpg.
 *
 *   Paper shows: Merk : ELDIS, Type : 5SR-N-I FL2000, SN : -
 *   Old default value was 'MSSR-1 / RL2000' which did not match the reference.
 *
 * Backfill rule: only update rows where `type` still equals the OLD default
 * ('MSSR-1 / RL2000'). Records where a user/manager deliberately edited the
 * value are preserved.
 */
return new class extends Migration {
    public function up(): void
    {
        // 1. Backfill stale rows (must run before changing the column default so
        //    existing records are updated based on the previous default).
        DB::table('cnsd_radar_meter_records')
            ->where('type', 'MSSR-1 / RL2000')
            ->update(['type' => '5SR-N-I FL2000']);

        // 2. Change column default for new inserts. We re-declare the column
        //    with the same shape but a new default value via ->change().
        Schema::table('cnsd_radar_meter_records', function (Blueprint $table) {
            $table->string('type', 60)->default('5SR-N-I FL2000')->change();
        });
    }

    public function down(): void
    {
        Schema::table('cnsd_radar_meter_records', function (Blueprint $table) {
            $table->string('type', 60)->default('MSSR-1 / RL2000')->change();
        });

        DB::table('cnsd_radar_meter_records')
            ->where('type', '5SR-N-I FL2000')
            ->update(['type' => 'MSSR-1 / RL2000']);
    }
};
