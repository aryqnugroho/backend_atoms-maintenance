<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bearing measurement points for DVOR Ground Check Form 1 — "Ground Check VOR".
 *
 * 25 fixed bearings (0°, 15°, 30°, ..., 360°) for TX I and TX II.
 * Technician enters `tx1_reading` / `tx2_reading` (absolute angle as displayed
 * by the instrument, signed if > 180°). System auto-computes:
 *   tx1_error = bearing - tx1_reading_unwrapped
 *   tx2_error = bearing - tx2_reading_unwrapped
 * The Min / Max / Spread / Differential statistics are stored on the
 * `ground_check_dvor_records` table via the service (not here).
 *
 * `tx1_value` / `tx2_value` are free-text manual entry columns (the empty
 * 4th sub-column in the paper form), kept for fidelity to the paper layout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ground_check_dvor_bearing_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ground_check_dvor_record_id');
            $table->integer('bearing'); // 0, 15, 30, ..., 360

            $table->decimal('tx1_reading', 10, 4)->nullable();
            $table->decimal('tx1_error', 10, 4)->nullable();
            $table->string('tx1_value')->nullable();

            $table->decimal('tx2_reading', 10, 4)->nullable();
            $table->decimal('tx2_error', 10, 4)->nullable();
            $table->string('tx2_value')->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('ground_check_dvor_record_id', 'gcd_bearing_record_fk')
                ->references('id')
                ->on('ground_check_dvor_records')
                ->onDelete('cascade');
            $table->index(['ground_check_dvor_record_id', 'sort_order'], 'gcd_bearing_record_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ground_check_dvor_bearing_points');
    }
};
