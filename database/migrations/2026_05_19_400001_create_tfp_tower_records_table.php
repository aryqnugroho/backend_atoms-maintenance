<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tfp_tower_records', function (Blueprint $table) {
            $table->id();
            $table->string('form_number', 80)->unique();
            $table->string('form_type', 30)->default('TOWER');
            $table->date('date');
            $table->string('day_name', 20)->nullable();
            $table->string('time_filled', 10)->nullable();
            $table->string('shift_type', 10);
            $table->string('location', 100)->default('GEDUNG TOWER');
            $table->string('status', 20)->default('ongoing');

            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('manager_name')->nullable();
            $table->longText('manager_signature')->nullable();
            $table->unsignedBigInteger('manager_signed_by')->nullable();
            $table->timestamp('manager_signed_at')->nullable();

            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->string('supervisor_name')->nullable();
            $table->longText('supervisor_signature')->nullable();
            $table->unsignedBigInteger('supervisor_signed_by')->nullable();
            $table->timestamp('supervisor_signed_at')->nullable();

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
            $table->index(['form_type', 'date']);
            $table->index('status');
        });

        \Illuminate\Support\Facades\DB::statement(
            'CREATE UNIQUE INDEX tfp_tower_records_unique_form_per_shift '
            . 'ON tfp_tower_records (form_type, date, shift_type) '
            . 'WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement('DROP INDEX IF EXISTS tfp_tower_records_unique_form_per_shift');
        Schema::dropIfExists('tfp_tower_records');
    }
};
