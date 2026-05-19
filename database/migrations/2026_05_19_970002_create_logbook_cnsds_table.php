<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('logbook_cnsds', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();

            // Manager Teknik signature (role-based delegation, immutable)
            $table->unsignedBigInteger('manager_signed_by_id')->nullable();
            $table->string('manager_signed_by_name', 120)->nullable();
            $table->string('manager_signed_by_role', 50)->nullable();
            $table->longText('manager_signature')->nullable();
            $table->timestamp('manager_signed_at')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name', 120)->nullable();

            $table->timestamps();

            $table->foreign('manager_signed_by_id')->references('id')->on('local_users')->nullOnDelete();
            $table->foreign('created_by_id')->references('id')->on('local_users')->nullOnDelete();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logbook_cnsds');
    }
};
