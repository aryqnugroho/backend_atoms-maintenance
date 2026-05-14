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
        Schema::table('work_orders', function (Blueprint $table) {
            $table->string('status', 20)->default('ongoing')->change();
            $table->unsignedBigInteger('supervisor_id')->nullable()->change();
            $table->string('supervisor_name_snapshot')->nullable()->change();

            $table->unsignedBigInteger('shift_id')->nullable()->after('shift_date');
            $table->boolean('has_supervisor')->default(true)->after('assigned_technician_id');

            $table->string('mt_name')->nullable()->after('supervisor_name_snapshot');
            $table->longText('mt_signature')->nullable()->after('mt_name');
            $table->unsignedBigInteger('mt_signed_by')->nullable()->after('mt_signature');
            $table->timestamp('mt_signed_at')->nullable()->after('mt_signed_by');

            $table->string('supervisor_name')->nullable()->after('mt_signed_at');
            $table->longText('supervisor_signature')->nullable()->after('supervisor_name');
            $table->unsignedBigInteger('supervisor_signed_by')->nullable()->after('supervisor_signature');
            $table->timestamp('supervisor_signed_at')->nullable()->after('supervisor_signed_by');

            $table->string('technician_name')->nullable()->after('supervisor_signed_at');
            $table->longText('technician_signature')->nullable()->after('technician_name');
            $table->unsignedBigInteger('technician_signed_by')->nullable()->after('technician_signature');
            $table->timestamp('technician_signed_at')->nullable()->after('technician_signed_by');

            $table->foreign('mt_signed_by')->references('id')->on('local_users');
            $table->foreign('supervisor_signed_by')->references('id')->on('local_users');
            $table->foreign('technician_signed_by')->references('id')->on('local_users');

            $table->index(['shift_id', 'shift_date', 'shift_type']);
            $table->index('has_supervisor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['mt_signed_by']);
            $table->dropForeign(['supervisor_signed_by']);
            $table->dropForeign(['technician_signed_by']);

            $table->dropIndex(['shift_id', 'shift_date', 'shift_type']);
            $table->dropIndex(['has_supervisor']);

            $table->dropColumn([
                'shift_id',
                'has_supervisor',
                'mt_name',
                'mt_signature',
                'mt_signed_by',
                'mt_signed_at',
                'supervisor_name',
                'supervisor_signature',
                'supervisor_signed_by',
                'supervisor_signed_at',
                'technician_name',
                'technician_signature',
                'technician_signed_by',
                'technician_signed_at',
            ]);

            $table->unsignedBigInteger('supervisor_id')->nullable(false)->change();
            $table->string('supervisor_name_snapshot')->nullable(false)->change();
            $table->string('status', 20)->default('open')->change();
        });
    }
};
