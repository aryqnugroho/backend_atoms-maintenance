<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tfp_localizer_technicians', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('localizer_record_id');
            $table->foreign('localizer_record_id')->references('id')->on('tfp_localizer_records')->cascadeOnDelete();
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->foreign('technician_id')->references('id')->on('local_users')->nullOnDelete();
            $table->string('technician_name');
            $table->longText('technician_signature')->nullable();
            $table->unsignedBigInteger('technician_signed_by')->nullable();
            $table->foreign('technician_signed_by')->references('id')->on('local_users')->nullOnDelete();
            $table->timestamp('technician_signed_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('localizer_record_id');
        });
    }
    public function down(): void { Schema::dropIfExists('tfp_localizer_technicians'); }
};
