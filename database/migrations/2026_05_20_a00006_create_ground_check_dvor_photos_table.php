<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ground_check_dvor_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ground_check_dvor_record_id');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('caption')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->unsignedBigInteger('uploaded_by_id')->nullable();
            $table->string('uploaded_by_name')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('ground_check_dvor_record_id', 'gcd_photos_record_fk')
                ->references('id')->on('ground_check_dvor_records')->cascadeOnDelete();
            $table->index(['ground_check_dvor_record_id', 'sort_order'], 'gcd_photos_record_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ground_check_dvor_photos');
    }
};
