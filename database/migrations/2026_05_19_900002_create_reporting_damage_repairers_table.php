<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reporting_damage_repairers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('person_name');
            $table->string('person_role', 50)->nullable();      // Teknisi CNSD/Teknisi TFP/Supervisor CNSD/Supervisor TFP
            $table->string('person_division', 30)->nullable();  // CNSD | TFP | Management

            // Per-row signature (immutable, name-matched)
            $table->longText('signature')->nullable();
            $table->unsignedBigInteger('signed_by')->nullable();
            $table->timestamp('signed_at')->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('report_id', 'rep_dam_rep_report_fk')
                ->references('id')->on('reporting_damage_reports')->cascadeOnDelete();
            $table->foreign('person_id', 'rep_dam_rep_user_fk')
                ->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('signed_by', 'rep_dam_rep_signer_fk')
                ->references('id')->on('local_users')->nullOnDelete();

            $table->index('report_id', 'rep_dam_rep_report_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_damage_repairers');
    }
};
