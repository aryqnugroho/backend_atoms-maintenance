<?php

namespace App\Services\Tfp;

/**
 * TfpTransmitterTxTemplate — canonical parameter and facility list for the
 * TFP Performance Check Gedung (Transmitter) TX form.
 *
 * 21 measurement parameters, 19 facility items.
 *
 * Panel columns (11 total):
 *   panel_tx01, panel_tx02 — single columns (no Input/Output split)
 *   panel_cos_tx03_input, panel_cos_tx03_output — Panel COS (TX 03)
 *   panel_output_ups_tx04 — single column
 *   panel_ups_tx07_input, panel_ups_tx07_output — Panel UPS (TX 07)
 *   panel_ac_tx06 — single column
 *   ups_piller_input, ups_piller_output — UPS PILLER
 *   panel_milat_ru11 — single column
 */
class TfpTransmitterTxTemplate
{
    public static function parameters(): array
    {
        $noDisabled = [];

        // Row 13 (Power Factor): TX01, TX02, Panel Output, Panel AC, Panel MILAT disabled
        $disabledPowerFactor = [
            'panel_tx01'            => true,
            'panel_tx02'            => true,
            'panel_output_ups_tx04' => true,
            'panel_ac_tx06'         => true,
            'panel_milat_ru11'      => true,
        ];

        // Rows 14-17 (Battery): TX01, TX02, Panel COS, Panel Output, Panel AC, Panel MILAT disabled
        $disabledBattery = [
            'panel_tx01'             => true,
            'panel_tx02'             => true,
            'panel_cos_tx03_input'   => true,
            'panel_cos_tx03_output'  => true,
            'panel_output_ups_tx04'  => true,
            'panel_ac_tx06'          => true,
            'panel_milat_ru11'       => true,
        ];

        // Rows 18-19 (Mode/Suplai): TX01, TX02, Panel Output, Panel AC, Panel MILAT disabled
        $disabledModeSupplai = [
            'panel_tx01'            => true,
            'panel_tx02'            => true,
            'panel_output_ups_tx04' => true,
            'panel_ac_tx06'         => true,
            'panel_milat_ru11'      => true,
        ];

        // Rows 20-21 (single value): only panel_tx01 active, rest disabled
        $disabledAllExceptFirst = [
            'panel_tx02'             => true,
            'panel_cos_tx03_input'   => true,
            'panel_cos_tx03_output'  => true,
            'panel_output_ups_tx04'  => true,
            'panel_ups_tx07_input'   => true,
            'panel_ups_tx07_output'  => true,
            'panel_ac_tx06'          => true,
            'ups_piller_input'       => true,
            'ups_piller_output'      => true,
            'panel_milat_ru11'       => true,
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
            ['parameter_number' => '20', 'parameter_name' => 'Suhu Ruangan',        'unit' => '°C',     'is_disabled_map' => $disabledAllExceptFirst],
            ['parameter_number' => '21', 'parameter_name' => 'KWH Meter',           'unit' => null,     'is_disabled_map' => $disabledAllExceptFirst],
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
            ['facility_name' => 'AC 02 (Split Wall)',        'keterangan' => 'A'],
            ['facility_name' => 'AC 03 (Split Wall)',        'keterangan' => 'A'],
            ['facility_name' => 'AC 04 (Split Wall)',        'keterangan' => 'A'],
            ['facility_name' => 'AC 05 (Split Wall)',        'keterangan' => 'A'],
            ['facility_name' => 'AC 06 (Split Wall)',        'keterangan' => 'A'],
            ['facility_name' => 'AC 07 (Split Wall)',        'keterangan' => 'A'],
            ['facility_name' => 'Exhaust Fan',               'keterangan' => null],
            ['facility_name' => 'Papan Nama AirNav',         'keterangan' => null],
            ['facility_name' => 'Rumput',                    'keterangan' => null],
            ['facility_name' => 'APAR/Fire Extinguisher',    'keterangan' => null],
            ['facility_name' => 'Atap',                      'keterangan' => null],
            ['facility_name' => 'Dinding',                   'keterangan' => null],
            ['facility_name' => 'Pintu',                     'keterangan' => null],
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
                'tx_record_id'           => $recordId,
                'parameter_number'       => $param['parameter_number'],
                'parameter_name'         => $param['parameter_name'],
                'unit'                   => $param['unit'],
                'panel_tx01'             => null,
                'panel_tx02'             => null,
                'panel_cos_tx03_input'   => null,
                'panel_cos_tx03_output'  => null,
                'panel_output_ups_tx04'  => null,
                'panel_ups_tx07_input'   => null,
                'panel_ups_tx07_output'  => null,
                'panel_ac_tx06'          => null,
                'ups_piller_input'       => null,
                'ups_piller_output'      => null,
                'panel_milat_ru11'       => null,
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
