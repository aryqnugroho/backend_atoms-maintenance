<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Items for VHF Ground Check Form 2 — "Pelaksanaan Kegiatan Pemeliharaan
 * Pencegahan" (Preventive Maintenance Activity).
 *
 * This table backs a hierarchical maintenance checklist with up to two header
 * levels per row plus the actual parameter row:
 *
 *   Section header  (e.g. "1. Membersihkan bagian dalam / modul peralatan")
 *     ├── Item row  (a. Transmitter — Bersih)
 *     └── Item row  (b. Receiver — Bersih)
 *   Subsection header (e.g. "a. Transmitter" inside section 2)
 *     ├── Item row  (Forward Power Output — WATT METER — 90 / 90)
 *     ...
 *
 * Columns mirror the paper form: TOLERANSI / INTERFACE / TX1 / TX2 / KETERANGAN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ground_check_vhf_maintenance_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ground_check_vhf_record_id');

            // Hierarchical layout
            $table->integer('section_number')->nullable();       // 1, 2, 3, 4
            $table->string('section_label')->nullable();         // "Membersihkan bagian dalam / modul peralatan"
            $table->string('subsection_label')->nullable();      // "Transmitter" / "Receiver" (only used inside section 2)
            $table->string('item_code')->nullable();             // "a", "b", "c", "d"
            $table->string('parameter_name');                    // "Transmitter" / "Forward Power Output" / "AC"

            // Data columns
            $table->string('toleransi')->nullable();             // "Bersih" / "Sudah"
            $table->string('interface_value')->nullable();       // "WATT METER"
            $table->string('tx1_value')->nullable();
            $table->string('tx2_value')->nullable();
            $table->text('keterangan')->nullable();

            // Row classification
            $table->boolean('is_section_header')->default(false);     // banner row "1." / "2." / "3." / "4."
            $table->boolean('is_subsection_header')->default(false);  // "a. Transmitter" inside section 2
            $table->string('input_type', 30)->default('text');        // text | numeric | header

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('ground_check_vhf_record_id', 'gcv_mitems_record_fk')
                ->references('id')
                ->on('ground_check_vhf_records')
                ->onDelete('cascade');
            $table->index(['ground_check_vhf_record_id', 'sort_order'], 'gcv_mitems_record_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ground_check_vhf_maintenance_items');
    }
};
