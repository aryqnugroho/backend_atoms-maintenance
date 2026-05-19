<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove soft deletes from logbook_tfps.
 *
 * Logbook uses hard delete so that the unique constraint on `date` is
 * fully released when a logbook is deleted, allowing the same date to be
 * used again.
 *
 * Also truncates existing logbook data (items + notes + header) so the
 * database starts clean after this migration.
 */
return new class extends Migration {
    public function up(): void
    {
        // ── Clear all existing logbook data (fresh start) ──────
        // Order matters: child tables first, then parent.
        DB::statement('TRUNCATE TABLE logbook_tfp_notes RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE logbook_tfp_items RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE logbook_tfps RESTART IDENTITY CASCADE');

        // ── Drop the deleted_at column ─────────────────────────
        Schema::table('logbook_tfps', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('logbook_tfps', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
