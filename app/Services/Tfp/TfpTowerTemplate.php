<?php

namespace App\Services\Tfp;

/**
 * TfpTowerTemplate — Performance Check Gedung Tower form.
 *
 * 23 parameters × 10 panels (11 cells total — panel_ats_a13 has Input/Output split):
 *   panel_a10 (1)     panel_a11 (1)     panel_ats_a13 (Input/Output)
 *   panel_a14 (1)     panel_a16 (1)     panel_a17 (1)
 *   panel_a18 (1)     panel_a19 (1)     panel_a20 (1)
 *   panel_milat_ru1213 (1)
 *
 * Disabled cell rules:
 *   Rows 1-12  : all 11 cells enabled
 *   Row 13     : Power Factor — only panel_ats_a13 active
 *   Rows 14-17 : Battery — ALL cells disabled (Tower has no battery — placeholder rows)
 *   Rows 18-19 : Mode/Suplai — only panel_ats_a13 active
 *   Row 20     : KWH Meter — single value in panel_ats_a13.input, merge_map=2 across IO
 *   Rows 21-23 : Suhu rows — single value in panel_a10, merge_map=11 to span all
 */
class TfpTowerTemplate
{
    public static function defaultColumnsConfig(): array
    {
        $single = [['key' => 'value',  'label' => 'Nilai']];
        $io     = [['key' => 'input',  'label' => 'Input'], ['key' => 'output', 'label' => 'Output']];

        return [
            ['id' => 'panel_a10',          'label' => 'Panel A 10',             'sub_columns' => $single],
            ['id' => 'panel_a11',          'label' => 'Panel A 11',             'sub_columns' => $single],
            ['id' => 'panel_ats_a13',      'label' => 'Panel ATS (A 13)',       'sub_columns' => $io],
            ['id' => 'panel_a14',          'label' => 'Panel A 14',             'sub_columns' => $single],
            ['id' => 'panel_a16',          'label' => 'Panel A 16',             'sub_columns' => $single],
            ['id' => 'panel_a17',          'label' => 'Panel A 17',             'sub_columns' => $single],
            ['id' => 'panel_a18',          'label' => 'Panel A 18',             'sub_columns' => $single],
            ['id' => 'panel_a19',          'label' => 'Panel A 19',             'sub_columns' => $single],
            ['id' => 'panel_a20',          'label' => 'Panel A 20',             'sub_columns' => $single],
            ['id' => 'panel_milat_ru1213', 'label' => 'Panel MILAT (RU 12/13)', 'sub_columns' => $single],
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

        // Row 13 (Power Factor): only panel_ats_a13 active
        $disabledPowerFactor = [
            'panel_a10.value'          => true,
            'panel_a11.value'          => true,
            'panel_a14.value'          => true,
            'panel_a16.value'          => true,
            'panel_a17.value'          => true,
            'panel_a18.value'          => true,
            'panel_a19.value'          => true,
            'panel_a20.value'          => true,
            'panel_milat_ru1213.value' => true,
        ];

        // Rows 14-17 (Battery): ALL cells disabled (Tower has no battery)
        $disabledBattery = array_fill_keys($allKeys, true);

        // Rows 18-19 (Mode/Suplai): same as Power Factor (only ATS active)
        $disabledModeSupplai = $disabledPowerFactor;

        // Row 20 (KWH Meter): single value at panel_ats_a13.input, merge_map=2 across IO
        $disabledKwh = [
            'panel_a10.value'          => true,
            'panel_a11.value'          => true,
            'panel_ats_a13.output'     => true,
            'panel_a14.value'          => true,
            'panel_a16.value'          => true,
            'panel_a17.value'          => true,
            'panel_a18.value'          => true,
            'panel_a19.value'          => true,
            'panel_a20.value'          => true,
            'panel_milat_ru1213.value' => true,
        ];
        $mergeKwh = ['panel_ats_a13.input' => 2];

        // Rows 21-23 (Suhu): single value in panel_a10.value, merge_map=11 to span all
        $disabledSuhu = array_fill_keys(
            array_values(array_filter($allKeys, fn ($k) => $k !== 'panel_a10.value')),
            true,
        );
        $mergeSuhu = ['panel_a10.value' => count($allKeys)];

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
            ['parameter_number' => '13', 'parameter_name' => 'Power Factor (Cos θ)',  'unit' => null,     'is_disabled_map' => $disabledPowerFactor,  'merge_map' => []],
            ['parameter_number' => '14', 'parameter_name' => 'Tegangan Battery',      'unit' => 'Volt',   'is_disabled_map' => $disabledBattery,      'merge_map' => []],
            ['parameter_number' => '15', 'parameter_name' => 'Arus Battery',          'unit' => 'Ampere', 'is_disabled_map' => $disabledBattery,      'merge_map' => []],
            ['parameter_number' => '16', 'parameter_name' => 'Kapasitas Battery',     'unit' => 'Ah',     'is_disabled_map' => $disabledBattery,      'merge_map' => []],
            ['parameter_number' => '17', 'parameter_name' => 'Suhu Battery',          'unit' => '°C',     'is_disabled_map' => $disabledBattery,      'merge_map' => []],
            ['parameter_number' => '18', 'parameter_name' => 'Mode *',                'unit' => null,     'is_disabled_map' => $disabledModeSupplai,  'merge_map' => []],
            ['parameter_number' => '19', 'parameter_name' => 'Suplai Aktif *',        'unit' => null,     'is_disabled_map' => $disabledModeSupplai,  'merge_map' => []],
            ['parameter_number' => '20', 'parameter_name' => 'KWH Meter',             'unit' => null,     'is_disabled_map' => $disabledKwh,          'merge_map' => $mergeKwh],
            ['parameter_number' => '21', 'parameter_name' => 'Suhu Tower Lt 11',      'unit' => '°C',     'is_disabled_map' => $disabledSuhu,         'merge_map' => $mergeSuhu],
            ['parameter_number' => '22', 'parameter_name' => 'Suhu Ruang RX',         'unit' => '°C',     'is_disabled_map' => $disabledSuhu,         'merge_map' => $mergeSuhu],
            ['parameter_number' => '23', 'parameter_name' => 'Suhu Cabin Tower',      'unit' => '°C',     'is_disabled_map' => $disabledSuhu,         'merge_map' => $mergeSuhu],
        ];
    }

    public static function facilities(): array
    {
        return [
            ['facility_name' => 'Catu Daya Listrik',       'keterangan' => null],
            ['facility_name' => 'Penerangan',              'keterangan' => null],
            ['facility_name' => 'Rotating Beacon',         'keterangan' => null],
            ['facility_name' => 'Hazard Beacon',           'keterangan' => null],
            ['facility_name' => 'AC 22 (Split Wall)',      'keterangan' => 'A'],
            ['facility_name' => 'AC 23 (Split Wall)',      'keterangan' => 'A'],
            ['facility_name' => 'Pompa Air Lt 5 Tower',    'keterangan' => null],
            ['facility_name' => 'Lift',                    'keterangan' => null],
            ['facility_name' => 'APAR/Fire Extinguisher',  'keterangan' => null],
            ['facility_name' => 'Atap',                    'keterangan' => null],
            ['facility_name' => 'Plafond',                 'keterangan' => null],
            ['facility_name' => 'Dinding',                 'keterangan' => null],
            ['facility_name' => 'Pintu',                   'keterangan' => null],
            ['facility_name' => 'Door Lock',               'keterangan' => null],
        ];
    }

    public static function buildItemRows(int $recordId): array
    {
        $rows = [];
        $sortOrder = 0;
        $now = now();
        foreach (self::parameters() as $param) {
            $rows[] = [
                'tower_record_id'  => $recordId,
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
                'tower_record_id' => $recordId,
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
