<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add time_filled and day_name to cnsd_amsc_meter_records.
 *
 * Mirrors the pattern used in tfp_aob_ground_records:
 *   - day_name  : Indonesian day name auto-filled from date at create time
 *   - time_filled : HH:MM, set at create time (WIB), refreshed on each update
 *
 * Existing rows get NULL for both columns — safe, no data loss.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cnsd_amsc_meter_records', function (Blueprint $table) {
            // Insert after shift_type column
            $table->string('day_name', 20)->nullable()->after('shift_type');
            $table->string('time_filled', 10)->nullable()->after('day_name');
        });
    }

    public function down(): void
    {
        Schema::table('cnsd_amsc_meter_records', function (Blueprint $table) {
            $table->dropColumn(['day_name', 'time_filled']);
        });
    }
};
