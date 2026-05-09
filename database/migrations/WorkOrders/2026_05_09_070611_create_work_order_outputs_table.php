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
        Schema::create('work_order_outputs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id');
            $table->string('output_type', 50); // e.g., 'Document', 'Part Replaced', 'Software Updated'
            $table->text('description');
            
            // Optional file attachment
            $table->string('file_path')->nullable();
            
            $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_outputs');
    }
};
