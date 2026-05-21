<?php

namespace App\Services\Tfp;

/**
 * TfpRadarTemplate — Performance Check Gedung Radar form.
 *
 * 24 parameters × 9 panels (11 cells total — some with Input/Output split):
 *   panel_rd01 (1)         panel_rd02 (1)          panel_cos_rd03 (Input/Output)
 *   ups_topaz (Input/Output)
 *   panel_rd04 (1)         panel_rd05 (1)          panel_rd06 (1)
 *   panel_rd07 (1)         panel_rd08 (1)
 *
 * Disabled cell rules (per row):
 *   - Rows 1-12  : all 11 cells enabled
 *   - Row 13     : Power Factor — only panel_cos_rd03 + ups_topaz active
 *   - Rows 14-17 : Battery — only ups_topaz + panel_rd07 active
 *   - Rows 18-19 : Mode/Suplai — only panel_cos_rd03 + ups_topaz active
 *   - Rows 20-24 : Single value rows (KWH / Suhu Ruang variants / Meter Air)
 *                  in panel_rd01.value, others disabled, merge_map=11 to span
 */
class TfpRadarTemplate
{
    public static function defaultColumnsConfig(): array
    {
        $single = [['key' => 'value',  'label' => 'Nilai']];
        $io     = [['key' => 'input',  'label' => 'Input'], ['key' => 'output', 'label' => 'Output']];

        return [
            ['id' => 'panel_rd01',     'label' => 'Panel RD 01',       'sub_columns' => $single],
            ['id' => 'panel_rd02',     'label' => 'Panel RD 02',       'sub_columns' => $single],
            ['id' => 'panel_cos_rd03', 'label' => 'Panel COS (RD 03)', 'sub_columns' => $io],
            ['id' => 'ups_topaz',      'label' => 'UPS TOPAZ',         'sub_columns' => $io],
            ['id' => 'panel_rd04',     'label' => 'Panel RD 04',       'sub_columns' => $single],
            ['id' => 'panel_rd05',     'label' => 'Panel RD 05',       'sub_columns' => $single],
            ['id' => 'panel_rd06',     'label' => 'Panel RD 06',       'sub_columns' => $single],
            ['id' => 'panel_rd07',     'label' => 'Panel RD 07',       'sub_columns' => $single],
            ['id' => 'panel_rd08',     'label' => 'Panel RD 08',       'sub_columns' => $single],
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

        // Row 13 (Power Factor): only panel_cos_rd03 + ups_topaz active
        $disabledPowerFactor = [
            'panel_rd01.value' => true,
            'panel_rd02.value' => true,
            'panel_rd04.value' => true,
            'panel_rd05.value' => true,
            'panel_rd06.value' => true,
            'panel_rd07.value' => true,
            'panel_rd08.value' => true,
        ];

        // Rows 14-17 (Battery): only ups_topaz + panel_rd07 active
        $disabledBattery = [
            'panel_rd01.value'        => true,
            'panel_rd02.value'        => true,
            'panel_cos_rd03.input'    => true,
            'panel_cos_rd03.output'   => true,
            'panel_rd04.value'        => true,
            'panel_rd05.value'        => true,
            'panel_rd06.value'        => true,
            'panel_rd08.value'        => true,
        ];

        // Rows 18-19 (Mode/Suplai): same as Power Factor
        $disabledModeSupplai = $disabledPowerFactor;

        // Rows 20-24 (single value rows): only panel_rd01.value active.
        // merge_map=count(allKeys) so the active cell spans all 11 cells.
        $disabledSingleValue = array_fill_keys(
            array_values(array_filter($allKeys, fn ($k) => $k !== 'panel_rd01.value')),
            true,
        );
        $singleValueMerge = ['panel_rd01.value' => count($allKeys)];

        return [
            ['parameter_number' => '1',  'parameter_name' => 'L1 - N',                'unit' => 'Volt',   'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '2',  'parameter_name' => 'L2 - N',                'unit' => 'Volt',   'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '3',  'parameter_name' => 'L3 - N',                'unit' => 'Volt',   'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '4',  'parameter_name' => 'N - G',                 'unit' => 'Volt',   'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '5',  'parameter_name' => 'L1 - L2',               'unit' => 'Volt',   'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '6',  'parameter_name' => 'L1 - L3',               'unit' => 'Volt',   'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '7',  'parameter_name' => 'L2 - L3',               'unit' => 'Volt',   'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '8',  'parameter_name' => 'L1',                    'unit' => 'Ampere', 'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '9',  'parameter_name' => 'L2',                    'unit' => 'Ampere', 'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '10', 'parameter_name' => 'L3',                    'unit' => 'Ampere', 'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '11', 'parameter_name' => 'N',                     'unit' => 'Ampere', 'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '12', 'parameter_name' => 'Frekuensi',             'unit' => 'Hz',     'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '13', 'parameter_name' => 'Power Factor (Cos θ)',  'unit' => null,     'is_disabled_map' => $disabledPowerFactor,     'merge_map' => []],
            ['parameter_number' => '14', 'parameter_name' => 'Tegangan Battery',      'unit' => 'Volt',   'is_disabled_map' => $disabledBattery,         'merge_map' => []],
            ['parameter_number' => '15', 'parameter_name' => 'Arus Battery',          'unit' => 'Ampere', 'is_disabled_map' => $disabledBattery,         'merge_map' => []],
            ['parameter_number' => '16', 'parameter_name' => 'Kapasitas Battery',     'unit' => 'Ah',     'is_disabled_map' => $disabledBattery,         'merge_map' => []],
            ['parameter_number' => '17', 'parameter_name' => 'Suhu Battery',          'unit' => '°C',     'is_disabled_map' => $disabledBattery,         'merge_map' => []],
            ['parameter_number' => '18', 'parameter_name' => 'Mode *',                'unit' => null,     'is_disabled_map' => $disabledModeSupplai,     'merge_map' => []],
            ['parameter_number' => '19', 'parameter_name' => 'Suplai Aktif *',        'unit' => null,     'is_disabled_map' => $disabledModeSupplai,     'merge_map' => []],
            ['parameter_number' => '20', 'parameter_name' => 'kWh meter',             'unit' => null,     'is_disabled_map' => $disabledSingleValue,     'merge_map' => $singleValueMerge],
            ['parameter_number' => '21', 'parameter_name' => 'Suhu Ruang UPS',        'unit' => '°C',     'is_disabled_map' => $disabledSingleValue,     'merge_map' => $singleValueMerge],
            ['parameter_number' => '22', 'parameter_name' => 'Suhu Ruang ADSB',       'unit' => '°C',     'is_disabled_map' => $disabledSingleValue,     'merge_map' => $singleValueMerge],
            ['parameter_number' => '23', 'parameter_name' => 'Suhu Ruang Radar Head', 'unit' => '°C',     'is_disabled_map' => $disabledSingleValue,     'merge_map' => $singleValueMerge],
            ['parameter_number' => '24', 'parameter_name' => 'Meter Air',             'unit' => null,     'is_disabled_map' => $disabledSingleValue,     'merge_map' => $singleValueMerge],
        ];
    }

    public static function facilities(): array
    {
        return [
            ['facility_name' => 'Catu Daya Listrik',       'keterangan' => null],
            ['facility_name' => 'Penerangan Gedung',       'keterangan' => null],
            ['facility_name' => 'Penerangan Jalan (PJU)',  'keterangan' => null],
            ['facility_name' => 'Obstacle Light',          'keterangan' => null],
            ['facility_name' => 'Genset 150 KVA',          'keterangan' => null],
            ['facility_name' => 'UPS 150 KVA',             'keterangan' => null],
            ['facility_name' => 'AC 01 (SPLIT DUCT)',      'keterangan' => 'A'],
            ['facility_name' => 'AC 02 (SPLIT DUCT)',      'keterangan' => 'A'],
            ['facility_name' => 'AC 03 (STANDING)',        'keterangan' => 'A'],
            ['facility_name' => 'AC 04 (STANDING)',        'keterangan' => 'A'],
            ['facility_name' => 'AC 05 (SPLIT WALL)',      'keterangan' => 'A'],
            ['facility_name' => 'AC 06 (SPLIT WALL)',      'keterangan' => 'A'],
            ['facility_name' => 'AC 07 (SPLIT WALL)',      'keterangan' => 'A'],
            ['facility_name' => 'AC 08 (SPLIT WALL)',      'keterangan' => 'A'],
            ['facility_name' => 'AC 09 (SPLIT WALL)',      'keterangan' => 'A'],
            ['facility_name' => 'AC 10 (SPLIT WALL)',      'keterangan' => 'A'],
            ['facility_name' => 'AC 11 (SPLIT WALL)',      'keterangan' => 'A'],
            ['facility_name' => 'AC 12 (SPLIT WALL)',      'keterangan' => 'A'],
            ['facility_name' => 'AC 13 (SPLIT WALL)',      'keterangan' => 'A'],
            ['facility_name' => 'Papan Nama AirNav',       'keterangan' => null],
            ['facility_name' => 'Pagar',                   'keterangan' => null],
            ['facility_name' => 'Rumput',                  'keterangan' => null],
            ['facility_name' => 'Door Lock',               'keterangan' => null],
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
                'radar_record_id'  => $recordId,
                'parameter_number' => $param['parameter_number'],
                'parameter_name'   => $param['parameter_name'],
                'unit'             => $param['unit'],
                'values'           => null,
                'is_disabled_map'  => empty($param['is_disabled_map']) ? null : json_encode($param['is_disabled_map']),
                'merge_map'        => empty($param['merge_map']) ? null : json_encode($param['merge_map']),
                'sort_order'       => $sortOrder++,
                'created_at'       => $now,
                'updated_at'       => $now,
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
                'radar_record_id' => $recordId,
                'facility_name'   => $facility['facility_name'],
                'kondisi'         => null,
                'keterangan'      => $facility['keterangan'],
                'sort_order'      => $sortOrder++,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }
        return $rows;
    }
}
