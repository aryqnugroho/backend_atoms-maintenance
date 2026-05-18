<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tfp_aob_lt12_facilities — facility condition rows for the TFP AOB Lantai 1 & 2
 * Performance Check form.
 *
 * Each row represents one facility item (e.g. Catu Daya Listrik, AC 09, etc.)
 * that the technician checks during the shift. Seeded from TfpAobLt12Template
 * at create-time.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tfp_aob_lt12_facilities', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('aob_lt12_record_id');
            $table->foreign('aob_lt12_record_id')
                ->references('id')
                ->on('tfp_aob_lt12_records')
                ->cascadeOnDelete();

            $table->string('facility_name', 100);
            $table->string('kondisi', 20)->nullable();  // Baik | Normal | Tidak Baik
            $table->text('keterangan')->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('aob_lt12_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tfp_aob_lt12_facilities');
    }
};
