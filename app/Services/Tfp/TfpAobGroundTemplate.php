<?php

namespace App\Services\Tfp;

/**
 * TfpAobGroundTemplate — canonical parameter and facility list for the
 * TFP Performance Check AOB Lantai Ground form.
 *
 * After the dynamic-columns migration, all cell positions are encoded via
 * `columns_config` (on the record) + composite "panel_id.sub_col_key" cell
 * keys (on the items). The default config matches the paper form: four panels
 * (COS A03, ATS A12, UPS TESCOM A, UPS TESCOM B), each with Input + Output.
 *
 * Disabled cell rules (is_disabled_map):
 *   - Rows 1-12  (voltage/current/frequency): all 8 columns enabled
 *   - Row 13     (Power Factor): UPS TESCOM A/B columns disabled
 *   - Rows 14-17 (Battery params): Panel COS A03 and Panel ATS A12 disabled
 *   - Rows 18-19 (Mode / Suplai Aktif): UPS TESCOM A/B disabled,
 *                                       Input + Output merged within each panel
 *   - Rows 20-21 (KWH Meter, Suhu Eq. Room): only panel_cos_a03.input active,
 *                                            the active cell spans all 8 cells
 */
class TfpAobGroundTemplate
{
    /**
     * Default columns_config used when seeding a new record.
     *
     * Shape:
     *   [
     *     {
     *       "id": "panel_cos_a03",
     *       "label": "Panel COS (A 03)",
     *       "sub_columns": [
     *         {"key": "input",  "label": "Input"},
     *         {"key": "output", "label": "Output"}
     *       ]
     *     },
     *     ...
     *   ]
     */
    public static function defaultColumnsConfig(): array
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
     * Flat list of every cell key produced by defaultColumnsConfig().
     * Used to derive "all-except-X" disable maps.
     */
    public static function defaultCellKeys(): array
    {
        $keys = [];
        foreach (self::defaultColumnsConfig() as $panel) {
            foreach ($panel['sub_columns'] as $sub) {
                $keys[] = $panel['id'] . '.' . $sub['key'];
            }
        }
        return $keys;
    }

    /**
     * Returns the canonical list of measurement parameters.
     */
    public static function parameters(): array
    {
        $allKeys = self::defaultCellKeys();

        // UPS TESCOM A/B disabled
        $disabledUps = [
            'ups_tescom_a.input'  => true,
            'ups_tescom_a.output' => true,
            'ups_tescom_b.input'  => true,
            'ups_tescom_b.output' => true,
        ];

        // Panel COS A03 and Panel ATS A12 disabled (Battery rows)
        $disabledPanels = [
            'panel_cos_a03.input'  => true,
            'panel_cos_a03.output' => true,
            'panel_ats_a12.input'  => true,
            'panel_ats_a12.output' => true,
        ];

        // Single-value rows: only panel_cos_a03.input is active,
        // the rest are disabled. The active cell spans all 8 columns.
        $disabledAllExceptFirst = array_fill_keys(
            array_values(array_filter($allKeys, fn ($k) => $k !== 'panel_cos_a03.input')),
            true,
        );
        $singleValueMerge = ['panel_cos_a03.input' => 8];

        // Rows 18-19: merge Input + Output within each enabled panel
        $mergeIO = [
            'panel_cos_a03.input' => 2,
            'panel_ats_a12.input' => 2,
        ];

        return [
            // ── Voltage (L-N) ──────────────────────────────────
            ['parameter_number' => '1',  'parameter_name' => 'L1 - N',              'unit' => 'Volt',    'is_disabled_map' => [],            'merge_map' => []],
            ['parameter_number' => '2',  'parameter_name' => 'L2 - N',              'unit' => 'Volt',    'is_disabled_map' => [],            'merge_map' => []],
            ['parameter_number' => '3',  'parameter_name' => 'L3 - N',              'unit' => 'Volt',    'is_disabled_map' => [],            'merge_map' => []],
            ['parameter_number' => '4',  'parameter_name' => 'N - G',               'unit' => 'Volt',    'is_disabled_map' => [],            'merge_map' => []],
            // ── Voltage (L-L) ──────────────────────────────────
            ['parameter_number' => '5',  'parameter_name' => 'L1 - L2',             'unit' => 'Volt',    'is_disabled_map' => [],            'merge_map' => []],
            ['parameter_number' => '6',  'parameter_name' => 'L1 - L3',             'unit' => 'Volt',    'is_disabled_map' => [],            'merge_map' => []],
            ['parameter_number' => '7',  'parameter_name' => 'L2 - L3',             'unit' => 'Volt',    'is_disabled_map' => [],            'merge_map' => []],
            // ── Current ────────────────────────────────────────
            ['parameter_number' => '8',  'parameter_name' => 'L1',                  'unit' => 'Ampere',  'is_disabled_map' => [],            'merge_map' => []],
            ['parameter_number' => '9',  'parameter_name' => 'L2',                  'unit' => 'Ampere',  'is_disabled_map' => [],            'merge_map' => []],
            ['parameter_number' => '10', 'parameter_name' => 'L3',                  'unit' => 'Ampere',  'is_disabled_map' => [],            'merge_map' => []],
            ['parameter_number' => '11', 'parameter_name' => 'N',                   'unit' => 'Ampere',  'is_disabled_map' => [],            'merge_map' => []],
            // ── Frequency ──────────────────────────────────────
            ['parameter_number' => '12', 'parameter_name' => 'Frekuensi',           'unit' => 'Hz',      'is_disabled_map' => [],            'merge_map' => []],
            // ── Power Factor ───────────────────────────────────
            ['parameter_number' => '13', 'parameter_name' => 'Power Factor (Cos Θ)', 'unit' => null,     'is_disabled_map' => $disabledUps,  'merge_map' => []],
            // ── Battery ────────────────────────────────────────
            ['parameter_number' => '14', 'parameter_name' => 'Tegangan Battery',    'unit' => 'Volt',    'is_disabled_map' => $disabledPanels, 'merge_map' => []],
            ['parameter_number' => '15', 'parameter_name' => 'Arus Battery',        'unit' => 'Ampere',  'is_disabled_map' => $disabledPanels, 'merge_map' => []],
            ['parameter_number' => '16', 'parameter_name' => 'Kapasitas Battery',   'unit' => 'Ah',      'is_disabled_map' => $disabledPanels, 'merge_map' => []],
            ['parameter_number' => '17', 'parameter_name' => 'Suhu Battery',        'unit' => '°C',      'is_disabled_map' => $disabledPanels, 'merge_map' => []],
            // ── Mode / Suplai (merged Input+Output per panel) ──
            ['parameter_number' => '18', 'parameter_name' => 'Mode *',              'unit' => null,      'is_disabled_map' => $disabledUps,  'merge_map' => $mergeIO],
            ['parameter_number' => '19', 'parameter_name' => 'Suplai Aktif *',      'unit' => null,      'is_disabled_map' => $disabledUps,  'merge_map' => $mergeIO],
            // ── Single-value rows (one merged cell across all panels) ──
            ['parameter_number' => '20', 'parameter_name' => 'KWH Meter',           'unit' => null,      'is_disabled_map' => $disabledAllExceptFirst, 'merge_map' => $singleValueMerge],
            ['parameter_number' => '21', 'parameter_name' => 'Suhu Eq. Room',       'unit' => '°C',      'is_disabled_map' => $disabledAllExceptFirst, 'merge_map' => $singleValueMerge],
        ];
    }

    /**
     * Returns the canonical list of facility items.
     */
    public static function facilities(): array
    {
        return [
            ['facility_name' => 'Catu Daya Listrik'],
            ['facility_name' => 'Penerangan'],
            ['facility_name' => 'UPS Tescom A'],
            ['facility_name' => 'UPS Tescom B'],
            ['facility_name' => 'AC 01 (Split Wall) Eq'],
            ['facility_name' => 'AC 02 (Split Wall) Eq'],
            ['facility_name' => 'AC 03 (Split Wall) Eq'],
            ['facility_name' => 'AC 04 (Split Wall) Eq'],
            ['facility_name' => 'AC 05 (Split Wall) Gd 21'],
            ['facility_name' => 'AC 06 (Split Wall) Ex MCC'],
            ['facility_name' => 'AC 08 (Split Wall) ARO'],
            ['facility_name' => 'Papan Nama AirNav'],
            ['facility_name' => 'Atap'],
            ['facility_name' => 'Plafond'],
            ['facility_name' => 'Dinding'],
            ['facility_name' => 'Pintu'],
            ['facility_name' => 'Door Lock'],
        ];
    }

    /**
     * Flatten parameters into DB row inserts for tfp_aob_ground_items.
     */
    public static function buildItemRows(int $recordId): array
    {
        $rows = [];
        $sortOrder = 0;
        $now = now();

        foreach (self::parameters() as $param) {
            $rows[] = [
                'aob_ground_record_id' => $recordId,
                'parameter_number'     => $param['parameter_number'],
                'parameter_name'       => $param['parameter_name'],
                'unit'                 => $param['unit'],
                'values'               => null,
                'is_disabled_map'      => empty($param['is_disabled_map']) ? null : json_encode($param['is_disabled_map']),
                'merge_map'            => empty($param['merge_map']) ? null : json_encode($param['merge_map']),
                'sort_order'           => $sortOrder++,
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }

        return $rows;
    }

    /**
     * Flatten facilities into DB row inserts for tfp_aob_ground_facilities.
     */
    public static function buildFacilityRows(int $recordId): array
    {
        $rows = [];
        $sortOrder = 0;
        $now = now();

        foreach (self::facilities() as $facility) {
            $rows[] = [
                'aob_ground_record_id' => $recordId,
                'facility_name'        => $facility['facility_name'],
                'kondisi'              => null,
                'keterangan'           => null,
                'sort_order'           => $sortOrder++,
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }

        return $rows;
    }
}
