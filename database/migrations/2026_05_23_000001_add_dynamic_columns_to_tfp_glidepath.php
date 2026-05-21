<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert tfp_glidepath from fixed-column schema to dynamic JSON schema.
 * Records gain `columns_config`; items gain `values` + `merge_map`; legacy
 * panel_gp01 column is dropped. Destructive — dev only, no backfill (confirmed).
 */
return new class extends Migration {
    private function defaultColumnsConfig(): array
    {
        $single = [['key' => 'value', 'label' => 'Nilai']];

        return [
            ['id' => 'panel_gp01', 'label' => 'Panel GP 01', 'sub_columns' => $single],
        ];
    }

    public function up(): void
    {
        Schema::table('tfp_glidepath_records', function (Blueprint $table) {
            $table->jsonb('columns_config')->nullable()->after('location');
        });

        Schema::table('tfp_glidepath_items', function (Blueprint $table) {
            $table->jsonb('values')->nullable()->after('unit');
            $table->jsonb('merge_map')->nullable()->after('is_disabled_map');
        });

        DB::table('tfp_glidepath_records')
            ->whereNull('columns_config')
            ->update(['columns_config' => json_encode($this->defaultColumnsConfig())]);

        DB::table('tfp_glidepath_items')->update([
            'values'          => null,
            'is_disabled_map' => null,
        ]);

        Schema::table('tfp_glidepath_items', function (Blueprint $table) {
            $table->dropColumn(['panel_gp01']);
        });
    }

    public function down(): void
    {
        Schema::table('tfp_glidepath_items', function (Blueprint $table) {
            $table->string('panel_gp01', 50)->nullable();
            $table->dropColumn(['values', 'merge_map']);
        });

        Schema::table('tfp_glidepath_records', function (Blueprint $table) {
            $table->dropColumn('columns_config');
        });
    }
};
