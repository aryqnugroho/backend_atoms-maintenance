<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add per-shift Manager Teknik signature slots to logbook_cnsds.
 * Same shape as the matching TFP migration.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::statement('TRUNCATE TABLE logbook_cnsd_notes RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE logbook_cnsd_items RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE logbook_cnsds RESTART IDENTITY CASCADE');

        Schema::table('logbook_cnsds', function (Blueprint $table) {
            $table->dropForeign(['manager_signed_by_id']);
            $table->dropColumn([
                'manager_signed_by_id',
                'manager_signed_by_name',
                'manager_signed_by_role',
                'manager_signature',
                'manager_signed_at',
            ]);

            foreach (['pagi', 'siang', 'malam'] as $shift) {
                $table->unsignedBigInteger("manager_signed_by_id_{$shift}")->nullable();
                $table->string("manager_signed_by_name_{$shift}", 120)->nullable();
                $table->string("manager_signed_by_role_{$shift}", 50)->nullable();
                $table->longText("manager_signature_{$shift}")->nullable();
                $table->timestamp("manager_signed_at_{$shift}")->nullable();

                $table->foreign("manager_signed_by_id_{$shift}", "logbook_cnsds_mgr_{$shift}_fk")
                    ->references('id')->on('local_users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('logbook_cnsds', function (Blueprint $table) {
            foreach (['pagi', 'siang', 'malam'] as $shift) {
                $table->dropForeign("logbook_cnsds_mgr_{$shift}_fk");
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
