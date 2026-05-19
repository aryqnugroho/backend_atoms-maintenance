<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('logbook_tfp_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logbook_tfp_id');
            $table->string('shift', 10);              // pagi | siang | malam
            $table->string('time', 10)->nullable();   // HH:MM
            $table->text('activity');

            $table->timestamps();

            $table->foreign('logbook_tfp_id', 'ltfp_notes_logbook_fk')
                ->references('id')->on('logbook_tfps')->cascadeOnDelete();

            $table->index('logbook_tfp_id', 'ltfp_notes_logbook_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logbook_tfp_notes');
    }
};
