<?php

namespace App\Services\Tfp;

/**
 * TfpDvorTemplate — Performance Check Gedung DVOR form.
 *
 * 21 parameters × 5 panels (6 cells total — panel_ats_d06 has Input/Output split):
 *   panel_d01 (1)     panel_d03 (1)     panel_d04 (1)
 *   panel_d05 (1)     panel_ats_d06 (Input/Output)
 *
 * Disabled cell rules:
 *   Rows 1-12  : all 6 cells enabled
 *   Row 13     : Power Factor — only panel_ats_d06 active (D.01/D.03/D.04/D.05 disabled)
 *   Rows 14-17 : Battery — only panel_ats_d06 active (D.01/D.03/D.04/D.05 disabled)
 *   Rows 18-19 : Mode/Suplai — D.01/D.03/D.04 disabled; D.05 + ATS/AMF active
 *   Row 20     : Suhu Ruangan — single value in panel_d05.value, merge_map=3 to span D.05 + IO
 *   Row 21     : KWH Meter — single value in panel_d05.value, merge_map=3 to span D.05 + IO
 */
class TfpDvorTemplate
{
    public static function defaultColumnsConfig(): array
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
        // Row 13 (Power Factor) + Rows 14-17 (Battery): only panel_ats_d06 active
        $disabledPowerBattery = [
            'panel_d01.value' => true,
            'panel_d03.value' => true,
            'panel_d04.value' => true,
            'panel_d05.value' => true,
        ];

        // Rows 18-19 (Mode/Suplai): D.01/D.03/D.04 disabled; D.05 + ATS active
        $disabledModeSupplai = [
            'panel_d01.value' => true,
            'panel_d03.value' => true,
            'panel_d04.value' => true,
        ];

        // Rows 20-21 (Suhu Ruangan / KWH Meter): single value at panel_d05.value,
        // merge_map=3 to span D.05 + ATS input/output
        $disabledSingle = [
            'panel_d01.value' => true,
            'panel_d03.value' => true,
            'panel_d04.value' => true,
        ];
        $mergeSingle = ['panel_d05.value' => 3];

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
            ['parameter_number' => '20', 'parameter_name' => 'Suhu Ruangan',          'unit' => '°C',     'is_disabled_map' => $disabledSingle,       'merge_map' => $mergeSingle],
            ['parameter_number' => '21', 'parameter_name' => 'KWH Meter',             'unit' => null,     'is_disabled_map' => $disabledSingle,       'merge_map' => $mergeSingle],
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
            ['facility_name' => 'Exhaust Fan 1',           'keterangan' => null],
            ['facility_name' => 'Exhaust Fan 2',           'keterangan' => null],
            ['facility_name' => 'Exhaust Fan 3',           'keterangan' => null],
            ['facility_name' => 'Exhaust Fan 4',           'keterangan' => null],
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
                'dvor_record_id'   => $recordId,
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
                'dvor_record_id' => $recordId,
                'facility_name'  => $facility['facility_name'],
                'kondisi'        => null,
                'keterangan'     => $facility['keterangan'],
                'sort_order'     => $sortOrder++,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }
        return $rows;
    }
}
