<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('wo_number', 50)->unique();
            $table->string('wo_type', 20); // shift or personal
            $table->string('division', 10); // CNSD or TFP
            $table->string('shift_type', 10); // pagi, siang, malam
            $table->date('shift_date');
            $table->text('description');
            $table->string('status', 20)->default('open'); // open, in_progress, pending, closed
            
            // Personnel references
            $table->unsignedBigInteger('manager_id');
            $table->unsignedBigInteger('supervisor_id');
            $table->unsignedBigInteger('assigned_technician_id')->nullable();
            $table->foreign('manager_id')->references('id')->on('local_users');
            $table->foreign('supervisor_id')->references('id')->on('local_users');
            $table->foreign('assigned_technician_id')->references('id')->on('local_users');
            
            // Snapshots
            $table->string('manager_name_snapshot');
            $table->string('supervisor_name_snapshot');
            
            // Completion data
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('completion_status', 30)->nullable();
            $table->text('notes_kendala')->nullable();
            $table->text('notes_usulan')->nullable();
            $table->text('notes_pemberi_tugas')->nullable();
            
            // Audit
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('local_users');
            $table->timestamp('closed_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['division', 'shift_date']);
            $table->index('status');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
