<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the canonical Merk value 'OTE / PAE / TELERAD' for existing CNSD
 * Receiver Meter Reading records that pre-date the seeding default. Matches
 * the official 006_Receiver paper form header.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('cnsd_receiver_meter_records')
            ->whereNull('merk')
            ->update(['merk' => 'OTE / PAE / TELERAD']);

        DB::table('cnsd_receiver_meter_records')
            ->where('merk', '')
            ->update(['merk' => 'OTE / PAE / TELERAD']);
    }

    public function down(): void
    {
        DB::table('cnsd_receiver_meter_records')
            ->where('merk', 'OTE / PAE / TELERAD')
            ->update(['merk' => null]);
    }
};
