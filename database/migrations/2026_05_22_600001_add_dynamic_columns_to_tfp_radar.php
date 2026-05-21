<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert tfp_radar from fixed-column schema to dynamic JSON schema.
 * Records gain `columns_config`; items gain `values` + `merge_map`; legacy
 * panel columns are dropped. Destructive — dev only, no backfill (confirmed).
 */
return new class extends Migration {
    private function defaultColumnsConfig(): array
    {
        $single = [['key' => 'value',  'label' => 'Nilai']];
        $io     = [['key' => 'input',  'label' => 'Input'], ['key' => 'output', 'label' => 'Output']];

        return [
            ['id' => 'panel_rd01',     'label' => 'Panel RD 01',       'sub_columns' => $single],
            ['id' => 'panel_rd02',     'label' => 'Panel RD 02',       'sub_columns' => $single],
            ['id' => 'panel_cos_rd03', 'label' => 'Panel COS (RD 03)', 'sub_columns' => $io],
            ['id' => 'ups_topaz',      'label' => 'UPS TOPAZ',         'sub_columns' => $io],
            ['id' => 'panel_rd04',     'label' => 'Panel RD 04',       'sub_columns' => $single],
            ['id' => 'panel_rd05',     'label' => 'Panel RD 05',       'sub_columns' => $single],
            ['id' => 'panel_rd06',     'label' => 'Panel RD 06',       'sub_columns' => $single],
            ['id' => 'panel_rd07',     'label' => 'Panel RD 07',       'sub_columns' => $single],
            ['id' => 'panel_rd08',     'label' => 'Panel RD 08',       'sub_columns' => $single],
        ];
    }

    public function up(): void
    {
        Schema::table('tfp_radar_records', function (Blueprint $table) {
            $table->jsonb('columns_config')->nullable()->after('location');
        });

        Schema::table('tfp_radar_items', function (Blueprint $table) {
            $table->jsonb('values')->nullable()->after('unit');
            $table->jsonb('merge_map')->nullable()->after('is_disabled_map');
        });

        DB::table('tfp_radar_records')
            ->whereNull('columns_config')
            ->update(['columns_config' => json_encode($this->defaultColumnsConfig())]);

        DB::table('tfp_radar_items')->update([
            'values'          => null,
            'is_disabled_map' => null,
        ]);

        Schema::table('tfp_radar_items', function (Blueprint $table) {
            $table->dropColumn([
                'panel_rd01', 'panel_rd02',
                'panel_cos_rd03_input', 'panel_cos_rd03_output',
                'ups_topaz_input', 'ups_topaz_output',
                'panel_rd04', 'panel_rd05', 'panel_rd06', 'panel_rd07', 'panel_rd08',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('tfp_radar_items', function (Blueprint $table) {
            $table->string('panel_rd01', 50)->nullable();
            $table->string('panel_rd02', 50)->nullable();
            $table->string('panel_cos_rd03_input', 50)->nullable();
            $table->string('panel_cos_rd03_output', 50)->nullable();
            $table->string('ups_topaz_input', 50)->nullable();
            $table->string('ups_topaz_output', 50)->nullable();
            $table->string('panel_rd04', 50)->nullable();
            $table->string('panel_rd05', 50)->nullable();
            $table->string('panel_rd06', 50)->nullable();
            $table->string('panel_rd07', 50)->nullable();
            $table->string('panel_rd08', 50)->nullable();
            $table->dropColumn(['values', 'merge_map']);
        });

        Schema::table('tfp_radar_records', function (Blueprint $table) {
            $table->dropColumn('columns_config');
        });
    }
};
