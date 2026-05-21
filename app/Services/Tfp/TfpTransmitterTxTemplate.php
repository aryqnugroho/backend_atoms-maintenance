<?php

namespace App\Services\Tfp;

/**
 * TfpTransmitterTxTemplate — canonical parameter and facility list for the
 * TFP Performance Check Gedung (Transmitter) TX form.
 *
 * 21 parameters × 8 panels (11 cells total — some panels have Input/Output split):
 *   panel_tx01 (1)         panel_tx02 (1)            panel_cos_tx03 (Input/Output)
 *   panel_output_ups_tx04  panel_ups_tx07 (I/O)      panel_ac_tx06 (1)
 *   ups_piller (I/O)       panel_milat_ru11 (1)
 *
 * Disabled cell rules (per row):
 *   - Rows 1-12 : all 11 cells enabled
 *   - Row 13    : Power Factor disables tx01/tx02/output-ups/ac/milat
 *   - Rows 14-17: Battery disables tx01/tx02/cos/output-ups/ac/milat
 *   - Rows 18-19: Mode/Suplai disables tx01/tx02/output-ups/ac/milat
 *   - Rows 20-21: Suhu/KWH single value in panel_tx01.value (others disabled)
 */
class TfpTransmitterTxTemplate
{
    public static function defaultColumnsConfig(): array
    {
        $single = [['key' => 'value',  'label' => 'Nilai']];
        $io     = [['key' => 'input',  'label' => 'Input'], ['key' => 'output', 'label' => 'Output']];

        return [
            ['id' => 'panel_tx01',            'label' => 'Panel TX 01',            'sub_columns' => $single],
            ['id' => 'panel_tx02',            'label' => 'Panel TX 02',            'sub_columns' => $single],
            ['id' => 'panel_cos_tx03',        'label' => 'Panel COS (TX 03)',      'sub_columns' => $io],
            ['id' => 'panel_output_ups_tx04', 'label' => 'Panel Output UPS TX 04', 'sub_columns' => $single],
            ['id' => 'panel_ups_tx07',        'label' => 'Panel UPS (TX 07)',      'sub_columns' => $io],
            ['id' => 'panel_ac_tx06',         'label' => 'Panel AC (TX 06)',       'sub_columns' => $single],
            ['id' => 'ups_piller',            'label' => 'UPS PILLER',             'sub_columns' => $io],
            ['id' => 'panel_milat_ru11',      'label' => 'Panel MILAT (RU 11)',    'sub_columns' => $single],
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

        // Row 13 (Power Factor): single-value panels + ac + milat disabled
        $disabledPowerFactor = [
            'panel_tx01.value'            => true,
            'panel_tx02.value'            => true,
            'panel_output_ups_tx04.value' => true,
            'panel_ac_tx06.value'         => true,
            'panel_milat_ru11.value'      => true,
        ];

        // Rows 14-17 (Battery): also disables panel_cos
        $disabledBattery = [
            'panel_tx01.value'            => true,
            'panel_tx02.value'            => true,
            'panel_cos_tx03.input'        => true,
            'panel_cos_tx03.output'       => true,
            'panel_output_ups_tx04.value' => true,
            'panel_ac_tx06.value'         => true,
            'panel_milat_ru11.value'      => true,
        ];

        // Rows 18-19: same as Power Factor (Mode/Suplai)
        $disabledModeSupplai = $disabledPowerFactor;

        // Rows 20-21: only panel_tx01.value is active, rest disabled.
        // The active cell spans all 11 cells via merge_map.
        $disabledAllExceptFirst = array_fill_keys(
            array_values(array_filter($allKeys, fn ($k) => $k !== 'panel_tx01.value')),
            true,
        );
        $singleValueMerge = ['panel_tx01.value' => count($allKeys)];

        return [
            ['parameter_number' => '1',  'parameter_name' => 'L1 - N',               'unit' => 'Volt',   'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '2',  'parameter_name' => 'L2 - N',               'unit' => 'Volt',   'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '3',  'parameter_name' => 'L3 - N',               'unit' => 'Volt',   'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '4',  'parameter_name' => 'N - G',                'unit' => 'Volt',   'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '5',  'parameter_name' => 'L1 - L2',              'unit' => 'Volt',   'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '6',  'parameter_name' => 'L1 - L3',              'unit' => 'Volt',   'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '7',  'parameter_name' => 'L2 - L3',              'unit' => 'Volt',   'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '8',  'parameter_name' => 'L1',                   'unit' => 'Ampere', 'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '9',  'parameter_name' => 'L2',                   'unit' => 'Ampere', 'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '10', 'parameter_name' => 'L3',                   'unit' => 'Ampere', 'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '11', 'parameter_name' => 'N',                    'unit' => 'Ampere', 'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '12', 'parameter_name' => 'Frekuensi',            'unit' => 'Hz',     'is_disabled_map' => [],                       'merge_map' => []],
            ['parameter_number' => '13', 'parameter_name' => 'Power Factor (Cos θ)', 'unit' => null,     'is_disabled_map' => $disabledPowerFactor,     'merge_map' => []],
            ['parameter_number' => '14', 'parameter_name' => 'Tegangan Battery',     'unit' => 'Volt',   'is_disabled_map' => $disabledBattery,         'merge_map' => []],
            ['parameter_number' => '15', 'parameter_name' => 'Arus Battery',         'unit' => 'Ampere', 'is_disabled_map' => $disabledBattery,         'merge_map' => []],
            ['parameter_number' => '16', 'parameter_name' => 'Kapasitas Battery',    'unit' => 'Ah',     'is_disabled_map' => $disabledBattery,         'merge_map' => []],
            ['parameter_number' => '17', 'parameter_name' => 'Suhu Battery',         'unit' => '°C',     'is_disabled_map' => $disabledBattery,         'merge_map' => []],
            ['parameter_number' => '18', 'parameter_name' => 'Mode *',               'unit' => null,     'is_disabled_map' => $disabledModeSupplai,     'merge_map' => []],
            ['parameter_number' => '19', 'parameter_name' => 'Suplai Aktif *',       'unit' => null,     'is_disabled_map' => $disabledModeSupplai,     'merge_map' => []],
            ['parameter_number' => '20', 'parameter_name' => 'Suhu Ruangan',         'unit' => '°C',     'is_disabled_map' => $disabledAllExceptFirst,  'merge_map' => $singleValueMerge],
            ['parameter_number' => '21', 'parameter_name' => 'KWH Meter',            'unit' => null,     'is_disabled_map' => $disabledAllExceptFirst,  'merge_map' => $singleValueMerge],
        ];
    }

    public static function facilities(): array
    {
        return [
            ['facility_name' => 'Catu Daya Listrik',       'keterangan' => null],
            ['facility_name' => 'Penerangan',               'keterangan' => null],
            ['facility_name' => 'Obstacle Light',           'keterangan' => null],
            ['facility_name' => 'UPS 30 KVA PILLER',        'keterangan' => null],
            ['facility_name' => 'ETS 30 KVA',               'keterangan' => 'U/S - Di OFF kan'],
            ['facility_name' => 'AC 02 (Split Wall)',       'keterangan' => 'A'],
            ['facility_name' => 'AC 03 (Split Wall)',       'keterangan' => 'A'],
            ['facility_name' => 'AC 04 (Split Wall)',       'keterangan' => 'A'],
            ['facility_name' => 'AC 05 (Split Wall)',       'keterangan' => 'A'],
            ['facility_name' => 'AC 06 (Split Wall)',       'keterangan' => 'A'],
            ['facility_name' => 'AC 07 (Split Wall)',       'keterangan' => 'A'],
            ['facility_name' => 'Exhaust Fan',              'keterangan' => null],
            ['facility_name' => 'Papan Nama AirNav',        'keterangan' => null],
            ['facility_name' => 'Rumput',                   'keterangan' => null],
            ['facility_name' => 'APAR/Fire Extinguisher',   'keterangan' => null],
            ['facility_name' => 'Atap',                     'keterangan' => null],
            ['facility_name' => 'Dinding',                  'keterangan' => null],
            ['facility_name' => 'Pintu',                    'keterangan' => null],
            ['facility_name' => 'Pintu',                    'keterangan' => null],
        ];
    }

    public static function buildItemRows(int $recordId): array
    {
        $rows = [];
        $sortOrder = 0;
        $now = now();
        foreach (self::parameters() as $param) {
            $rows[] = [
                'tx_record_id'     => $recordId,
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
                'tx_record_id'  => $recordId,
                'facility_name' => $facility['facility_name'],
                'kondisi'       => null,
                'keterangan'    => $facility['keterangan'],
                'sort_order'    => $sortOrder++,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }
        return $rows;
    }
}
