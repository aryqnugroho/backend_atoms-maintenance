<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('grounding_report_technicians', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grounding_report_record_id');
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->string('technician_name');
            $table->longText('technician_signature')->nullable();
            $table->unsignedBigInteger('technician_signed_by')->nullable();
            $table->timestamp('technician_signed_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('grounding_report_record_id', 'grnd_tech_record_fk')
                ->references('id')->on('grounding_report_records')->cascadeOnDelete();
            $table->foreign('technician_id', 'grnd_tech_user_fk')
                ->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('technician_signed_by', 'grnd_tech_signer_fk')
                ->references('id')->on('local_users')->nullOnDelete();
            $table->index('grounding_report_record_id', 'grnd_tech_record_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grounding_report_technicians');
    }
};
