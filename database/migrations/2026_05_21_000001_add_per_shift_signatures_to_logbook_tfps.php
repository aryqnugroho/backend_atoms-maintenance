<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add per-shift Manager Teknik signature slots to logbook_tfps.
 *
 * Before: 1 set of signature fields (manager_signature / manager_signed_by_*)
 *         shared across all 3 shifts. When any manager signed, the UI rendered
 *         all 3 shift slots as signed (bug).
 *
 * After:  3 sets of signature fields, one per shift (pagi/siang/malam).
 *         Each shift's manager can only sign that shift's slot.
 *
 * Dev convention: TRUNCATE first (consistent with the earlier
 * `2026_05_19_960005_drop_soft_deletes_from_logbook_tfps.php` reset).
 */
return new class extends Migration {
    public function up(): void
    {
        // Clear existing data (per-shift schema change — old single signature has no clean mapping)
        DB::statement('TRUNCATE TABLE logbook_tfp_notes RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE logbook_tfp_items RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE logbook_tfps RESTART IDENTITY CASCADE');

        Schema::table('logbook_tfps', function (Blueprint $table) {
            // Drop old single-signature columns
            $table->dropForeign(['manager_signed_by_id']);
            $table->dropColumn([
                'manager_signed_by_id',
                'manager_signed_by_name',
                'manager_signed_by_role',
                'manager_signature',
                'manager_signed_at',
            ]);

            // Add per-shift signature slots
            foreach (['pagi', 'siang', 'malam'] as $shift) {
                $table->unsignedBigInteger("manager_signed_by_id_{$shift}")->nullable();
                $table->string("manager_signed_by_name_{$shift}", 120)->nullable();
                $table->string("manager_signed_by_role_{$shift}", 50)->nullable();
                $table->longText("manager_signature_{$shift}")->nullable();
                $table->timestamp("manager_signed_at_{$shift}")->nullable();

                $table->foreign("manager_signed_by_id_{$shift}", "logbook_tfps_mgr_{$shift}_fk")
                    ->references('id')->on('local_users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('logbook_tfps', function (Blueprint $table) {
            foreach (['pagi', 'siang', 'malam'] as $shift) {
                $table->dropForeign("logbook_tfps_mgr_{$shift}_fk");
                $table->dropColumn([
                    "manager_signed_by_id_{$shift}",
                    "manager_signed_by_name_{$shift}",
                    "manager_signed_by_role_{$shift}",
                    "manager_signature_{$shift}",
                    "manager_signed_at_{$shift}",
                ]);
            }

            $table->unsignedBigInteger('manager_signed_by_id')->nullable();
            $table->string('manager_signed_by_name', 120)->nullable();
            $table->string('manager_signed_by_role', 50)->nullable();
            $table->longText('manager_signature')->nullable();
            $table->timestamp('manager_signed_at')->nullable();
            $table->foreign('manager_signed_by_id')->references('id')->on('local_users')->nullOnDelete();
        });
    }
};
