<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * dashboard_checklist_items — editable list of modules shown on the dashboard
 * "Pengingat Pengecekan Harian" card (and the kiosk /monitor variant).
 *
 * Replaces the hardcoded CATALOG previously inside DashboardController and
 * MonitorController. Manager Teknik / Supervisor CNSD / Supervisor TFP /
 * Admin can edit (add, remove, reorder, toggle active) without a deploy.
 *
 * Two categories:
 *   - 'wajib' — shown in every shift (shift_type must be NULL)
 *   - 'shift' — shown only on the matching shift_type (pagi|siang|malam)
 *
 * `module_key` references DashboardModuleRegistry. The registry is the
 * authoritative source for label, route, and Eloquent model — this table
 * just persists which modules are listed where and in what order.
 *
 * Backfill: seeds the 14 items previously hardcoded in the two controllers
 * so the dashboard looks identical the moment migration runs.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('dashboard_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->string('module_key', 60);
            $table->string('category', 10);                 // 'wajib' | 'shift'
            $table->string('shift_type', 10)->nullable();   // 'pagi'|'siang'|'malam' or null for wajib
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->timestamps();

            $table->foreign('created_by_id')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('updated_by_id')->references('id')->on('local_users')->nullOnDelete();

            $table->index(['category', 'shift_type', 'sort_order'], 'dashboard_checklist_lookup_idx');
            $table->index('is_active');
        });

        // Partial unique indexes — PostgreSQL treats NULL as not-equal in regular
        // unique constraints, so split wajib (shift_type IS NULL) and shift
        // (shift_type IS NOT NULL) to enforce "one row per (module, slot)".
        DB::statement(
            'CREATE UNIQUE INDEX dashboard_checklist_items_wajib_unique '
            . 'ON dashboard_checklist_items (module_key) '
            . "WHERE category = 'wajib'"
        );
        DB::statement(
            'CREATE UNIQUE INDEX dashboard_checklist_items_shift_unique '
            . 'ON dashboard_checklist_items (module_key, shift_type) '
            . "WHERE category = 'shift'"
        );

        // ─── Seed: backfill the 14 previously-hardcoded items ────────────
        $now = now();
        $seed = [
            // Wajib (every shift)
            ['module_key' => 'cnsd-readiness', 'category' => 'wajib', 'shift_type' => null,    'sort_order' => 1],
            ['module_key' => 'tfp-aob-ground', 'category' => 'wajib', 'shift_type' => null,    'sort_order' => 2],

            // Pagi
            ['module_key' => 'cnsd-localizer',   'category' => 'shift', 'shift_type' => 'pagi', 'sort_order' => 1],
            ['module_key' => 'cnsd-glidepath',   'category' => 'shift', 'shift_type' => 'pagi', 'sort_order' => 2],
            ['module_key' => 'cnsd-tdme',        'category' => 'shift', 'shift_type' => 'pagi', 'sort_order' => 3],
            ['module_key' => 'cnsd-transmitter', 'category' => 'shift', 'shift_type' => 'pagi', 'sort_order' => 4],

            // Siang
            ['module_key' => 'cnsd-dvor',  'category' => 'shift', 'shift_type' => 'siang', 'sort_order' => 1],
            ['module_key' => 'cnsd-dme',   'category' => 'shift', 'shift_type' => 'siang', 'sort_order' => 2],
            ['module_key' => 'cnsd-radar', 'category' => 'shift', 'shift_type' => 'siang', 'sort_order' => 3],

            // Malam
            ['module_key' => 'cnsd-recorder',   'category' => 'shift', 'shift_type' => 'malam', 'sort_order' => 1],
            ['module_key' => 'cnsd-atc-system', 'category' => 'shift', 'shift_type' => 'malam', 'sort_order' => 2],
            ['module_key' => 'cnsd-amsc',       'category' => 'shift', 'shift_type' => 'malam', 'sort_order' => 3],
            ['module_key' => 'cnsd-atis',       'category' => 'shift', 'shift_type' => 'malam', 'sort_order' => 4],
            ['module_key' => 'cnsd-receiver',   'category' => 'shift', 'shift_type' => 'malam', 'sort_order' => 5],
        ];

        $rows = array_map(static function ($row) use ($now) {
            return array_merge($row, [
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $seed);

        DB::table('dashboard_checklist_items')->insert($rows);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS dashboard_checklist_items_wajib_unique');
        DB::statement('DROP INDEX IF EXISTS dashboard_checklist_items_shift_unique');
        Schema::dropIfExists('dashboard_checklist_items');
    }
};
