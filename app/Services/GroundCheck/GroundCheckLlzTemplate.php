<?php

namespace App\Services\GroundCheck;

/**
 * GroundCheckLlzTemplate — Form 1 "Pengujian Berkala di Darat" for ILS LOCALIZER.
 *
 * Three hierarchy flags:
 *   - is_header     : top section banner (PARAMETER PERALATAN / ADDITIONAL TEST EQUIPMENT)
 *   - is_subheader  : sub-section banner (BUILT IN TEST / SENSITIVITY / RADIATION)
 *   - is_disabled   : grey row, no TX measurement (INTERCONECTION / ANTENA / MONITORING: / etc.)
 *
 * calibration_result pre-filled with paper-form example values; teknisi can edit.
 * TX1 / TX2 hasil PD kept null — teknisi must fill manually.
 */
class GroundCheckLlzTemplate
{
    public static function items(): array
    {
        return [
            // ═══ SECTION: PARAMETER PERALATAN ════════════════════════
            self::header('PARAMETER PERALATAN', 'PARAMETER PERALATAN'),

            // ─── BUILT IN TEST subsection ─────────────────────────
            self::subheader('PARAMETER PERALATAN', 'BUILT IN TEST', 'BUILT IN TEST'),

            self::row('PARAMETER PERALATAN', 'BUILT IN TEST', '1', 'RF POWER LEVEL',
                'numeric', '15.9 W', 'P_OUT (Commissioning) -'),
            self::row('PARAMETER PERALATAN', 'BUILT IN TEST', '2', 'COURSE ALIGNMENT',
                'numeric', '0.01', '0.0000 DDM ±0,0015'),
            self::row('PARAMETER PERALATAN', 'BUILT IN TEST', '3', 'DEPTH OF MODULATION',
                'numeric', '20,50%', '20% ±2%'),
            self::row('PARAMETER PERALATAN', 'BUILT IN TEST', '4', 'SUM OF MODULATION DEPTHS',
                'numeric', '40,90%', '40% ±4%'),
            self::row('PARAMETER PERALATAN', 'BUILT IN TEST', '5', 'IDENTIFICATION MODULATION DEPTH',
                'numeric', '9.8%', '10% ± 5%'),

            // MONITORING: parent row (no measurement, just group label)
            self::disabledRow('PARAMETER PERALATAN', 'BUILT IN TEST', '6', 'MONITORING :'),

            // Direct children of MONITORING
            self::row('PARAMETER PERALATAN', 'MONITORING', null, '- COURSE SHIFT',
                'text', 'OK', '10.5 M'),
            self::row('PARAMETER PERALATAN', 'MONITORING', null, '- CHANGE IN DISPLACEMENT',
                'numeric', '0,155', '0,17'),

            // SENSITIVITY sub-group header
            self::subheader('PARAMETER PERALATAN', 'SENSITIVITY', 'SENSITIVITY'),
            self::row('PARAMETER PERALATAN', 'SENSITIVITY', null, '- CLEARANCE SIGNAL',
                'numeric', '150', '150 µA'),
            self::row('PARAMETER PERALATAN', 'SENSITIVITY', null, '- TOTAL TIME OF OUT OF TOLERANCE',
                'numeric', '10', '10 s'),

            // RADIATION sub-group header
            self::subheader('PARAMETER PERALATAN', 'RADIATION', 'RADIATION'),
            self::row('PARAMETER PERALATAN', 'RADIATION', null, '- REDUCTION IN POWER',
                'numeric', '0', '-3 dB dari Comm'),

            // Items 7-11 (gray rows for some, normal for one)
            self::disabledRow('PARAMETER PERALATAN', 'BUILT IN TEST', '7', 'INTERCONECTION'),
            self::disabledRow('PARAMETER PERALATAN', 'BUILT IN TEST', '8', 'ANTENA'),
            self::row('PARAMETER PERALATAN', 'BUILT IN TEST', '9', 'CHANGE OVER ( MAIN TO STANDBY )',
                'numeric', '2', '10 s'),
            self::disabledRow('PARAMETER PERALATAN', 'BUILT IN TEST', '10', 'INDICATOR LAMP & METERING'),
            self::disabledRow('PARAMETER PERALATAN', 'BUILT IN TEST', '11', 'REMOTE CONTROL AND MONITORING'),

            // ═══ SECTION: ADDITIONAL TEST EQUIPMENT ══════════════════
            self::header('ADDITIONAL TEST EQUIPMENT', 'ADDITIONAL TEST EQUIPMENT'),

            self::row('ADDITIONAL TEST EQUIPMENT', null, '1', 'RF POWER LEVEL',
                'numeric', '-28,5', 'Field strength Comm - 3'),
            self::row('ADDITIONAL TEST EQUIPMENT', null, '2', 'COURSE ALIGNMENT',
                'numeric', '9,6', '<10,5 meter (35 ft)'),
            self::row('ADDITIONAL TEST EQUIPMENT', null, '3', 'DISPLACEMENT SENSITIVITY',
                'numeric', '-', '0,00145 DDM/m ±17%'),
            self::row('ADDITIONAL TEST EQUIPMENT', null, '4', 'SPURIOUS MODULATION',
                'numeric', null, '<0,005 DDM peak to peak'),
            self::row('ADDITIONAL TEST EQUIPMENT', null, '5', 'DEPTH OF MODULATION',
                'numeric', '20.4', '20% ±2%'),
            self::row('ADDITIONAL TEST EQUIPMENT', null, '6', 'SUM OF MODULATION DEPTHS',
                'numeric', '40,86%', 'Modulation depth <95%'),
            self::row('ADDITIONAL TEST EQUIPMENT', null, '7', 'ORIENTATION',
                'text', 'CORRECT', 'Correct'),
            self::row('ADDITIONAL TEST EQUIPMENT', null, '8', 'FREQUENCY',
                'numeric', '10', "Single frequency :\nDual frequency : 0.002%\n5kHz < Diff. < 14 kHz"),
            self::row('ADDITIONAL TEST EQUIPMENT', null, '9', 'CARRIER MODULATION FREQUENCY',
                'numeric', '110,1', '±2.5%'),
            self::row('ADDITIONAL TEST EQUIPMENT', null, '10', 'IDENTIFICATION TONE FREQUENCY',
                'numeric', '1020', '1020 ±50Hz'),
            self::row('ADDITIONAL TEST EQUIPMENT', null, '11', 'IDENTIFICATION MODULATION DEPTH',
                'numeric', '10', '10% ± 5%'),
            self::row('ADDITIONAL TEST EQUIPMENT', null, '12', 'IDENTIFICATION SPEED',
                'text', 'OK', '7 words / menit'),
            self::row('ADDITIONAL TEST EQUIPMENT', null, '13', 'IDENTIFICATION REPETITION RATE',
                'text', 'OK', 'As commissioning'),
        ];
    }

    private static function header(string $section, string $label): array
    {
        return [
            'section_name'       => $section,
            'subsection_name'    => null,
            'item_code'          => null,
            'parameter_name'     => $label,
            'input_type'         => 'header',
            'calibration_result' => null,
            'tolerance'          => null,
            'is_header'          => true,
            'is_subheader'       => false,
            'is_disabled'        => false,
        ];
    }

    private static function subheader(string $section, string $subsection, string $label): array
    {
        return [
            'section_name'       => $section,
            'subsection_name'    => $subsection,
            'item_code'          => null,
            'parameter_name'     => $label,
            'input_type'         => 'header',
            'calibration_result' => null,
            'tolerance'          => null,
            'is_header'          => false,
            'is_subheader'       => true,
            'is_disabled'        => false,
        ];
    }

    private static function disabledRow(string $section, ?string $subsection, ?string $code, string $name): array
    {
        return [
            'section_name'       => $section,
            'subsection_name'    => $subsection,
            'item_code'          => $code,
            'parameter_name'     => $name,
            'input_type'         => 'text',
            'calibration_result' => null,
            'tolerance'          => null,
            'is_header'          => false,
            'is_subheader'       => false,
            'is_disabled'        => true,
        ];
    }

    private static function row(string $section, ?string $subsection, ?string $code, string $name,
                                string $inputType, ?string $calib, ?string $tol): array
    {
        return [
            'section_name'       => $section,
            'subsection_name'    => $subsection,
            'item_code'          => $code,
            'parameter_name'     => $name,
            'input_type'         => $inputType,
            'calibration_result' => $calib,
            'tolerance'          => $tol,
            'is_header'          => false,
            'is_subheader'       => false,
            'is_disabled'        => false,
        ];
    }

    public static function buildItemRows(int $recordId): array
    {
        $rows = [];
        $sort = 0;
        $now = now()->toDateTimeString();

        foreach (self::items() as $item) {
            $rows[] = [
                'ground_check_llz_record_id' => $recordId,
                'section_name'       => $item['section_name'],
                'subsection_name'    => $item['subsection_name'],
                'item_code'          => $item['item_code'],
                'parameter_name'     => $item['parameter_name'],
                'input_type'         => $item['input_type'] ?? 'text',
                'calibration_result' => $item['calibration_result'],
                'tolerance'          => $item['tolerance'],
                'tx1_hasil_pd'       => null,
                'tx1_in_tolerance'   => null,
                'tx1_out_of_tolerance' => null,
                'tx2_hasil_pd'       => null,
                'tx2_in_tolerance'   => null,
                'tx2_out_of_tolerance' => null,
                'keterangan'         => null,
                'is_header'          => $item['is_header'],
                'is_subheader'       => $item['is_subheader'],
                'is_disabled'        => $item['is_disabled'],
                'sort_order'         => $sort++,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        return $rows;
    }
}
