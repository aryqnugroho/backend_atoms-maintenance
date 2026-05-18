<?php

namespace App\Services\Tfp;

/**
 * TfpTowerTemplate — canonical parameter and facility list for the
 * TFP Performance Check Gedung Tower form.
 *
 * 23 measurement parameters, 14 facility items.
 *
 * Panel columns (11 total):
 *   panel_a10, panel_a11 — single columns
 *   panel_ats_a13_input, panel_ats_a13_output — Panel ATS (A 13)
 *   panel_a14, panel_a16, panel_a17, panel_a18, panel_a19, panel_a20 — single columns
 *   panel_milat_ru1213 — single column
 *
 * Disabled cell rules (from form image):
 *   Rows 1-12  : all columns enabled
 *   Row 13     : Power Factor — A10, A11, A14, A16, A17, A18, A19, A20, MILAT disabled
 *   Rows 14-17 : Battery — A10, A11, ATS input/output, A14, A16, A17, A18, A19, A20, MILAT disabled
 *   Rows 18-19 : Mode/Suplai — A10, A11, A14, A16, A17, A18, A19, A20, MILAT disabled
 *   Row 20     : KWH Meter — single value in panel_ats_a13_input, rest disabled
 *   Rows 21-23 : Suhu rows — single value in panel_a10, rest disabled
 */
class TfpTowerTemplate
{
    public static function parameters(): array
    {
        $noDisabled = [];

        // Row 13 (Power Factor): A10, A11, A14, A16, A17, A18, A19, A20, MILAT disabled
        $disabledPowerFactor = [
            'panel_a10'          => true,
            'panel_a11'          => true,
            'panel_a14'          => true,
            'panel_a16'          => true,
            'panel_a17'          => true,
            'panel_a18'          => true,
            'panel_a19'          => true,
            'panel_a20'          => true,
            'panel_milat_ru1213' => true,
        ];

        // Rows 14-17 (Battery): all columns disabled except none (all disabled)
        $disabledBattery = [
            'panel_a10'              => true,
            'panel_a11'              => true,
            'panel_ats_a13_input'    => true,
            'panel_ats_a13_output'   => true,
            'panel_a14'              => true,
            'panel_a16'              => true,
            'panel_a17'              => true,
            'panel_a18'              => true,
            'panel_a19'              => true,
            'panel_a20'              => true,
            'panel_milat_ru1213'     => true,
        ];

        // Rows 18-19 (Mode/Suplai): A10, A11, A14, A16, A17, A18, A19, A20, MILAT disabled
        $disabledModeSupplai = [
            'panel_a10'          => true,
            'panel_a11'          => true,
            'panel_a14'          => true,
            'panel_a16'          => true,
            'panel_a17'          => true,
            'panel_a18'          => true,
            'panel_a19'          => true,
            'panel_a20'          => true,
            'panel_milat_ru1213' => true,
        ];

        // Row 20 (KWH Meter): single value in panel_ats_a13_input, rest disabled
        $disabledKwh = [
            'panel_a10'              => true,
            'panel_a11'              => true,
            'panel_ats_a13_output'   => true,
            'panel_a14'              => true,
            'panel_a16'              => true,
            'panel_a17'              => true,
            'panel_a18'              => true,
            'panel_a19'              => true,
            'panel_a20'              => true,
            'panel_milat_ru1213'     => true,
        ];

        // Rows 21-23 (Suhu): single value in panel_a10, rest disabled
        $disabledSuhu = [
            'panel_a11'              => true,
            'panel_ats_a13_input'    => true,
            'panel_ats_a13_output'   => true,
            'panel_a14'              => true,
            'panel_a16'              => true,
            'panel_a17'              => true,
            'panel_a18'              => true,
            'panel_a19'              => true,
            'panel_a20'              => true,
            'panel_milat_ru1213'     => true,
        ];

        return [
            ['parameter_number' => '1',  'parameter_name' => 'L1 - N',              'unit' => 'Volt',   'is_disabled_map' => $noDisabled],
            ['parameter_number' => '2',  'parameter_name' => 'L2 - N',              'unit' => 'Volt',   'is_disabled_map' => $noDisabled],
            ['parameter_number' => '3',  'parameter_name' => 'L3 - N',              'unit' => 'Volt',   'is_disabled_map' => $noDisabled],
            ['parameter_number' => '4',  'parameter_name' => 'N - G',               'unit' => 'Volt',   'is_disabled_map' => $noDisabled],
            ['parameter_number' => '5',  'parameter_name' => 'L1 - L2',             'unit' => 'Volt',   'is_disabled_map' => $noDisabled],
            ['parameter_number' => '6',  'parameter_name' => 'L1 - L3',             'unit' => 'Volt',   'is_disabled_map' => $noDisabled],
            ['parameter_number' => '7',  'parameter_name' => 'L2 - L3',             'unit' => 'Volt',   'is_disabled_map' => $noDisabled],
            ['parameter_number' => '8',  'parameter_name' => 'L1',                  'unit' => 'Ampere', 'is_disabled_map' => $noDisabled],
            ['parameter_number' => '9',  'parameter_name' => 'L2',                  'unit' => 'Ampere', 'is_disabled_map' => $noDisabled],
            ['parameter_number' => '10', 'parameter_name' => 'L3',                  'unit' => 'Ampere', 'is_disabled_map' => $noDisabled],
            ['parameter_number' => '11', 'parameter_name' => 'N',                   'unit' => 'Ampere', 'is_disabled_map' => $noDisabled],
            ['parameter_number' => '12', 'parameter_name' => 'Frekuensi',           'unit' => 'Hz',     'is_disabled_map' => $noDisabled],
            ['parameter_number' => '13', 'parameter_name' => 'Power Factor (Cos θ)', 'unit' => null,    'is_disabled_map' => $disabledPowerFactor],
            ['parameter_number' => '14', 'parameter_name' => 'Tegangan Battery',    'unit' => 'Volt',   'is_disabled_map' => $disabledBattery],
            ['parameter_number' => '15', 'parameter_name' => 'Arus Battery',        'unit' => 'Ampere', 'is_disabled_map' => $disabledBattery],
            ['parameter_number' => '16', 'parameter_name' => 'Kapasitas Battery',   'unit' => 'Ah',     'is_disabled_map' => $disabledBattery],
            ['parameter_number' => '17', 'parameter_name' => 'Suhu Battery',        'unit' => '°C',     'is_disabled_map' => $disabledBattery],
            ['parameter_number' => '18', 'parameter_name' => 'Mode *',              'unit' => null,     'is_disabled_map' => $disabledModeSupplai],
            ['parameter_number' => '19', 'parameter_name' => 'Suplai Aktif *',      'unit' => null,     'is_disabled_map' => $disabledModeSupplai],
            ['parameter_number' => '20', 'parameter_name' => 'KWH Meter',           'unit' => null,     'is_disabled_map' => $disabledKwh],
            ['parameter_number' => '21', 'parameter_name' => 'Suhu Tower Lt 11',    'unit' => '°C',     'is_disabled_map' => $disabledSuhu],
            ['parameter_number' => '22', 'parameter_name' => 'Suhu Ruang RX',       'unit' => '°C',     'is_disabled_map' => $disabledSuhu],
            ['parameter_number' => '23', 'parameter_name' => 'Suhu Cabin Tower',    'unit' => '°C',     'is_disabled_map' => $disabledSuhu],
        ];
    }

    public static function facilities(): array
    {
        return [
            ['facility_name' => 'Catu Daya Listrik',       'keterangan' => null],
            ['facility_name' => 'Penerangan',               'keterangan' => null],
            ['facility_name' => 'Rotating Beacon',          'keterangan' => null],
            ['facility_name' => 'Hazard Beacon',            'keterangan' => null],
            ['facility_name' => 'AC 22 (Split Wall)',        'keterangan' => 'A'],
            ['facility_name' => 'AC 23 (Split Wall)',        'keterangan' => 'A'],
            ['facility_name' => 'Pompa Air Lt 5 Tower',     'keterangan' => null],
            ['facility_name' => 'Lift',                     'keterangan' => null],
            ['facility_name' => 'APAR/Fire Extinguisher',   'keterangan' => null],
            ['facility_name' => 'Atap',                     'keterangan' => null],
            ['facility_name' => 'Plafond',                  'keterangan' => null],
            ['facility_name' => 'Dinding',                  'keterangan' => null],
            ['facility_name' => 'Pintu',                    'keterangan' => null],
            ['facility_name' => 'Door Lock',                'keterangan' => null],
        ];
    }

    public static function buildItemRows(int $recordId): array
    {
        $rows = [];
        $sortOrder = 0;
        $now = now();
        foreach (self::parameters() as $param) {
            $disabledMap = $param['is_disabled_map'];
            $rows[] = [
                'tower_record_id'        => $recordId,
                'parameter_number'       => $param['parameter_number'],
                'parameter_name'         => $param['parameter_name'],
                'unit'                   => $param['unit'],
                'panel_a10'              => null,
                'panel_a11'              => null,
                'panel_ats_a13_input'    => null,
                'panel_ats_a13_output'   => null,
                'panel_a14'              => null,
                'panel_a16'              => null,
                'panel_a17'              => null,
                'panel_a18'              => null,
                'panel_a19'              => null,
                'panel_a20'              => null,
                'panel_milat_ru1213'     => null,
                'is_disabled_map'        => empty($disabledMap) ? null : json_encode($disabledMap),
                'sort_order'             => $sortOrder++,
                'created_at'             => $now,
                'updated_at'             => $now,
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
