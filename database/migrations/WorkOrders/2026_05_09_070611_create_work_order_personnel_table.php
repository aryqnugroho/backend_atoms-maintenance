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
        Schema::create('work_order_personnel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role_label', 50); // e.g., 'Teknisi 1', 'Teknisi 2'
            
            $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('local_users');
            
            $table->timestamps();
            
            // A user can only be assigned once per role per WO, or just once per WO
            $table->unique(['work_order_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_personnel');
    }
};
