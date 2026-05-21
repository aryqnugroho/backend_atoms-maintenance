<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Services\Cnsd\CnsdEq1Template;

/**
 * Per-record sections_meta JSON column.
 *
 * Why store per-record (vs always reading from CnsdEq1Template):
 *   Manager/Supervisor can rename section headings on an existing form
 *   (e.g. KOMUNIKASI PENERBANGAN -> KOMUNIKASI BARU). The template only
 *   provides the *default* values when a record is first created.
 *
 * Shape:
 *   [
 *     {"name": "KOMUNIKASI PENERBANGAN", "columns_label_1": "SERVER AKTIF", "columns_label_2": "DUAL STATE"},
 *     ...
 *   ]
 *
 * Backfill: any existing records get the default EQ-1 sections meta.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cnsd_readiness_records', function (Blueprint $table) {
            $table->json('sections_meta')->nullable()->after('room');
        });

        // Backfill existing rows from the template default.
        $defaults = json_encode(CnsdEq1Template::sectionMeta(), JSON_UNESCAPED_UNICODE);
        DB::table('cnsd_readiness_records')
            ->whereNull('sections_meta')
            ->update(['sections_meta' => $defaults]);
    }

    public function down(): void
    {
        Schema::table('cnsd_readiness_records', function (Blueprint $table) {
            $table->dropColumn('sections_meta');
        });
    }
};
