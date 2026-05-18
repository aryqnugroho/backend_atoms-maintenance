<?php

namespace App\Services\Tfp;

/**
 * TfpLocalizerTemplate — Performance Check Gedung Localizer form.
 * 21 parameters, 13 facilities.
 *
 * Panel columns (7):
 *   panel_lz01              — Panel LZ 01 (single)
 *   panel_cos_lz02_input    — Panel COS (LZ 02) Input
 *   panel_cos_lz02_output   — Panel COS (LZ 02) Output
 *   panel_lz03              — Panel LZ 03 (single)
 *   panel_cos_lz04_input    — Panel COS (LZ 04) Input
 *   panel_cos_lz04_output   — Panel COS (LZ 04) Output
 *   panel_mlat_ru04         — Panel MLAT RU 04 (single)
 *
 * Disabled cell rules (from form image):
 *   Rows 1-12  : all columns enabled
 *   Row 13     : Power Factor — LZ01, LZ03, MLAT disabled
 *   Rows 14-17 : Battery — LZ01, LZ03, MLAT disabled
 *   Rows 18-19 : Mode/Suplai — LZ01, LZ03, MLAT disabled (COS LZ02 + COS LZ04 active)
 *   Rows 20-21 : single value in panel_lz01, rest disabled
 */
class TfpLocalizerTemplate
{
    public static function parameters(): array
    {
        $noDisabled = [];

        // Row 13 (Power Factor): LZ01, LZ03, MLAT disabled
        $disabledPowerFactor = [
            'panel_lz01'         => true,
            'panel_lz03'         => true,
            'panel_mlat_ru04'    => true,
        ];

        // Rows 14-17 (Battery): LZ01, LZ03, MLAT disabled
        $disabledBattery = [
            'panel_lz01'         => true,
            'panel_lz03'         => true,
            'panel_mlat_ru04'    => true,
        ];

        // Rows 18-19 (Mode/Suplai): LZ01, LZ03, MLAT disabled; COS LZ02 + COS LZ04 active
        $disabledModeSupplai = [
            'panel_lz01'         => true,
            'panel_lz03'         => true,
            'panel_mlat_ru04'    => true,
        ];

        // Rows 20-21 (single value): only panel_lz01 active, rest disabled
        $disabledSingleValue = [
            'panel_cos_lz02_input'  => true,
            'panel_cos_lz02_output' => true,
            'panel_lz03'            => true,
            'panel_cos_lz04_input'  => true,
            'panel_cos_lz04_output' => true,
            'panel_mlat_ru04'       => true,
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
            ['facility_name' => 'Exhaust Fan',               'keterangan' => 'A'],
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
                'localizer_record_id'    => $recordId,
                'parameter_number'       => $param['parameter_number'],
                'parameter_name'         => $param['parameter_name'],
                'unit'                   => $param['unit'],
                'panel_lz01'             => null,
                'panel_cos_lz02_input'   => null,
                'panel_cos_lz02_output'  => null,
                'panel_lz03'             => null,
                'panel_cos_lz04_input'   => null,
                'panel_cos_lz04_output'  => null,
                'panel_mlat_ru04'        => null,
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
