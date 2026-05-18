<?php

namespace App\Services\Tfp;

/**
 * TfpRadarTemplate — Performance Check Gedung Radar form.
 * 24 parameters, 28 facilities.
 *
 * Panel columns (11): panel_rd01, panel_rd02, panel_cos_rd03_input/output,
 * ups_topaz_input/output, panel_rd04, panel_rd05, panel_rd06, panel_rd07, panel_rd08
 */
class TfpRadarTemplate
{
    public static function parameters(): array
    {
        $noDisabled = [];

        // Row 13 (Power Factor): RD01, RD02, RD04-RD08 disabled; COS RD03 + UPS TOPAZ active
        $disabledPowerFactor = [
            'panel_rd01' => true,
            'panel_rd02' => true,
            'panel_rd04' => true,
            'panel_rd05' => true,
            'panel_rd06' => true,
            'panel_rd07' => true,
            'panel_rd08' => true,
        ];

        // Rows 14-17 (Battery): RD01, RD02, COS RD03, RD04, RD05, RD06, RD08 disabled; UPS TOPAZ + RD07 active
        $disabledBattery = [
            'panel_rd01'             => true,
            'panel_rd02'             => true,
            'panel_cos_rd03_input'   => true,
            'panel_cos_rd03_output'  => true,
            'panel_rd04'             => true,
            'panel_rd05'             => true,
            'panel_rd06'             => true,
            'panel_rd08'             => true,
        ];

        // Rows 18-19 (Mode/Suplai): RD01, RD02, RD04-RD08 disabled; COS RD03 + UPS TOPAZ active
        $disabledModeSupplai = [
            'panel_rd01' => true,
            'panel_rd02' => true,
            'panel_rd04' => true,
            'panel_rd05' => true,
            'panel_rd06' => true,
            'panel_rd07' => true,
            'panel_rd08' => true,
        ];

        // Row 20 (kWh meter): single value in panel_rd01, rest disabled
        $disabledSingleValue = [
            'panel_rd02'             => true,
            'panel_cos_rd03_input'   => true,
            'panel_cos_rd03_output'  => true,
            'ups_topaz_input'        => true,
            'ups_topaz_output'       => true,
            'panel_rd04'             => true,
            'panel_rd05'             => true,
            'panel_rd06'             => true,
            'panel_rd07'             => true,
            'panel_rd08'             => true,
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
            ['parameter_number' => '20', 'parameter_name' => 'kWh meter',           'unit' => null,     'is_disabled_map' => $disabledSingleValue],
            ['parameter_number' => '21', 'parameter_name' => 'Suhu Ruang UPS',      'unit' => '°C',     'is_disabled_map' => $disabledSingleValue],
            ['parameter_number' => '22', 'parameter_name' => 'Suhu Ruang ADSB',     'unit' => '°C',     'is_disabled_map' => $disabledSingleValue],
            ['parameter_number' => '23', 'parameter_name' => 'Suhu Ruang Radar Head', 'unit' => '°C',   'is_disabled_map' => $disabledSingleValue],
            ['parameter_number' => '24', 'parameter_name' => 'Meter Air',           'unit' => null,     'is_disabled_map' => $disabledSingleValue],
        ];
    }

    public static function facilities(): array
    {
        return [
            ['facility_name' => 'Catu Daya Listrik',       'keterangan' => null],
            ['facility_name' => 'Penerangan Gedung',        'keterangan' => null],
            ['facility_name' => 'Penerangan Jalan (PJU)',   'keterangan' => null],
            ['facility_name' => 'Obstacle Light',           'keterangan' => null],
            ['facility_name' => 'Genset 150 KVA',           'keterangan' => null],
            ['facility_name' => 'UPS 150 KVA',              'keterangan' => null],
            ['facility_name' => 'AC 01 (SPLIT DUCT)',       'keterangan' => 'A'],
            ['facility_name' => 'AC 02 (SPLIT DUCT)',       'keterangan' => 'A'],
            ['facility_name' => 'AC 03 (STANDING)',          'keterangan' => 'A'],
            ['facility_name' => 'AC 04 (STANDING)',          'keterangan' => 'A'],
            ['facility_name' => 'AC 05 (SPLIT WALL)',        'keterangan' => 'A'],
            ['facility_name' => 'AC 06 (SPLIT WALL)',        'keterangan' => 'A'],
            ['facility_name' => 'AC 07 (SPLIT WALL)',        'keterangan' => 'A'],
            ['facility_name' => 'AC 08 (SPLIT WALL)',        'keterangan' => 'A'],
            ['facility_name' => 'AC 09 (SPLIT WALL)',        'keterangan' => 'A'],
            ['facility_name' => 'AC 10 (SPLIT WALL)',        'keterangan' => 'A'],
            ['facility_name' => 'AC 11 (SPLIT WALL)',        'keterangan' => 'A'],
            ['facility_name' => 'AC 12 (SPLIT WALL)',        'keterangan' => 'A'],
            ['facility_name' => 'AC 13 (SPLIT WALL)',        'keterangan' => 'A'],
            ['facility_name' => 'Papan Nama AirNav',         'keterangan' => null],
            ['facility_name' => 'Pagar',                     'keterangan' => null],
            ['facility_name' => 'Rumput',                    'keterangan' => null],
            ['facility_name' => 'Door Lock',                 'keterangan' => null],
            ['facility_name' => 'Atap',                      'keterangan' => null],
            ['facility_name' => 'Plafond',                   'keterangan' => null],
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
                'radar_record_id'        => $recordId,
                'parameter_number'       => $param['parameter_number'],
                'parameter_name'         => $param['parameter_name'],
                'unit'                   => $param['unit'],
                'panel_rd01'             => null, 'panel_rd02' => null,
                'panel_cos_rd03_input'   => null, 'panel_cos_rd03_output' => null,
                'ups_topaz_input'        => null, 'ups_topaz_output' => null,
                'panel_rd04'             => null, 'panel_rd05' => null,
                'panel_rd06'             => null, 'panel_rd07' => null, 'panel_rd08' => null,
                'is_disabled_map'        => empty($disabledMap) ? null : json_encode($disabledMap),
                'sort_order'             => $sortOrder++,
                'created_at'             => $now, 'updated_at' => $now,
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
                'created_at'      => $now, 'updated_at' => $now,
            ];
        }
        return $rows;
    }
}
