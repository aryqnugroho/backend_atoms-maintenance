<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert tfp_transmitter_tx from fixed-column schema to dynamic JSON schema.
 * Records gain `columns_config`; items gain `values` + `merge_map`; legacy
 * panel columns are dropped. Destructive — dev only, no backfill (confirmed).
 */
return new class extends Migration {
    private function defaultColumnsConfig(): array
    {
        $single = [['key' => 'value',  'label' => 'Nilai']];
        $io     = [['key' => 'input',  'label' => 'Input'], ['key' => 'output', 'label' => 'Output']];

        return [
            ['id' => 'panel_tx01',            'label' => 'Panel TX 01',           'sub_columns' => $single],
            ['id' => 'panel_tx02',            'label' => 'Panel TX 02',           'sub_columns' => $single],
            ['id' => 'panel_cos_tx03',        'label' => 'Panel COS (TX 03)',     'sub_columns' => $io],
            ['id' => 'panel_output_ups_tx04', 'label' => 'Panel Output UPS TX 04','sub_columns' => $single],
            ['id' => 'panel_ups_tx07',        'label' => 'Panel UPS (TX 07)',     'sub_columns' => $io],
            ['id' => 'panel_ac_tx06',         'label' => 'Panel AC (TX 06)',      'sub_columns' => $single],
            ['id' => 'ups_piller',            'label' => 'UPS PILLER',            'sub_columns' => $io],
            ['id' => 'panel_milat_ru11',      'label' => 'Panel MILAT (RU 11)',   'sub_columns' => $single],
        ];
    }

    public function up(): void
    {
        Schema::table('tfp_transmitter_tx_records', function (Blueprint $table) {
            $table->jsonb('columns_config')->nullable()->after('location');
        });

        Schema::table('tfp_transmitter_tx_items', function (Blueprint $table) {
            $table->jsonb('values')->nullable()->after('unit');
            $table->jsonb('merge_map')->nullable()->after('is_disabled_map');
        });

        DB::table('tfp_transmitter_tx_records')
            ->whereNull('columns_config')
            ->update(['columns_config' => json_encode($this->defaultColumnsConfig())]);

        // Destructive — wipe legacy values and is_disabled_map
        DB::table('tfp_transmitter_tx_items')->update([
            'values'          => null,
            'is_disabled_map' => null,
        ]);

        Schema::table('tfp_transmitter_tx_items', function (Blueprint $table) {
            $table->dropColumn([
                'panel_tx01', 'panel_tx02',
                'panel_cos_tx03_input', 'panel_cos_tx03_output',
                'panel_output_ups_tx04',
                'panel_ups_tx07_input', 'panel_ups_tx07_output',
                'panel_ac_tx06',
                'ups_piller_input', 'ups_piller_output',
                'panel_milat_ru11',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('tfp_transmitter_tx_items', function (Blueprint $table) {
            $table->string('panel_tx01', 50)->nullable();
            $table->string('panel_tx02', 50)->nullable();
            $table->string('panel_cos_tx03_input', 50)->nullable();
            $table->string('panel_cos_tx03_output', 50)->nullable();
            $table->string('panel_output_ups_tx04', 50)->nullable();
            $table->string('panel_ups_tx07_input', 50)->nullable();
            $table->string('panel_ups_tx07_output', 50)->nullable();
            $table->string('panel_ac_tx06', 50)->nullable();
            $table->string('ups_piller_input', 50)->nullable();
            $table->string('ups_piller_output', 50)->nullable();
            $table->string('panel_milat_ru11', 50)->nullable();
            $table->dropColumn(['values', 'merge_map']);
        });

        Schema::table('tfp_transmitter_tx_records', function (Blueprint $table) {
            $table->dropColumn('columns_config');
        });
    }
};
