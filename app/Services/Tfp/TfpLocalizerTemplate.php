<?php

namespace App\Services\Tfp;

/**
 * TfpLocalizerTemplate — Performance Check Gedung Localizer form.
 *
 * 21 parameters × 5 panels (7 cells total — COS LZ02 & COS LZ04 have Input/Output split):
 *   panel_lz01 (1)        panel_cos_lz02 (Input/Output)   panel_lz03 (1)
 *   panel_cos_lz04 (Input/Output)   panel_mlat_ru04 (1)
 *
 * Disabled cell rules:
 *   Rows 1-12  : all 7 cells enabled
 *   Row 13     : Power Factor — only COS LZ02 + COS LZ04 active
 *   Rows 14-17 : Battery — only COS LZ02 + COS LZ04 active
 *   Rows 18-19 : Mode/Suplai — only COS LZ02 + COS LZ04 active
 *   Rows 20-21 : Suhu Ruangan / KWH Meter — single value at panel_lz01.value,
 *                merge_map=7 spans the whole row
 */
class TfpLocalizerTemplate
{
    public static function defaultColumnsConfig(): array
    {
        $single = [['key' => 'value',  'label' => 'Nilai']];
        $io     = [['key' => 'input',  'label' => 'Input'], ['key' => 'output', 'label' => 'Output']];

        return [
            ['id' => 'panel_lz01',      'label' => 'Panel LZ 01',        'sub_columns' => $single],
            ['id' => 'panel_cos_lz02',  'label' => 'Panel COS (LZ 02)',  'sub_columns' => $io],
            ['id' => 'panel_lz03',      'label' => 'Panel LZ 03',        'sub_columns' => $single],
            ['id' => 'panel_cos_lz04',  'label' => 'Panel COS (LZ 04)',  'sub_columns' => $io],
            ['id' => 'panel_mlat_ru04', 'label' => 'Panel MLAT RU 04',   'sub_columns' => $single],
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
        $allKeys = self::defaultCellKeys();

        // Rows 13-17 (Power Factor + Battery): only COS LZ02 + COS LZ04 active
        $disabledPowerBattery = [
            'panel_lz01.value'      => true,
            'panel_lz03.value'      => true,
            'panel_mlat_ru04.value' => true,
        ];

        // Rows 18-19 (Mode/Suplai): same as Power Factor (only COS panels active)
        $disabledModeSupplai = $disabledPowerBattery;

        // Rows 20-21 (Suhu Ruangan / KWH Meter): single value in panel_lz01.value,
        // merge_map spans all 7 cells
        $disabledSuhu = array_fill_keys(
            array_values(array_filter($allKeys, fn ($k) => $k !== 'panel_lz01.value')),
            true,
        );
        $mergeSuhu = ['panel_lz01.value' => count($allKeys)];

        return [
            ['parameter_number' => '1',  'parameter_name' => 'L1 - N',                'unit' => 'Volt',   'is_disabled_map' => [],                    'merge_map' => []],
            ['parameter_number' => '2',  'parameter_name' => 'L2 - N',                'unit' => 'Volt',   'is_disabled_map' => [],                    'merge_map' => []],
            ['parameter_number' => '3',  'parameter_name' => 'L3 - N',                'unit' => 'Volt',   'is_disabled_map' => [],                    'merge_map' => []],
            ['parameter_number' => '4',  'parameter_name' => 'N - G',                 'unit' => 'Volt',   'is_disabled_map' => [],                    'merge_map' => []],
            ['parameter_number' => '5',  'parameter_name' => 'L1 - L2',               'unit' => 'Volt',   'is_disabled_map' => [],                    'merge_map' => []],
            ['parameter_number' => '6',  'parameter_name' => 'L1 - L3',               'unit' => 'Volt',   'is_disabled_map' => [],                    'merge_map' => []],
            ['parameter_number' => '7',  'parameter_name' => 'L2 - L3',               'unit' => 'Volt',   'is_disabled_map' => [],                    'merge_map' => []],
            ['parameter_number' => '8',  'parameter_name' => 'L1',                    'unit' => 'Ampere', 'is_disabled_map' => [],                    'merge_map' => []],
            ['parameter_number' => '9',  'parameter_name' => 'L2',                    'unit' => 'Ampere', 'is_disabled_map' => [],                    'merge_map' => []],
            ['parameter_number' => '10', 'parameter_name' => 'L3',                    'unit' => 'Ampere', 'is_disabled_map' => [],                    'merge_map' => []],
            ['parameter_number' => '11', 'parameter_name' => 'N',                     'unit' => 'Ampere', 'is_disabled_map' => [],                    'merge_map' => []],
            ['parameter_number' => '12', 'parameter_name' => 'Frekuensi',             'unit' => 'Hz',     'is_disabled_map' => [],                    'merge_map' => []],
            ['parameter_number' => '13', 'parameter_name' => 'Power Factor (Cos θ)',  'unit' => null,     'is_disabled_map' => $disabledPowerBattery, 'merge_map' => []],
            ['parameter_number' => '14', 'parameter_name' => 'Tegangan Battery',      'unit' => 'Volt',   'is_disabled_map' => $disabledPowerBattery, 'merge_map' => []],
            ['parameter_number' => '15', 'parameter_name' => 'Arus Battery',          'unit' => 'Ampere', 'is_disabled_map' => $disabledPowerBattery, 'merge_map' => []],
            ['parameter_number' => '16', 'parameter_name' => 'Kapasitas Battery',     'unit' => 'Ah',     'is_disabled_map' => $disabledPowerBattery, 'merge_map' => []],
            ['parameter_number' => '17', 'parameter_name' => 'Suhu Battery',          'unit' => '°C',     'is_disabled_map' => $disabledPowerBattery, 'merge_map' => []],
            ['parameter_number' => '18', 'parameter_name' => 'Mode *',                'unit' => null,     'is_disabled_map' => $disabledModeSupplai,  'merge_map' => []],
            ['parameter_number' => '19', 'parameter_name' => 'Suplai Aktif *',        'unit' => null,     'is_disabled_map' => $disabledModeSupplai,  'merge_map' => []],
            ['parameter_number' => '20', 'parameter_name' => 'Suhu Ruangan',          'unit' => '°C',     'is_disabled_map' => $disabledSuhu,         'merge_map' => $mergeSuhu],
            ['parameter_number' => '21', 'parameter_name' => 'KWH Meter',             'unit' => null,     'is_disabled_map' => $disabledSuhu,         'merge_map' => $mergeSuhu],
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
            ['facility_name' => 'Exhaust Fan',             'keterangan' => 'A'],
            ['facility_name' => 'Papan Nama AirNav',       'keterangan' => null],
            ['facility_name' => 'Rumput',                  'keterangan' => null],
            ['facility_name' => 'APAR/Fire Extinguisher',  'keterangan' => null],
            ['facility_name' => 'Atap',                    'keterangan' => null],
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
                'localizer_record_id' => $recordId,
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
                'localizer_record_id' => $recordId,
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
