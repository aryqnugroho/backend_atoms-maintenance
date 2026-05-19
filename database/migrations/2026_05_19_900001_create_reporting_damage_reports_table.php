<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reporting_damage_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_number', 80)->unique();
            $table->date('report_date');
            $table->string('day_name', 20)->nullable();

            // Section 1 — Data Laporan
            $table->string('location', 200);
            $table->string('facility', 200);
            $table->string('equipment_name', 200);
            $table->string('equipment_module', 200)->nullable();
            $table->string('damage_category', 20); // Ringan | Sedang | Berat

            // Section 2 — Detail Kerusakan
            $table->text('damage_description');
            $table->text('damage_cause')->nullable();

            // Section 3 — Tindakan Perbaikan
            $table->text('repair_action')->nullable();
            $table->string('repair_by_type', 20)->nullable(); // lokasi | pusat

            // Section 4 — Waktu Kerusakan
            $table->dateTime('damage_started_at')->nullable();
            $table->dateTime('repair_finished_at')->nullable();
            $table->decimal('downtime_hours', 8, 2)->nullable();

            // Section 5 — Kode Hambatan
            $table->string('obstacle_code', 5)->nullable();
            $table->text('obstacle_description')->nullable(); // Required when AL

            // Status
            $table->string('status', 20)->default('ongoing'); // ongoing | on_hold | completed

            // Section 6 — Manager Teknik (chosen manually)
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('manager_name')->nullable();
            $table->string('manager_role', 50)->nullable();
            $table->longText('manager_signature')->nullable();
            $table->unsignedBigInteger('manager_signed_by')->nullable();
            $table->timestamp('manager_signed_at')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('manager_id')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('manager_signed_by')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('created_by_id')->references('id')->on('local_users')->nullOnDelete();

            $table->index('report_date');
            $table->index('status');
            $table->index('damage_category');
            $table->index('obstacle_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_damage_reports');
    }
};
