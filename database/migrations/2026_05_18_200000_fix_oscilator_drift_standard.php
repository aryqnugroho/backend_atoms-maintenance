<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix existing cnsd_radar_meter_items rows where 'Oscilator Drift' was seeded
 * without a standard value.
 *
 * Root cause: The template originally had 'standard' => null for Oscilator Drift.
 * The correct standard per the paper form is "Green" (same as the other GPS
 * Receiver items). This migration patches existing rows so the UI and print view
 * show the correct standard without requiring records to be recreated.
 *
 * The update is safe and idempotent:
 *   - Only rows where standard IS NULL and item_name = 'Oscilator Drift' are touched.
 *   - Rows that already have a standard set are left unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('cnsd_radar_meter_items')
            ->where('item_name', 'Oscilator Drift')
            ->whereNull('standard')
            ->update(['standard' => 'Green']);
    }

    public function down(): void
    {
        // Reverse: clear back to null only for rows we just set.
        // We cannot reliably know which rows were changed vs pre-existing Green,
        // so this resets ALL Oscilator Drift rows' standard back to null.
        DB::table('cnsd_radar_meter_items')
            ->where('item_name', 'Oscilator Drift')
            ->where('standard', 'Green')
            ->update(['standard' => null]);
    }
};
