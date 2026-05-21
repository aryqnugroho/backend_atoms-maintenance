<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert tfp_aob_lt12 from fixed-column schema to the dynamic JSON-based
 * schema used by tfp_aob_ground. Records gain `columns_config`; items gain
 * `values` + `merge_map`; the six legacy panel columns are dropped.
 *
 * Destructive — no backfill. Project is still in dev so existing rows do not
 * need to be preserved. Confirmed by the user before writing this migration.
 */
return new class extends Migration {
    private function defaultColumnsConfig(): array
    {
        // 6 panels, each with a single "Nilai" sub-column — matches the paper form layout.
        $single = [['key' => 'value', 'label' => 'Nilai']];

        return [
            ['id' => 'panel_a05_app_room',   'label' => 'Panel A 05 APP Room',   'sub_columns' => $single],
            ['id' => 'panel_a06_app_room',   'label' => 'Panel A 06 APP Room',   'sub_columns' => $single],
            ['id' => 'panel_a07_app_room',   'label' => 'Panel A 07 APP Room',   'sub_columns' => $single],
            ['id' => 'panel_a08_gudang_lt1', 'label' => 'Panel A 08 Gudang Lt 1','sub_columns' => $single],
            ['id' => 'panel_a22_gudang_lt1', 'label' => 'Panel A 22 Gudang Lt 1','sub_columns' => $single],
            ['id' => 'panel_a09_amsc_room',  'label' => 'Panel A 09 AMSC Room',  'sub_columns' => $single],
        ];
    }

    public function up(): void
    {
        Schema::table('tfp_aob_lt12_records', function (Blueprint $table) {
            $table->jsonb('columns_config')->nullable()->after('location');
        });

        Schema::table('tfp_aob_lt12_items', function (Blueprint $table) {
            $table->jsonb('values')->nullable()->after('unit');
            $table->jsonb('merge_map')->nullable()->after('is_disabled_map');
        });

        // Seed columns_config for any existing record
        DB::table('tfp_aob_lt12_records')
            ->whereNull('columns_config')
            ->update(['columns_config' => json_encode($this->defaultColumnsConfig())]);

        // Destructive — wipe legacy fixed columns and any is_disabled_map keyed
        // by the old names. Existing values are not preserved.
        DB::table('tfp_aob_lt12_items')->update([
            'values'          => null,
            'is_disabled_map' => null,
        ]);

        Schema::table('tfp_aob_lt12_items', function (Blueprint $table) {
            $table->dropColumn([
                'panel_a05_app_room',
                'panel_a06_app_room',
                'panel_a07_app_room',
                'panel_a08_gudang_lt1',
                'panel_a22_gudang_lt1',
                'panel_a09_amsc_room',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('tfp_aob_lt12_items', function (Blueprint $table) {
            $table->string('panel_a05_app_room', 50)->nullable();
            $table->string('panel_a06_app_room', 50)->nullable();
            $table->string('panel_a07_app_room', 50)->nullable();
            $table->string('panel_a08_gudang_lt1', 50)->nullable();
            $table->string('panel_a22_gudang_lt1', 50)->nullable();
            $table->string('panel_a09_amsc_room', 50)->nullable();
            $table->dropColumn(['values', 'merge_map']);
        });

        Schema::table('tfp_aob_lt12_records', function (Blueprint $table) {
            $table->dropColumn('columns_config');
        });
    }
};
