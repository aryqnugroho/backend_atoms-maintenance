<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnsd_vccs_freq_meter_technicians', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vccs_freq_meter_record_id');
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->string('technician_name');
            $table->longText('technician_signature')->nullable();
            $table->unsignedBigInteger('technician_signed_by')->nullable();
            $table->timestamp('technician_signed_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Explicit short FK names — auto-gen exceeds PG 63-char limit
            $table->foreign('vccs_freq_meter_record_id', 'cnsd_vccs_freq_tech_rec_fk')
                ->references('id')->on('cnsd_vccs_freq_meter_records')->cascadeOnDelete();
            $table->foreign('technician_id', 'cnsd_vccs_freq_tech_user_fk')
                ->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('technician_signed_by', 'cnsd_vccs_freq_tech_signer_fk')
                ->references('id')->on('local_users')->nullOnDelete();

            $table->index('vccs_freq_meter_record_id', 'cnsd_vccs_freq_tech_rec_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnsd_vccs_freq_meter_technicians');
    }
};
