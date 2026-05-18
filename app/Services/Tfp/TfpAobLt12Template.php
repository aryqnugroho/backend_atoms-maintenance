<?php

namespace App\Services\Tfp;

/**
 * TfpAobLt12Template — canonical parameter and facility list for the
 * TFP Performance Check AOB Lantai 1 & 2 form.
 *
 * The backend uses this template at create-time to seed
 * tfp_aob_lt12_items and tfp_aob_lt12_facilities for every new record.
 * Users only fill in the measurement values — they never define items themselves.
 *
 * Form structure:
 *   21 measurement parameters (voltage, current, frequency, battery, mode, etc.)
 *   24 facility items (power supply, lighting, AC units, building elements)
 *
 * Panel columns (6 panels, each single value — NO Input/Output split):
 *   - panel_a05_app_room   → Panel A 05 APP Room
 *   - panel_a06_app_room   → Panel A 06 APP Room
 *   - panel_a07_app_room   → Panel A 07 APP Room
 *   - panel_a08_gudang_lt1 → Panel A 08 Gudang Lt 1
 *   - panel_a22_gudang_lt1 → Panel A 22 Gudang Lt 1
 *   - panel_a09_amsc_room  → Panel A 09 AMSC Room
 *
 * Disabled cell rules (is_disabled_map):
 *   - Rows 1-12  (voltage/current/frequency): all 6 columns enabled
 *   - Row 13     (Power Factor): panel_a09_amsc_room disabled
 *   - Rows 14-17 (Battery params): panel_a05, a06, a07 disabled
 *   - Row 18     (Mode *): panel_a08, a22, a09 disabled (only a05, a06, a07 active)
 *   - Row 19     (Suplai Aktif *): same as Mode
 *   - Row 20     (Suhu APP Room): single value in panel_a05_app_room, rest disabled
 *   - Row 21     (Suhu AMSC Room): single value in panel_a09_amsc_room, rest disabled
 */
class TfpAobLt12Template
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

        // Row 13 (Power Factor): panel_a09_amsc_room disabled
        $disabledAmsc = [
            'panel_a09_amsc_room' => true,
        ];

        // Rows 14-17 (Battery): panel_a05, a06, a07 disabled
        $disabledAppRooms = [
            'panel_a05_app_room' => true,
            'panel_a06_app_room' => true,
            'panel_a07_app_room' => true,
        ];

        // Rows 18-19 (Mode / Suplai Aktif): panel_a08, a22, a09 disabled
        $disabledGudangAmsc = [
            'panel_a08_gudang_lt1' => true,
            'panel_a22_gudang_lt1' => true,
            'panel_a09_amsc_room'  => true,
        ];

        // Row 20 (Suhu APP Room): single value in panel_a05_app_room, rest disabled
        $disabledAllExceptA05 = [
            'panel_a06_app_room'   => true,
            'panel_a07_app_room'   => true,
            'panel_a08_gudang_lt1' => true,
            'panel_a22_gudang_lt1' => true,
            'panel_a09_amsc_room'  => true,
        ];

        // Row 21 (Suhu AMSC Room): single value in panel_a09_amsc_room, rest disabled
        $disabledAllExceptA09 = [
            'panel_a05_app_room'   => true,
            'panel_a06_app_room'   => true,
            'panel_a07_app_room'   => true,
            'panel_a08_gudang_lt1' => true,
            'panel_a22_gudang_lt1' => true,
        ];

        return [
            // ── Voltage (L-N) ──────────────────────────────────
            ['parameter_number' => '1',  'parameter_name' => 'L1 - N',               'unit' => 'Volt',   'is_disabled_map' => $noDisabled],
            ['parameter_number' => '2',  'parameter_name' => 'L2 - N',               'unit' => 'Volt',   'is_disabled_map' => $noDisabled],
            ['parameter_number' => '3',  'parameter_name' => 'L3 - N',               'unit' => 'Volt',   'is_disabled_map' => $noDisabled],
            ['parameter_number' => '4',  'parameter_name' => 'N - G',                'unit' => 'Volt',   'is_disabled_map' => $noDisabled],
            // ── Voltage (L-L) ──────────────────────────────────
            ['parameter_number' => '5',  'parameter_name' => 'L1 - L2',              'unit' => 'Volt',   'is_disabled_map' => $noDisabled],
            ['parameter_number' => '6',  'parameter_name' => 'L1 - L3',              'unit' => 'Volt',   'is_disabled_map' => $noDisabled],
            ['parameter_number' => '7',  'parameter_name' => 'L2 - L3',              'unit' => 'Volt',   'is_disabled_map' => $noDisabled],
            // ── Current ────────────────────────────────────────
            ['parameter_number' => '8',  'parameter_name' => 'L1',                   'unit' => 'Ampere', 'is_disabled_map' => $noDisabled],
            ['parameter_number' => '9',  'parameter_name' => 'L2',                   'unit' => 'Ampere', 'is_disabled_map' => $noDisabled],
            ['parameter_number' => '10', 'parameter_name' => 'L3',                   'unit' => 'Ampere', 'is_disabled_map' => $noDisabled],
            ['parameter_number' => '11', 'parameter_name' => 'N',                    'unit' => 'Ampere', 'is_disabled_map' => $noDisabled],
            // ── Frequency ──────────────────────────────────────
            ['parameter_number' => '12', 'parameter_name' => 'Frekuensi',            'unit' => 'Hz',     'is_disabled_map' => $noDisabled],
            // ── Power Factor ───────────────────────────────────
            ['parameter_number' => '13', 'parameter_name' => 'Power Factor (Cos Θ)', 'unit' => null,     'is_disabled_map' => $disabledAmsc],
            // ── Battery ────────────────────────────────────────
            ['parameter_number' => '14', 'parameter_name' => 'Tegangan Battery',     'unit' => 'Volt',   'is_disabled_map' => $disabledAppRooms],
            ['parameter_number' => '15', 'parameter_name' => 'Arus Battery',         'unit' => 'Ampere', 'is_disabled_map' => $disabledAppRooms],
            ['parameter_number' => '16', 'parameter_name' => 'Kapasitas Battery',    'unit' => 'Ah',     'is_disabled_map' => $disabledAppRooms],
            ['parameter_number' => '17', 'parameter_name' => 'Suhu Battery',         'unit' => '°C',     'is_disabled_map' => $disabledAppRooms],
            // ── Mode / Suplai ──────────────────────────────────
            ['parameter_number' => '18', 'parameter_name' => 'Mode *',               'unit' => null,     'is_disabled_map' => $disabledGudangAmsc],
            ['parameter_number' => '19', 'parameter_name' => 'Suplai Aktif *',       'unit' => null,     'is_disabled_map' => $disabledGudangAmsc],
            // ── Single-value rows ──────────────────────────────
            ['parameter_number' => '20', 'parameter_name' => 'Suhu APP Room',        'unit' => '°C',     'is_disabled_map' => $disabledAllExceptA05],
            ['parameter_number' => '21', 'parameter_name' => 'Suhu AMSC Room',       'unit' => '°C',     'is_disabled_map' => $disabledAllExceptA09],
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
            ['facility_name' => 'AC 09 (Split Wall) GM'],
            ['facility_name' => 'AC 10 (Split Wall) Sek.GM'],
            ['facility_name' => 'AC 11 (Split Wall) ATS'],
            ['facility_name' => 'AC 12 (Split Wall) Staf FAM'],
            ['facility_name' => 'AC 13 (Split Wall) FAM'],
            ['facility_name' => 'AC 14 (Split Wall) Rest APP'],
            ['facility_name' => 'AC 15 (Split Wall) Kmr L'],
            ['facility_name' => 'AC 16 (Split Wall) Kmr P'],
            ['facility_name' => 'AC 17 (Split Wall) Sms'],
            ['facility_name' => 'AC 18 (Standing Floor) APP'],
            ['facility_name' => 'AC 19 (Standing Floor) APP'],
            ['facility_name' => 'AC 20 (Split Wall) AMSC'],
            ['facility_name' => 'AC 21 (Split Wall) ATIS'],
            ['facility_name' => 'Exhaust Fan Gudang Lt 1'],
            ['facility_name' => 'Exhaust Fan Toilet L/P'],
            ['facility_name' => 'Exhaust Fan Pantry'],
            ['facility_name' => 'APAR / Fire Extinguisher'],
            ['facility_name' => 'Atap'],
            ['facility_name' => 'Plafond'],
            ['facility_name' => 'Dinding'],
            ['facility_name' => 'Pintu'],
            ['facility_name' => 'Door Lock'],
        ];
    }

    /**
     * Flatten parameters into DB row inserts for tfp_aob_lt12_items.
     */
    public static function buildItemRows(int $recordId): array
    {
        $rows = [];
        $sortOrder = 0;
        $now = now();

        foreach (self::parameters() as $param) {
            $disabledMap = $param['is_disabled_map'];

            $rows[] = [
                'aob_lt12_record_id'   => $recordId,
                'parameter_number'     => $param['parameter_number'],
                'parameter_name'       => $param['parameter_name'],
                'unit'                 => $param['unit'],
                'panel_a05_app_room'   => null,
                'panel_a06_app_room'   => null,
                'panel_a07_app_room'   => null,
                'panel_a08_gudang_lt1' => null,
                'panel_a22_gudang_lt1' => null,
                'panel_a09_amsc_room'  => null,
                'is_disabled_map'      => empty($disabledMap) ? null : json_encode($disabledMap),
                'sort_order'           => $sortOrder++,
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }

        return $rows;
    }

    /**
     * Flatten facilities into DB row inserts for tfp_aob_lt12_facilities.
     */
    public static function buildFacilityRows(int $recordId): array
    {
        $rows = [];
        $sortOrder = 0;
        $now = now();

        foreach (self::facilities() as $facility) {
            $rows[] = [
                'aob_lt12_record_id' => $recordId,
                'facility_name'      => $facility['facility_name'],
                'kondisi'            => null,
                'keterangan'         => null,
                'sort_order'         => $sortOrder++,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        return $rows;
    }
}
