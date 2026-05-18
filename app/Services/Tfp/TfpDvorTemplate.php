<?php

namespace App\Services\Tfp;

/**
 * TfpDvorTemplate — Performance Check Gedung DVOR form.
 * 21 parameters, 15 facilities.
 *
 * Panel columns (6):
 *   panel_d01          — Panel D.01 (single)
 *   panel_d03          — Panel D.03 CCTV Indoor (single)
 *   panel_d04          — Panel D.04 CCTV Outdoor (single)
 *   panel_d05          — Panel Input D.05 (single)
 *   panel_ats_d06_input  — Panel ATS/AMF (D.06) Input
 *   panel_ats_d06_output — Panel ATS/AMF (D.06) Output
 *
 * Disabled cell rules (from form image):
 *   Rows 1-12  : all columns enabled
 *   Row 13     : Power Factor — D.01, D.03, D.04, D.05 disabled
 *   Rows 14-17 : Battery — D.01, D.03, D.04, D.05 disabled
 *   Rows 18-19 : Mode/Suplai — D.01, D.03, D.04 disabled (D.05 + ATS/AMF active)
 *   Rows 20-21 : single value in panel_d05, rest disabled
 */
class TfpDvorTemplate
{
    public static function parameters(): array
    {
        $noDisabled = [];

        // Row 13 (Power Factor): D.01, D.03, D.04, D.05 disabled
        $disabledPowerFactor = [
            'panel_d01' => true,
            'panel_d03' => true,
            'panel_d04' => true,
            'panel_d05' => true,
        ];

        // Rows 14-17 (Battery): D.01, D.03, D.04, D.05 disabled
        $disabledBattery = [
            'panel_d01' => true,
            'panel_d03' => true,
            'panel_d04' => true,
            'panel_d05' => true,
        ];

        // Rows 18-19 (Mode/Suplai): D.01, D.03, D.04 disabled; D.05 + ATS/AMF active
        $disabledModeSupplai = [
            'panel_d01' => true,
            'panel_d03' => true,
            'panel_d04' => true,
        ];

        // Rows 20-21 (single value): only panel_d05 active, rest disabled
        $disabledSingleValue = [
            'panel_d01'              => true,
            'panel_d03'              => true,
            'panel_d04'              => true,
            'panel_ats_d06_input'    => true,
            'panel_ats_d06_output'   => true,
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
            ['parameter_number' => '20', 'parameter_name' => 'Suhu Ruangan',        'unit' => '°C',     'is_disabled_map' => $disabledSingleValue],
            ['parameter_number' => '21', 'parameter_name' => 'KWH Meter',           'unit' => null,     'is_disabled_map' => $disabledSingleValue],
        ];
    }

    public static function facilities(): array
    {
        return [
            ['facility_name' => 'Catu Daya Listrik',       'keterangan' => null],
            ['facility_name' => 'Penerangan',               'keterangan' => null],
            ['facility_name' => 'Obstacle Light',           'keterangan' => null],
            ['facility_name' => 'AC 01 (Split Wall)',        'keterangan' => 'A'],
            ['facility_name' => 'AC 02 (Split Wall)',        'keterangan' => 'A'],
            ['facility_name' => 'Exhaust Fan 1',             'keterangan' => null],
            ['facility_name' => 'Exhaust Fan 2',             'keterangan' => null],
            ['facility_name' => 'Exhaust Fan 3',             'keterangan' => null],
            ['facility_name' => 'Exhaust Fan 4',             'keterangan' => null],
            ['facility_name' => 'Papan Nama AirNav',         'keterangan' => null],
            ['facility_name' => 'Rumput',                    'keterangan' => null],
            ['facility_name' => 'APAR/Fire Extinguisher',    'keterangan' => null],
            ['facility_name' => 'Atap',                      'keterangan' => null],
            ['facility_name' => 'Dinding',                   'keterangan' => null],
            ['facility_name' => 'Pintu',                     'keterangan' => null],
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
                'dvor_record_id'         => $recordId,
                'parameter_number'       => $param['parameter_number'],
                'parameter_name'         => $param['parameter_name'],
                'unit'                   => $param['unit'],
                'panel_d01'              => null,
                'panel_d03'              => null,
                'panel_d04'              => null,
                'panel_d05'              => null,
                'panel_ats_d06_input'    => null,
                'panel_ats_d06_output'   => null,
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
