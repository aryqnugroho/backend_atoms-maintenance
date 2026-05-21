<?php

namespace App\Services\Tfp;

/**
 * TfpGlidepathTemplate — Performance Check Gedung Glide Path form.
 *
 * 21 parameters × 1 panel (1 cell total):
 *   panel_gp01 (Nilai)
 *
 * Disabled cell rules:
 *   Rows 1-12  : panel_gp01 enabled
 *   Rows 13-19 : panel_gp01 disabled (Power Factor, Battery, Mode, Suplai Aktif)
 *   Rows 20-21 : panel_gp01 enabled (Suhu Ruangan, KWH Meter)
 */
class TfpGlidepathTemplate
{
    public static function defaultColumnsConfig(): array
    {
        $single = [['key' => 'value', 'label' => 'Nilai']];

        return [
            ['id' => 'panel_gp01', 'label' => 'Panel GP 01', 'sub_columns' => $single],
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
        // Rows 13-19 (Power Factor + Battery + Mode/Suplai): panel_gp01 disabled
        $disabled = ['panel_gp01.value' => true];

        return [
            ['parameter_number' => '1',  'parameter_name' => 'L1 - N',                'unit' => 'Volt',   'is_disabled_map' => [],        'merge_map' => []],
            ['parameter_number' => '2',  'parameter_name' => 'L2 - N',                'unit' => 'Volt',   'is_disabled_map' => [],        'merge_map' => []],
            ['parameter_number' => '3',  'parameter_name' => 'L3 - N',                'unit' => 'Volt',   'is_disabled_map' => [],        'merge_map' => []],
            ['parameter_number' => '4',  'parameter_name' => 'N - G',                 'unit' => 'Volt',   'is_disabled_map' => [],        'merge_map' => []],
            ['parameter_number' => '5',  'parameter_name' => 'L1 - L2',               'unit' => 'Volt',   'is_disabled_map' => [],        'merge_map' => []],
            ['parameter_number' => '6',  'parameter_name' => 'L1 - L3',               'unit' => 'Volt',   'is_disabled_map' => [],        'merge_map' => []],
            ['parameter_number' => '7',  'parameter_name' => 'L2 - L3',               'unit' => 'Volt',   'is_disabled_map' => [],        'merge_map' => []],
            ['parameter_number' => '8',  'parameter_name' => 'L1',                    'unit' => 'Ampere', 'is_disabled_map' => [],        'merge_map' => []],
            ['parameter_number' => '9',  'parameter_name' => 'L2',                    'unit' => 'Ampere', 'is_disabled_map' => [],        'merge_map' => []],
            ['parameter_number' => '10', 'parameter_name' => 'L3',                    'unit' => 'Ampere', 'is_disabled_map' => [],        'merge_map' => []],
            ['parameter_number' => '11', 'parameter_name' => 'N',                     'unit' => 'Ampere', 'is_disabled_map' => [],        'merge_map' => []],
            ['parameter_number' => '12', 'parameter_name' => 'Frekuensi',             'unit' => 'Hz',     'is_disabled_map' => [],        'merge_map' => []],
            ['parameter_number' => '13', 'parameter_name' => 'Power Factor (Cos θ)',  'unit' => null,     'is_disabled_map' => $disabled, 'merge_map' => []],
            ['parameter_number' => '14', 'parameter_name' => 'Tegangan Battery',      'unit' => 'Volt',   'is_disabled_map' => $disabled, 'merge_map' => []],
            ['parameter_number' => '15', 'parameter_name' => 'Arus Battery',          'unit' => 'Ampere', 'is_disabled_map' => $disabled, 'merge_map' => []],
            ['parameter_number' => '16', 'parameter_name' => 'Kapasitas Battery',     'unit' => 'Ah',     'is_disabled_map' => $disabled, 'merge_map' => []],
            ['parameter_number' => '17', 'parameter_name' => 'Suhu Battery',          'unit' => '°C',     'is_disabled_map' => $disabled, 'merge_map' => []],
            ['parameter_number' => '18', 'parameter_name' => 'Mode *',                'unit' => null,     'is_disabled_map' => $disabled, 'merge_map' => []],
            ['parameter_number' => '19', 'parameter_name' => 'Suplai Aktif *',        'unit' => null,     'is_disabled_map' => $disabled, 'merge_map' => []],
            ['parameter_number' => '20', 'parameter_name' => 'Suhu Ruangan',          'unit' => '°C',     'is_disabled_map' => [],        'merge_map' => []],
            ['parameter_number' => '21', 'parameter_name' => 'KWH Meter',             'unit' => null,     'is_disabled_map' => [],        'merge_map' => []],
        ];
    }

    public static function facilities(): array
    {
        return [
            ['facility_name' => 'Catu Daya Listrik',       'keterangan' => null],
            ['facility_name' => 'Penerangan',              'keterangan' => null],
            ['facility_name' => 'Obstacle Light',          'keterangan' => null],
            ['facility_name' => 'AC 01 (Split Wall)',      'keterangan' => 'A'],
            ['facility_name' => 'AC 02 (Split Wall)',      'keterangan' => 'A'],
            ['facility_name' => 'Exhaust Fan',             'keterangan' => null],
            ['facility_name' => 'Rumput',                  'keterangan' => null],
            ['facility_name' => 'APAR/Fire Extinguisher',  'keterangan' => null],
            ['facility_name' => 'Atap',                    'keterangan' => null],
            ['facility_name' => 'Plafond',                 'keterangan' => null],
            ['facility_name' => 'Dinding',                 'keterangan' => null],
            ['facility_name' => 'Pintu',                   'keterangan' => null],
        ];
    }

    public static function buildItemRows(int $recordId): array
    {
        $rows = [];
        $sortOrder = 0;
        $now = now();
        foreach (self::parameters() as $param) {
            $rows[] = [
                'glidepath_record_id' => $recordId,
                'parameter_number'    => $param['parameter_number'],
                'parameter_name'      => $param['parameter_name'],
                'unit'                => $param['unit'],
                'values'              => null,
                'is_disabled_map'     => empty($param['is_disabled_map']) ? null : json_encode($param['is_disabled_map']),
                'merge_map'           => empty($param['merge_map']) ? null : json_encode($param['merge_map']),
                'sort_order'          => $sortOrder++,
                'created_at'          => $now,
                'updated_at'          => $now,
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
                'glidepath_record_id' => $recordId,
                'facility_name'       => $facility['facility_name'],
                'kondisi'             => null,
                'keterangan'          => $facility['keterangan'],
                'sort_order'          => $sortOrder++,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }
        return $rows;
    }
}
