<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert tfp_localizer from fixed-column schema to dynamic JSON schema.
 * Records gain `columns_config`; items gain `values` + `merge_map`; legacy
 * panel columns are dropped. Destructive — dev only, no backfill (confirmed).
 */
return new class extends Migration {
    private function defaultColumnsConfig(): array
    {
        $single = [['key' => 'value',  'label' => 'Nilai']];
        $io     = [['key' => 'input',  'label' => 'Input'], ['key' => 'output', 'label' => 'Output']];

        return [
            ['id' => 'panel_lz01',        'label' => 'Panel LZ 01',           'sub_columns' => $single],
            ['id' => 'panel_cos_lz02',    'label' => 'Panel COS (LZ 02)',     'sub_columns' => $io],
            ['id' => 'panel_lz03',        'label' => 'Panel LZ 03',           'sub_columns' => $single],
            ['id' => 'panel_cos_lz04',    'label' => 'Panel COS (LZ 04)',     'sub_columns' => $io],
            ['id' => 'panel_mlat_ru04',   'label' => 'Panel MLAT RU 04',      'sub_columns' => $single],
        ];
    }

    public function up(): void
    {
        Schema::table('tfp_localizer_records', function (Blueprint $table) {
            $table->jsonb('columns_config')->nullable()->after('location');
        });

        Schema::table('tfp_localizer_items', function (Blueprint $table) {
            $table->jsonb('values')->nullable()->after('unit');
            $table->jsonb('merge_map')->nullable()->after('is_disabled_map');
        });

        DB::table('tfp_localizer_records')
            ->whereNull('columns_config')
            ->update(['columns_config' => json_encode($this->defaultColumnsConfig())]);

        DB::table('tfp_localizer_items')->update([
            'values'          => null,
            'is_disabled_map' => null,
        ]);

        Schema::table('tfp_localizer_items', function (Blueprint $table) {
            $table->dropColumn([
                'panel_lz01',
                'panel_cos_lz02_input', 'panel_cos_lz02_output',
                'panel_lz03',
                'panel_cos_lz04_input', 'panel_cos_lz04_output',
                'panel_mlat_ru04',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('tfp_localizer_items', function (Blueprint $table) {
            $table->string('panel_lz01', 50)->nullable();
            $table->string('panel_cos_lz02_input', 50)->nullable();
            $table->string('panel_cos_lz02_output', 50)->nullable();
            $table->string('panel_lz03', 50)->nullable();
            $table->string('panel_cos_lz04_input', 50)->nullable();
            $table->string('panel_cos_lz04_output', 50)->nullable();
            $table->string('panel_mlat_ru04', 50)->nullable();
            $table->dropColumn(['values', 'merge_map']);
        });

        Schema::table('tfp_localizer_records', function (Blueprint $table) {
            $table->dropColumn('columns_config');
        });
    }
};
