<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('grounding_report_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grounding_report_record_id');
            $table->string('section_name', 50); // 'VISUAL' or 'PENGUKURAN'
            $table->integer('item_number');
            $table->string('item_name', 200);
            $table->string('standard', 50)->nullable(); // for PENGUKURAN: ≤ 1 Ω, -
            $table->string('availability', 20)->nullable(); // Ada | Tidak Ada (VISUAL only)
            $table->string('condition', 20)->nullable(); // Baik | Tidak Baik
            $table->string('notes')->nullable(); // free text
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('grounding_report_record_id', 'grnd_item_record_fk')
                ->references('id')->on('grounding_report_records')->cascadeOnDelete();
            $table->index('grounding_report_record_id', 'grnd_item_record_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grounding_report_items');
    }
};
