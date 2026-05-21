<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert tfp_dvor from fixed-column schema to dynamic JSON schema.
 * Records gain `columns_config`; items gain `values` + `merge_map`; legacy
 * panel columns are dropped. Destructive — dev only, no backfill (confirmed).
 */
return new class extends Migration {
    private function defaultColumnsConfig(): array
    {
        $single = [['key' => 'value',  'label' => 'Nilai']];
        $io     = [['key' => 'input',  'label' => 'Input'], ['key' => 'output', 'label' => 'Output']];

        return [
            ['id' => 'panel_d01',     'label' => 'Panel D.01',              'sub_columns' => $single],
            ['id' => 'panel_d03',     'label' => 'Panel D.03 CCTV Indoor',  'sub_columns' => $single],
            ['id' => 'panel_d04',     'label' => 'Panel D.04 CCTV Outdoor', 'sub_columns' => $single],
            ['id' => 'panel_d05',     'label' => 'Panel Input D.05',        'sub_columns' => $single],
            ['id' => 'panel_ats_d06', 'label' => 'Panel ATS/AMF (D.06)',    'sub_columns' => $io],
        ];
    }

    public function up(): void
    {
        Schema::table('tfp_dvor_records', function (Blueprint $table) {
            $table->jsonb('columns_config')->nullable()->after('location');
        });

        Schema::table('tfp_dvor_items', function (Blueprint $table) {
            $table->jsonb('values')->nullable()->after('unit');
            $table->jsonb('merge_map')->nullable()->after('is_disabled_map');
        });

        DB::table('tfp_dvor_records')
            ->whereNull('columns_config')
            ->update(['columns_config' => json_encode($this->defaultColumnsConfig())]);

        DB::table('tfp_dvor_items')->update([
            'values'          => null,
            'is_disabled_map' => null,
        ]);

        Schema::table('tfp_dvor_items', function (Blueprint $table) {
            $table->dropColumn([
                'panel_d01', 'panel_d03', 'panel_d04', 'panel_d05',
                'panel_ats_d06_input', 'panel_ats_d06_output',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('tfp_dvor_items', function (Blueprint $table) {
            $table->string('panel_d01', 50)->nullable();
            $table->string('panel_d03', 50)->nullable();
            $table->string('panel_d04', 50)->nullable();
            $table->string('panel_d05', 50)->nullable();
            $table->string('panel_ats_d06_input', 50)->nullable();
            $table->string('panel_ats_d06_output', 50)->nullable();
            $table->dropColumn(['values', 'merge_map']);
        });

        Schema::table('tfp_dvor_records', function (Blueprint $table) {
            $table->dropColumn('columns_config');
        });
    }
};
