<?php

namespace App\Services\Tfp;

/**
 * TfpAobLt12Template — canonical parameter and facility list for the
 * TFP Performance Check AOB Lantai 1 & 2 form.
 *
 * After the dynamic-columns migration, all cell positions are encoded via
 * `columns_config` (on the record) + composite "panel_id.sub_col_key" cell
 * keys (on the items). The default config matches the paper form: 6 panels,
 * each with a single "Nilai" sub-column.
 *
 * Disabled cell rules (is_disabled_map):
 *   - Rows 1-12  (voltage/current/frequency): all 6 cells enabled
 *   - Row 13     (Power Factor): panel_a09_amsc_room disabled
 *   - Rows 14-17 (Battery params): panel_a05/a06/a07 disabled
 *   - Rows 18-19 (Mode / Suplai Aktif): panel_a08/a22/a09 disabled
 *   - Row 20     (Suhu APP Room): only panel_a05_app_room active
 *   - Row 21     (Suhu AMSC Room): only panel_a09_amsc_room active
 */
class TfpAobLt12Template
{
    /**
     * Default columns_config used when seeding a new record.
     * 6 panels × 1 sub-column ("value") = 6 cells per row.
     */
    public static function defaultColumnsConfig(): array
    {
        $single = [['key' => 'value', 'label' => 'Nilai']];

        return [
            ['id' => 'panel_a05_app_room',   'label' => 'Panel A 05 APP Room',    'sub_columns' => $single],
            ['id' => 'panel_a06_app_room',   'label' => 'Panel A 06 APP Room',    'sub_columns' => $single],
            ['id' => 'panel_a07_app_room',   'label' => 'Panel A 07 APP Room',    'sub_columns' => $single],
            ['id' => 'panel_a08_gudang_lt1', 'label' => 'Panel A 08 Gudang Lt 1', 'sub_columns' => $single],
            ['id' => 'panel_a22_gudang_lt1', 'label' => 'Panel A 22 Gudang Lt 1', 'sub_columns' => $single],
            ['id' => 'panel_a09_amsc_room',  'label' => 'Panel A 09 AMSC Room',   'sub_columns' => $single],
        ];
    }

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

    public static function parameters(): array
    {
        $disabledAmsc = ['panel_a09_amsc_room.value' => true];

        $disabledAppRooms = [
            'panel_a05_app_room.value' => true,
            'panel_a06_app_room.value' => true,
            'panel_a07_app_room.value' => true,
        ];

        $disabledGudangAmsc = [
            'panel_a08_gudang_lt1.value' => true,
            'panel_a22_gudang_lt1.value' => true,
            'panel_a09_amsc_room.value'  => true,
        ];

        $disabledAllExceptA05 = [
            'panel_a06_app_room.value'   => true,
            'panel_a07_app_room.value'   => true,
            'panel_a08_gudang_lt1.value' => true,
            'panel_a22_gudang_lt1.value' => true,
            'panel_a09_amsc_room.value'  => true,
        ];

        $disabledAllExceptA09 = [
            'panel_a05_app_room.value'   => true,
            'panel_a06_app_room.value'   => true,
            'panel_a07_app_room.value'   => true,
            'panel_a08_gudang_lt1.value' => true,
            'panel_a22_gudang_lt1.value' => true,
        ];

        // Rows 20-21 visually span all 6 cells via merge_map (starts at the active panel)
        $mergeSuhuApp  = ['panel_a05_app_room.value' => 6];
        $mergeSuhuAmsc = []; // a09 is the last cell — can't merge right, leave as single cell

        return [
            ['parameter_number' => '1',  'parameter_name' => 'L1 - N',               'unit' => 'Volt',   'is_disabled_map' => [],                'merge_map' => []],
            ['parameter_number' => '2',  'parameter_name' => 'L2 - N',               'unit' => 'Volt',   'is_disabled_map' => [],                'merge_map' => []],
            ['parameter_number' => '3',  'parameter_name' => 'L3 - N',               'unit' => 'Volt',   'is_disabled_map' => [],                'merge_map' => []],
            ['parameter_number' => '4',  'parameter_name' => 'N - G',                'unit' => 'Volt',   'is_disabled_map' => [],                'merge_map' => []],
            ['parameter_number' => '5',  'parameter_name' => 'L1 - L2',              'unit' => 'Volt',   'is_disabled_map' => [],                'merge_map' => []],
            ['parameter_number' => '6',  'parameter_name' => 'L1 - L3',              'unit' => 'Volt',   'is_disabled_map' => [],                'merge_map' => []],
            ['parameter_number' => '7',  'parameter_name' => 'L2 - L3',              'unit' => 'Volt',   'is_disabled_map' => [],                'merge_map' => []],
            ['parameter_number' => '8',  'parameter_name' => 'L1',                   'unit' => 'Ampere', 'is_disabled_map' => [],                'merge_map' => []],
            ['parameter_number' => '9',  'parameter_name' => 'L2',                   'unit' => 'Ampere', 'is_disabled_map' => [],                'merge_map' => []],
            ['parameter_number' => '10', 'parameter_name' => 'L3',                   'unit' => 'Ampere', 'is_disabled_map' => [],                'merge_map' => []],
            ['parameter_number' => '11', 'parameter_name' => 'N',                    'unit' => 'Ampere', 'is_disabled_map' => [],                'merge_map' => []],
            ['parameter_number' => '12', 'parameter_name' => 'Frekuensi',            'unit' => 'Hz',     'is_disabled_map' => [],                'merge_map' => []],
            ['parameter_number' => '13', 'parameter_name' => 'Power Factor (Cos Θ)', 'unit' => null,     'is_disabled_map' => $disabledAmsc,     'merge_map' => []],
            ['parameter_number' => '14', 'parameter_name' => 'Tegangan Battery',     'unit' => 'Volt',   'is_disabled_map' => $disabledAppRooms, 'merge_map' => []],
            ['parameter_number' => '15', 'parameter_name' => 'Arus Battery',         'unit' => 'Ampere', 'is_disabled_map' => $disabledAppRooms, 'merge_map' => []],
            ['parameter_number' => '16', 'parameter_name' => 'Kapasitas Battery',    'unit' => 'Ah',     'is_disabled_map' => $disabledAppRooms, 'merge_map' => []],
            ['parameter_number' => '17', 'parameter_name' => 'Suhu Battery',         'unit' => '°C',     'is_disabled_map' => $disabledAppRooms, 'merge_map' => []],
            ['parameter_number' => '18', 'parameter_name' => 'Mode *',               'unit' => null,     'is_disabled_map' => $disabledGudangAmsc, 'merge_map' => []],
            ['parameter_number' => '19', 'parameter_name' => 'Suplai Aktif *',       'unit' => null,     'is_disabled_map' => $disabledGudangAmsc, 'merge_map' => []],
            ['parameter_number' => '20', 'parameter_name' => 'Suhu APP Room',        'unit' => '°C',     'is_disabled_map' => $disabledAllExceptA05, 'merge_map' => $mergeSuhuApp],
            ['parameter_number' => '21', 'parameter_name' => 'Suhu AMSC Room',       'unit' => '°C',     'is_disabled_map' => $disabledAllExceptA09, 'merge_map' => $mergeSuhuAmsc],
        ];
    }

    public static function facilities(): array
    {
        return [
            ['facility_name' => 'Catu Daya Listrik'],
            ['facility_name' => 'Penerangan'],
            ['facility_name' => 'AC 09 (Split Wall) GM'],
            ['facility_name' => 'AC 10 (Split Wall) Sek.GM'],
            ['facility_name' => 'AC 11 (Split Wall) ATS'],
            ['facility_name' => 'AC 12 (Split Wall) Staf FAM'],
            ['facility_name' => 'AC 13 (Split Wall) FAM'],
            ['facility_name' => 'AC 14 (Split Wall) Rest APP'],
            ['facility_name' => 'AC 15 (Split Wall) Kmr L'],
            ['facility_name' => 'AC 16 (Split Wall) Kmr P'],
            ['facility_name' => 'AC 17 (Split Wall) Sms'],
            ['facility_name' => 'AC 18 (Standing Floor) APP'],
            ['facility_name' => 'AC 19 (Standing Floor) APP'],
            ['facility_name' => 'AC 20 (Split Wall) AMSC'],
            ['facility_name' => 'AC 21 (Split Wall) ATIS'],
            ['facility_name' => 'Exhaust Fan Gudang Lt 1'],
            ['facility_name' => 'Exhaust Fan Toilet L/P'],
            ['facility_name' => 'Exhaust Fan Pantry'],
            ['facility_name' => 'APAR / Fire Extinguisher'],
            ['facility_name' => 'Atap'],
            ['facility_name' => 'Plafond'],
            ['facility_name' => 'Dinding'],
            ['facility_name' => 'Pintu'],
            ['facility_name' => 'Door Lock'],
        ];
    }

    public static function buildItemRows(int $recordId): array
    {
        $rows = [];
        $sortOrder = 0;
        $now = now();

        foreach (self::parameters() as $param) {
            $rows[] = [
                'aob_lt12_record_id' => $recordId,
                'parameter_number'   => $param['parameter_number'],
                'parameter_name'     => $param['parameter_name'],
                'unit'               => $param['unit'],
                'values'             => null,
                'is_disabled_map'    => empty($param['is_disabled_map']) ? null : json_encode($param['is_disabled_map']),
                'merge_map'          => empty($param['merge_map']) ? null : json_encode($param['merge_map']),
                'sort_order'         => $sortOrder++,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        return $rows;
    }

    public static function buildFacilityRows(int $recordId): array
    {
        $rows = [];
        $sortOrder = 0;
        $now = now();

        foreach (self::facilities() as $facility) {
            $rows[] = [
                'aob_lt12_record_id' => $recordId,
                'facility_name'      => $facility['facility_name'],
                'kondisi'            => null,
                'keterangan'         => null,
                'sort_order'         => $sortOrder++,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        return $rows;
    }
}
