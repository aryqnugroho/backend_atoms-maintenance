<?php

namespace App\Services\Tfp;

/**
 * TfpAobGroundTemplate — canonical parameter and facility list for the
 * TFP Performance Check AOB Lantai Ground form.
 *
 * The backend uses this template at create-time to seed
 * tfp_aob_ground_items and tfp_aob_ground_facilities for every new record.
 * Users only fill in the measurement values — they never define items themselves.
 *
 * Form structure:
 *   21 measurement parameters (voltage, current, frequency, battery, mode, etc.)
 *   17 facility items (power supply, lighting, UPS, AC units, building elements)
 *
 * Disabled cell rules (is_disabled_map):
 *   - Rows 1-12  (voltage/current/frequency): all 8 columns enabled
 *   - Row 13     (Power Factor): UPS TESCOM A/B columns disabled
 *   - Rows 14-17 (Battery params): Panel COS A03 and Panel ATS A12 columns disabled
 *   - Row 18     (Mode): UPS TESCOM A/B columns disabled
 *   - Row 19     (Suplai Aktif): UPS TESCOM A/B columns disabled
 *   - Row 20     (KWH Meter): single value in panel_cos_a03_input, rest disabled
 *   - Row 21     (Suhu Eq. Room): single value in panel_cos_a03_input, rest disabled
 */
class TfpAobGroundTemplate
{
    /**
     * Returns the canonical list of measurement parameters.
     *
     * Each item shape:
     *   - parameter_number : visible number on the form (1-21)
     *   - parameter_name   : display label
     *   - unit             : measurement unit (Volt, Ampere, Hz, °C, or null)
     *   - is_disabled_map  : array of column keys => true for disabled cells
     */
    public static function parameters(): array
    {
        $noDisabled = [];

        // UPS TESCOM A/B disabled (Power Factor row)
        $disabledUps = [
            'ups_tescom_a_input'  => true,
            'ups_tescom_a_output' => true,
            'ups_tescom_b_input'  => true,
            'ups_tescom_b_output' => true,
        ];

        // Panel COS A03 and Panel ATS A12 disabled (Battery rows)
        $disabledPanels = [
            'panel_cos_a03_input'  => true,
            'panel_cos_a03_output' => true,
            'panel_ats_a12_input'  => true,
            'panel_ats_a12_output' => true,
        ];

        // Single-value rows: only panel_cos_a03_input is active, rest disabled
        $disabledAllExceptFirst = [
            'panel_cos_a03_output' => true,
            'panel_ats_a12_input'  => true,
            'panel_ats_a12_output' => true,
            'ups_tescom_a_input'   => true,
            'ups_tescom_a_output'  => true,
            'ups_tescom_b_input'   => true,
            'ups_tescom_b_output'  => true,
        ];

        return [
            // ── Voltage (L-N) ──────────────────────────────────
            ['parameter_number' => '1',  'parameter_name' => 'L1 - N',              'unit' => 'Volt',    'is_disabled_map' => $noDisabled],
            ['parameter_number' => '2',  'parameter_name' => 'L2 - N',              'unit' => 'Volt',    'is_disabled_map' => $noDisabled],
            ['parameter_number' => '3',  'parameter_name' => 'L3 - N',              'unit' => 'Volt',    'is_disabled_map' => $noDisabled],
            ['parameter_number' => '4',  'parameter_name' => 'N - G',               'unit' => 'Volt',    'is_disabled_map' => $noDisabled],
            // ── Voltage (L-L) ──────────────────────────────────
            ['parameter_number' => '5',  'parameter_name' => 'L1 - L2',             'unit' => 'Volt',    'is_disabled_map' => $noDisabled],
            ['parameter_number' => '6',  'parameter_name' => 'L1 - L3',             'unit' => 'Volt',    'is_disabled_map' => $noDisabled],
            ['parameter_number' => '7',  'parameter_name' => 'L2 - L3',             'unit' => 'Volt',    'is_disabled_map' => $noDisabled],
            // ── Current ────────────────────────────────────────
            ['parameter_number' => '8',  'parameter_name' => 'L1',                  'unit' => 'Ampere',  'is_disabled_map' => $noDisabled],
            ['parameter_number' => '9',  'parameter_name' => 'L2',                  'unit' => 'Ampere',  'is_disabled_map' => $noDisabled],
            ['parameter_number' => '10', 'parameter_name' => 'L3',                  'unit' => 'Ampere',  'is_disabled_map' => $noDisabled],
            ['parameter_number' => '11', 'parameter_name' => 'N',                   'unit' => 'Ampere',  'is_disabled_map' => $noDisabled],
            // ── Frequency ──────────────────────────────────────
            ['parameter_number' => '12', 'parameter_name' => 'Frekuensi',           'unit' => 'Hz',      'is_disabled_map' => $noDisabled],
            // ── Power Factor ───────────────────────────────────
            ['parameter_number' => '13', 'parameter_name' => 'Power Factor (Cos Θ)', 'unit' => null,     'is_disabled_map' => $disabledUps],
            // ── Battery ────────────────────────────────────────
            ['parameter_number' => '14', 'parameter_name' => 'Tegangan Battery',    'unit' => 'Volt',    'is_disabled_map' => $disabledPanels],
            ['parameter_number' => '15', 'parameter_name' => 'Arus Battery',        'unit' => 'Ampere',  'is_disabled_map' => $disabledPanels],
            ['parameter_number' => '16', 'parameter_name' => 'Kapasitas Battery',   'unit' => 'Ah',      'is_disabled_map' => $disabledPanels],
            ['parameter_number' => '17', 'parameter_name' => 'Suhu Battery',        'unit' => '°C',      'is_disabled_map' => $disabledPanels],
            // ── Mode / Suplai ──────────────────────────────────
            ['parameter_number' => '18', 'parameter_name' => 'Mode *',              'unit' => null,      'is_disabled_map' => $disabledUps],
            ['parameter_number' => '19', 'parameter_name' => 'Suplai Aktif *',      'unit' => null,      'is_disabled_map' => $disabledUps],
            // ── Single-value rows ──────────────────────────────
            ['parameter_number' => '20', 'parameter_name' => 'KWH Meter',           'unit' => null,      'is_disabled_map' => $disabledAllExceptFirst],
            ['parameter_number' => '21', 'parameter_name' => 'Suhu Eq. Room',       'unit' => '°C',      'is_disabled_map' => $disabledAllExceptFirst],
        ];
    }

    /**
     * Returns the canonical list of facility items.
     *
     * Each item shape:
     *   - facility_name : display label
     */
    public static function facilities(): array
    {
        return [
            ['facility_name' => 'Catu Daya Listrik'],
            ['facility_name' => 'Penerangan'],
            ['facility_name' => 'UPS Tescom A'],
            ['facility_name' => 'UPS Tescom B'],
            ['facility_name' => 'AC 01 (Split Wall) Eq'],
            ['facility_name' => 'AC 02 (Split Wall) Eq'],
            ['facility_name' => 'AC 03 (Split Wall) Eq'],
            ['facility_name' => 'AC 04 (Split Wall) Eq'],
            ['facility_name' => 'AC 05 (Split Wall) Gd 21'],
            ['facility_name' => 'AC 06 (Split Wall) Ex MCC'],
            ['facility_name' => 'AC 08 (Split Wall) ARO'],
            ['facility_name' => 'Papan Nama AirNav'],
            ['facility_name' => 'Atap'],
            ['facility_name' => 'Plafond'],
            ['facility_name' => 'Dinding'],
            ['facility_name' => 'Pintu'],
            ['facility_name' => 'Door Lock'],
        ];
    }

    /**
     * Flatten parameters into DB row inserts for tfp_aob_ground_items.
     */
    public static function buildItemRows(int $recordId): array
    {
        $rows = [];
        $sortOrder = 0;
        $now = now();

        foreach (self::parameters() as $param) {
            $disabledMap = $param['is_disabled_map'];

            $rows[] = [
                'aob_ground_record_id'  => $recordId,
                'parameter_number'      => $param['parameter_number'],
                'parameter_name'        => $param['parameter_name'],
                'unit'                  => $param['unit'],
                'panel_cos_a03_input'   => null,
                'panel_cos_a03_output'  => null,
                'panel_ats_a12_input'   => null,
                'panel_ats_a12_output'  => null,
                'ups_tescom_a_input'    => null,
                'ups_tescom_a_output'   => null,
                'ups_tescom_b_input'    => null,
                'ups_tescom_b_output'   => null,
                'is_disabled_map'       => empty($disabledMap) ? null : json_encode($disabledMap),
                'sort_order'            => $sortOrder++,
                'created_at'            => $now,
                'updated_at'            => $now,
            ];
        }

        return $rows;
    }

    /**
     * Flatten facilities into DB row inserts for tfp_aob_ground_facilities.
     */
    public static function buildFacilityRows(int $recordId): array
    {
        $rows = [];
        $sortOrder = 0;
        $now = now();

        foreach (self::facilities() as $facility) {
            $rows[] = [
                'aob_ground_record_id' => $recordId,
                'facility_name'        => $facility['facility_name'],
                'kondisi'              => null,
                'keterangan'           => null,
                'sort_order'           => $sortOrder++,
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }

        return $rows;
    }
}
