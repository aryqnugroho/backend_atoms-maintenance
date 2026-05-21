<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Curve points for LLZ Ground Check Form 2 — "Groundcheck Performance Curve".
 *
 * Each row is a fixed measurement point at a (jarak_m, degrees) coordinate
 * along one of three sides of the localizer beam:
 *   - 90hz   : 90 Hz side (35° → 1.85°)
 *   - center : centerline (0°)
 *   - 150hz  : 150 Hz side (1.85° → 35°)
 *
 * Each TX (1 and 2) measures 6 parameters: DDM(%), DDM(µA), SUM(%),
 * MOD 90 Hz (%), MOD 150 Hz (%), RF LEVEL (dB).
 *
 * The signed-degrees X coordinate for the auto-plot is:
 *   -degrees  for side='90hz'
 *   0         for side='center'
 *   +degrees  for side='150hz'
 *
 * The Y coordinate is `tx1_ddm_pct` / `tx2_ddm_pct`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ground_check_llz_curve_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ground_check_llz_record_id');
            $table->string('side', 10); // '90hz' | 'center' | '150hz'
            $table->decimal('jarak_m', 8, 2);
            $table->decimal('degrees', 6, 2);

            // TX1 measurements
            $table->decimal('tx1_ddm_pct', 10, 4)->nullable();
            $table->decimal('tx1_ddm_ua', 10, 4)->nullable();
            $table->decimal('tx1_sum_pct', 10, 4)->nullable();
            $table->decimal('tx1_mod_90hz', 10, 4)->nullable();
            $table->decimal('tx1_mod_150hz', 10, 4)->nullable();
            $table->decimal('tx1_rf_level_db', 10, 4)->nullable();

            // TX2 measurements
            $table->decimal('tx2_ddm_pct', 10, 4)->nullable();
            $table->decimal('tx2_ddm_ua', 10, 4)->nullable();
            $table->decimal('tx2_sum_pct', 10, 4)->nullable();
            $table->decimal('tx2_mod_90hz', 10, 4)->nullable();
            $table->decimal('tx2_mod_150hz', 10, 4)->nullable();
            $table->decimal('tx2_rf_level_db', 10, 4)->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('ground_check_llz_record_id', 'gcl_curve_record_fk')
                ->references('id')
                ->on('ground_check_llz_records')
                ->onDelete('cascade');
            $table->index(['ground_check_llz_record_id', 'sort_order'], 'gcl_curve_record_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ground_check_llz_curve_points');
    }
};
