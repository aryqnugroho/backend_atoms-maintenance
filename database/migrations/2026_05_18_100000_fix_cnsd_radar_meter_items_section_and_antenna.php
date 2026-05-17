<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix existing cnsd_radar_meter_items rows seeded before two template changes:
 *
 * 1. Section code 'C' → 'B'  (Lingkungan Kerja)
 *    Before: section_code = 'C', section_name = 'LINGKUNGAN KERJA'
 *    After:  section_code = 'B', section_name = 'LINGKUNGAN KERJA'
 *    Cause: template changed section code from 'C' to 'B'. Rows seeded before
 *    this change have the old code, making them invisible in the frontend tab
 *    (frontend looks up items by section_code 'B' from sectionMeta).
 *
 * 2. item_name '* Antenna' → 'Antenna'  (RADAR CONTROL group)
 *    Before: item_name = '* Antenna'   (treated as sub-header in frontend)
 *    After:  item_name = 'Antenna'     (treated as editable input row)
 *    Cause: Antenna was originally seeded as a subheader marker. The template
 *    was updated to make it a regular editable row, but existing DB records
 *    still have the old name which triggers the isSubHeader() guard in frontend.
 *
 * Both changes are safe and idempotent (WHERE clause prevents double-apply).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Fix 1: Lingkungan Kerja section code C → B
        DB::table('cnsd_radar_meter_items')
            ->where('section_code', 'C')
            ->where('section_name', 'LINGKUNGAN KERJA')
            ->update(['section_code' => 'B']);

        // Fix 2: Antenna item name — strip leading '* ' marker
        DB::table('cnsd_radar_meter_items')
            ->where('item_name', '* Antenna')
            ->update(['item_name' => 'Antenna']);
    }

    public function down(): void
    {
        // Reverse: restore old values
        DB::table('cnsd_radar_meter_items')
            ->where('section_code', 'B')
            ->where('section_name', 'LINGKUNGAN KERJA')
            ->update(['section_code' => 'C']);

        DB::table('cnsd_radar_meter_items')
            ->where('item_name', 'Antenna')
            ->whereIn('group_name', ['RADAR CONTROL'])
            ->update(['item_name' => '* Antenna']);
    }
};
