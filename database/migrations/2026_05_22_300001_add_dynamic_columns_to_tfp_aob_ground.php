<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert the TFP AOB Lantai Ground items table from 8 fixed cell columns
 * into a dynamic JSON-based schema so that managers can add/rename/remove
 * panels and toggle disabled / merged cells from the UI (Excel-like editor).
 *
 * Records gain `columns_config` (JSONB) describing the panels and their
 * sub-columns in display order. Items gain `values`, `merge_map`, and
 * keep `is_disabled_map` — all keyed by composite "panel_id.sub_column_key"
 * (e.g. "panel_cos_a03.input").
 *
 * The 8 fixed cell columns on items are migrated into `values` and then
 * dropped. is_disabled_map keys are re-written from the legacy
 * "panel_cos_a03_input" naming to the new "panel_cos_a03.input" naming.
 *
 * Default columns_config (applied to every existing record) preserves the
 * four panels exactly as they appeared on the paper form.
 */
return new class extends Migration {
    /**
     * Default columns_config encoding the four legacy panels (COS A03, ATS A12,
     * UPS TESCOM A, UPS TESCOM B), each with Input + Output sub-columns.
     */
    private function defaultColumnsConfig(): array
    {
        $io = [
            ['key' => 'input',  'label' => 'Input'],
            ['key' => 'output', 'label' => 'Output'],
        ];

        return [
            ['id' => 'panel_cos_a03', 'label' => 'Panel COS (A 03)', 'sub_columns' => $io],
            ['id' => 'panel_ats_a12', 'label' => 'Panel ATS (A 12)', 'sub_columns' => $io],
            ['id' => 'ups_tescom_a',  'label' => 'UPS TESCOM A',     'sub_columns' => $io],
            ['id' => 'ups_tescom_b',  'label' => 'UPS TESCOM B',     'sub_columns' => $io],
        ];
    }

    /**
     * Mapping: legacy fixed column → new composite key "{panel_id}.{sub_col}".
     */
    private function legacyColumnMap(): array
    {
        return [
            'panel_cos_a03_input'  => 'panel_cos_a03.input',
            'panel_cos_a03_output' => 'panel_cos_a03.output',
            'panel_ats_a12_input'  => 'panel_ats_a12.input',
            'panel_ats_a12_output' => 'panel_ats_a12.output',
            'ups_tescom_a_input'   => 'ups_tescom_a.input',
            'ups_tescom_a_output'  => 'ups_tescom_a.output',
            'ups_tescom_b_input'   => 'ups_tescom_b.input',
            'ups_tescom_b_output'  => 'ups_tescom_b.output',
        ];
    }

    public function up(): void
    {
        // ─── Records: add columns_config ──────────────────────
        Schema::table('tfp_aob_ground_records', function (Blueprint $table) {
            $table->jsonb('columns_config')->nullable()->after('location');
        });

        // ─── Items: add values + merge_map ────────────────────
        Schema::table('tfp_aob_ground_items', function (Blueprint $table) {
            $table->jsonb('values')->nullable()->after('unit');
            $table->jsonb('merge_map')->nullable()->after('is_disabled_map');
        });

        // ─── Backfill ─────────────────────────────────────────
        $defaultConfig = json_encode($this->defaultColumnsConfig());
        DB::table('tfp_aob_ground_records')
            ->whereNull('columns_config')
            ->update(['columns_config' => $defaultConfig]);

        $map = $this->legacyColumnMap();
        $items = DB::table('tfp_aob_ground_items')->get();

        foreach ($items as $item) {
            // Migrate values from fixed columns → JSON
            $values = [];
            foreach ($map as $legacy => $newKey) {
                $v = $item->{$legacy} ?? null;
                if ($v !== null && $v !== '') {
                    $values[$newKey] = (string) $v;
                }
            }

            // Re-key is_disabled_map using legacy → new key mapping.
            $disabled = $this->reKey($item->is_disabled_map, $map);

            DB::table('tfp_aob_ground_items')
                ->where('id', $item->id)
                ->update([
                    'values'          => empty($values) ? null : json_encode($values),
                    'is_disabled_map' => empty($disabled) ? null : json_encode($disabled),
                ]);
        }

        // ─── Drop fixed columns ───────────────────────────────
        Schema::table('tfp_aob_ground_items', function (Blueprint $table) {
            $table->dropColumn([
                'panel_cos_a03_input',
                'panel_cos_a03_output',
                'panel_ats_a12_input',
                'panel_ats_a12_output',
                'ups_tescom_a_input',
                'ups_tescom_a_output',
                'ups_tescom_b_input',
                'ups_tescom_b_output',
            ]);
        });
    }

    public function down(): void
    {
        // Re-add the fixed columns and best-effort backfill from values JSON
        // so that a down migration leaves the table queryable in legacy code paths.
        Schema::table('tfp_aob_ground_items', function (Blueprint $table) {
            $table->string('panel_cos_a03_input', 50)->nullable();
            $table->string('panel_cos_a03_output', 50)->nullable();
            $table->string('panel_ats_a12_input', 50)->nullable();
            $table->string('panel_ats_a12_output', 50)->nullable();
            $table->string('ups_tescom_a_input', 50)->nullable();
            $table->string('ups_tescom_a_output', 50)->nullable();
            $table->string('ups_tescom_b_input', 50)->nullable();
            $table->string('ups_tescom_b_output', 50)->nullable();
        });

        $reverse = array_flip($this->legacyColumnMap());
        $items = DB::table('tfp_aob_ground_items')->get();

        foreach ($items as $item) {
            $valuesRaw = $item->values;
            $values = is_string($valuesRaw) ? (json_decode($valuesRaw, true) ?: []) : (is_array($valuesRaw) ? $valuesRaw : []);

            $update = [];
            foreach ($values as $newKey => $val) {
                if (isset($reverse[$newKey])) {
                    $update[$reverse[$newKey]] = is_string($val) ? substr($val, 0, 50) : null;
                }
            }

            if (!empty($update)) {
                $disabled = $this->reKey($item->is_disabled_map, $reverse);
                $update['is_disabled_map'] = empty($disabled) ? null : json_encode($disabled);
                DB::table('tfp_aob_ground_items')->where('id', $item->id)->update($update);
            }
        }

        Schema::table('tfp_aob_ground_items', function (Blueprint $table) {
            $table->dropColumn(['values', 'merge_map']);
        });

        Schema::table('tfp_aob_ground_records', function (Blueprint $table) {
            $table->dropColumn('columns_config');
        });
    }

    /**
     * Re-key a JSON map using oldKey → newKey lookup. Unknown keys are dropped.
     */
    private function reKey(mixed $raw, array $map): array
    {
        if ($raw === null) return [];
        $arr = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);

        $out = [];
        foreach ($arr as $k => $v) {
            if (isset($map[$k])) {
                $out[$map[$k]] = $v;
            }
        }
        return $out;
    }
};
