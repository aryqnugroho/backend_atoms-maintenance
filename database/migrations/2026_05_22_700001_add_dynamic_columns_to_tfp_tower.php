<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert tfp_tower from fixed-column schema to dynamic JSON schema.
 * Records gain `columns_config`; items gain `values` + `merge_map`; legacy
 * panel columns are dropped. Destructive — dev only, no backfill (confirmed).
 */
return new class extends Migration {
    private function defaultColumnsConfig(): array
    {
        $single = [['key' => 'value',  'label' => 'Nilai']];
        $io     = [['key' => 'input',  'label' => 'Input'], ['key' => 'output', 'label' => 'Output']];

        return [
            ['id' => 'panel_a10',          'label' => 'Panel A 10',           'sub_columns' => $single],
            ['id' => 'panel_a11',          'label' => 'Panel A 11',           'sub_columns' => $single],
            ['id' => 'panel_ats_a13',      'label' => 'Panel ATS (A 13)',     'sub_columns' => $io],
            ['id' => 'panel_a14',          'label' => 'Panel A 14',           'sub_columns' => $single],
            ['id' => 'panel_a16',          'label' => 'Panel A 16',           'sub_columns' => $single],
            ['id' => 'panel_a17',          'label' => 'Panel A 17',           'sub_columns' => $single],
            ['id' => 'panel_a18',          'label' => 'Panel A 18',           'sub_columns' => $single],
            ['id' => 'panel_a19',          'label' => 'Panel A 19',           'sub_columns' => $single],
            ['id' => 'panel_a20',          'label' => 'Panel A 20',           'sub_columns' => $single],
            ['id' => 'panel_milat_ru1213', 'label' => 'Panel MILAT (RU 12/13)', 'sub_columns' => $single],
        ];
    }

    public function up(): void
    {
        Schema::table('tfp_tower_records', function (Blueprint $table) {
            $table->jsonb('columns_config')->nullable()->after('location');
        });

        Schema::table('tfp_tower_items', function (Blueprint $table) {
            $table->jsonb('values')->nullable()->after('unit');
            $table->jsonb('merge_map')->nullable()->after('is_disabled_map');
        });

        DB::table('tfp_tower_records')
            ->whereNull('columns_config')
            ->update(['columns_config' => json_encode($this->defaultColumnsConfig())]);

        DB::table('tfp_tower_items')->update([
            'values'          => null,
            'is_disabled_map' => null,
        ]);

        Schema::table('tfp_tower_items', function (Blueprint $table) {
            $table->dropColumn([
                'panel_a10', 'panel_a11',
                'panel_ats_a13_input', 'panel_ats_a13_output',
                'panel_a14', 'panel_a16', 'panel_a17', 'panel_a18', 'panel_a19', 'panel_a20',
                'panel_milat_ru1213',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('tfp_tower_items', function (Blueprint $table) {
            $table->string('panel_a10', 50)->nullable();
            $table->string('panel_a11', 50)->nullable();
            $table->string('panel_ats_a13_input', 50)->nullable();
            $table->string('panel_ats_a13_output', 50)->nullable();
            $table->string('panel_a14', 50)->nullable();
            $table->string('panel_a16', 50)->nullable();
            $table->string('panel_a17', 50)->nullable();
            $table->string('panel_a18', 50)->nullable();
            $table->string('panel_a19', 50)->nullable();
            $table->string('panel_a20', 50)->nullable();
            $table->string('panel_milat_ru1213', 50)->nullable();
            $table->dropColumn(['values', 'merge_map']);
        });

        Schema::table('tfp_tower_records', function (Blueprint $table) {
            $table->dropColumn('columns_config');
        });
    }
};
