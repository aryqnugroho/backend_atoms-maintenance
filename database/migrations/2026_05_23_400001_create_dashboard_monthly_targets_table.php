<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dashboard_monthly_targets — per-equipment "wajib dicek N kali per bulan"
 * rules. Powers the "Pengingat Pengecekan Bulanan" card on the dashboard
 * and the kiosk monitor.
 *
 * Each row pins one module (resolved via DashboardModuleRegistry) to a
 * minimum count per calendar month. The summary endpoint computes the
 * current month's actual count of *completed* (fully signed) forms for
 * each target and surfaces dates so technicians know exactly when each
 * one was done.
 *
 * Typical usage:
 *   - Ground Check VHF        → min_count = 2 (twice per month)
 *   - Ground Check Localizer  → min_count = 2
 *   - Grounding Report (TFP)  → min_count = 1
 *
 * Empty by default — Phase 2 ships with no seeded targets so MT/Supervisor
 * decides which equipment to monitor.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('dashboard_monthly_targets', function (Blueprint $table) {
            $table->id();
            $table->string('module_key', 60)->unique();
            $table->integer('min_count')->default(1);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->timestamps();

            $table->foreign('created_by_id')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('updated_by_id')->references('id')->on('local_users')->nullOnDelete();

            $table->index(['is_active', 'sort_order'], 'dashboard_monthly_targets_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_monthly_targets');
    }
};
