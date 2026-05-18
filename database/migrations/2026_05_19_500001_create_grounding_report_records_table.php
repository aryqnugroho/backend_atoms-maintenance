<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('grounding_report_records', function (Blueprint $table) {
            $table->id();
            $table->string('report_number', 80)->unique();
            $table->date('date');
            $table->string('day_name', 20)->nullable();
            $table->string('time_filled', 10)->nullable();
            $table->string('shift_type', 10); // pagi | siang | malam
            $table->string('work_unit', 100)->default('Cabang Surabaya');
            $table->string('equipment_name', 200);
            $table->string('equipment_location', 200);
            $table->string('status', 20)->default('ongoing'); // ongoing | on_hold | completed
            // Manager Teknik (nullable)
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('manager_name')->nullable();
            $table->longText('manager_signature')->nullable();
            $table->unsignedBigInteger('manager_signed_by')->nullable();
            $table->timestamp('manager_signed_at')->nullable();
            // Supervisor TFP (nullable)
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->string('supervisor_name')->nullable();
            $table->longText('supervisor_signature')->nullable();
            $table->unsignedBigInteger('supervisor_signed_by')->nullable();
            $table->timestamp('supervisor_signed_at')->nullable();
            // Audit
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('manager_id')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('supervisor_id')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('manager_signed_by')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('supervisor_signed_by')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('created_by_id')->references('id')->on('local_users')->nullOnDelete();

            $table->index(['date', 'shift_type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grounding_report_records');
    }
};
