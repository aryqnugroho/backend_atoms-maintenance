<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cnsd_readiness_technicians — per-record technician roster.
 *
 * Stores the cached list of CNSD technicians on duty when the readiness record
 * was created. Each technician signs their own row; signatures are immutable
 * and cannot be delegated.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_readiness_technicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('readiness_record_id')
                ->constrained('cnsd_readiness_records')
                ->cascadeOnDelete();

            // Optional FK — null when the rostering user lacks a local_users mirror
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->string('technician_name'); // cached at create-time, source of truth
            $table->longText('technician_signature')->nullable();
            $table->unsignedBigInteger('technician_signed_by')->nullable();
            $table->timestamp('technician_signed_at')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('technician_id')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('technician_signed_by')->references('id')->on('local_users')->nullOnDelete();

            $table->index(['readiness_record_id', 'sort_order']);
            $table->index('technician_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_readiness_technicians');
    }
};
