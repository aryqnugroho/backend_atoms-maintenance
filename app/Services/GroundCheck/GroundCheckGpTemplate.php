<?php

namespace App\Services\GroundCheck;

/**
 * GroundCheckGpTemplate — Form 1 "Pengujian Berkala di Darat" for ILS GLIDE PATH.
 *
 * Hierarchy flags:
 *   - is_header     : top section banner ("BUILT IN TEST" / "ADDITIONAL TEST EQUIPMENT")
 *   - is_subheader  : info row ("(90 Hz + 150 Hz)")
 *   - is_disabled   : parent group row, no measurement ("MONITORING :")
 *   - is_check_only : Hasil PD hidden; only IN/OUT TOLERANCE toggle active
 *                    (INTERCONECTION / ANTENA / INDICATOR LAMP / REMOTE CONTROL / SYSTEM)
 *
 * calibration_result pre-filled from paper example (editable).
 * Hasil PD TX1/TX2 left null — teknisi fills manually.
 */
class GroundCheckGpTemplate
{
    public static function items(): array
    {
        return [
            // ═══ BUILT IN TEST ═══════════════════════════════════════
            self::header('BUILT IN TEST', 'BUILT IN TEST'),

            self::row('BUILT IN TEST', null, '1', 'RF POWER LEVEL',
                'numeric', '3 V', 'Pout Comissioning - 3 db'),
            self::row('BUILT IN TEST', null, '2', 'PATH ALIGNMENT',
                'numeric', '0', '0.000 DDM ± 0.055 DDM'),
            self::row('BUILT IN TEST', null, '3', 'DEPTH OF MODULATION',
                'numeric', '40,10%', '40% ±2,5%'),
            self::row('BUILT IN TEST', null, '4', 'SUM OF DEPTH OF MODULATION',
                'numeric', '80,20%', '80 % ± 5 %'),
            self::infoRow('BUILT IN TEST', '(90 Hz + 150 Hz)'),

            // 5. MONITORING (parent — no measurement)
            self::disabledRow('BUILT IN TEST', null, '5', 'MONITORING :'),
            self::row('BUILT IN TEST', 'MONITORING', null, '- TOTAL TIME OF OUT OF TOLERANCE RADIATION',
                'numeric', null, '6 s'),
            self::row('BUILT IN TEST', 'MONITORING', null, '- REDUCTION IN POWER',
                'numeric', '1', '-3 dB dari Comm'),
            self::row('BUILT IN TEST', 'MONITORING', null, '- PATH ANGLE',
                'text', null, 'Monitor must alarm for a change in angle of 7.5% of the promulgated angle.'),
            self::row('BUILT IN TEST', 'MONITORING', null, '- DISPLACEMENT SENSITIVITY',
                'text', null, 'Monitor must alarm for a change in the angle between the glide path and the line below the glide path at which 75µA is obtained, by more than 3,75% of path angle.'),
            self::row('BUILT IN TEST', 'MONITORING', null, '- CLEARANCE SIGNAL',
                'text', null, "DDM<0.175   BELOW\nPATH CLEARANCE"),

            // Items 6, 7, 9, 10, 11 are check-only (Hasil PD hidden, only IN/OUT TOL toggle)
            // Item 8 is normal (numeric)
            self::checkOnly('BUILT IN TEST', '6', 'INTERCONECTION'),
            self::checkOnly('BUILT IN TEST', '7', 'ANTENA'),
            self::row('BUILT IN TEST', null, '8', 'CHANGE OVER ( MAIN TO STANDBY )',
                'numeric', '2', '10 s'),
            self::checkOnly('BUILT IN TEST', '9', 'INDICATOR LAMP & METERING'),
            self::checkOnly('BUILT IN TEST', '10', 'REMOTE CONTROL AND MONITORING'),
            self::checkOnly('BUILT IN TEST', '11', 'SYSTEM'),

            // ═══ ADDITIONAL TEST EQUIPMENT ═══════════════════════════
            self::header('ADDITIONAL TEST EQUIPMENT', 'ADDITIONAL TEST EQUIPMENT'),

            self::row('ADDITIONAL TEST EQUIPMENT', null, '1', 'RF POWER LEVEL',
                'numeric', '-20,5 dBm', 'Field strength Comm - 3 db'),
            self::row('ADDITIONAL TEST EQUIPMENT', null, '2', 'PATH ALIGNMENT',
                'numeric', '0,005 DDM', '0.000 DDM ± 0.055 DDM'),
            self::row('ADDITIONAL TEST EQUIPMENT', null, '3', 'PATH WIDTH',
                'numeric', null, '0.175 DDM ± 0.050 DDM'),
            self::row('ADDITIONAL TEST EQUIPMENT', null, '4', 'DEPTH OF MODULATION',
                'numeric', '40,32%', '40% ±2,5%'),
            self::row('ADDITIONAL TEST EQUIPMENT', null, '5', 'SUM OF DEPTH OF MODULATION',
                'numeric', '80,65%', '80 % ± 5 %'),
            self::infoRow('ADDITIONAL TEST EQUIPMENT', '(90 Hz + 150 Hz)'),

            self::row('ADDITIONAL TEST EQUIPMENT', null, '6', 'ORIENTATION',
                'text', 'CORRECT', 'Correct'),
            self::row('ADDITIONAL TEST EQUIPMENT', null, '7', 'FREQUENCY',
                'numeric', null, "Single : 0.005%\nDual : 0.002%\n4kHz < Diff <32kHz"),
            self::row('ADDITIONAL TEST EQUIPMENT', null, '8', 'CARRIER MODULATION FREQUENCY',
                'numeric', '334,4', '±2.5%'),
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
            'is_check_only'      => false,
        ];
    }

    /** Italic info sub-row, e.g. "(90 Hz + 150 Hz)" annotation. */
    private static function infoRow(string $section, string $label): array
    {
        return [
            'section_name'       => $section,
            'subsection_name'    => null,
            'item_code'          => null,
            'parameter_name'     => $label,
            'input_type'         => 'header',
            'calibration_result' => null,
            'tolerance'          => null,
            'is_header'          => false,
            'is_subheader'       => true,
            'is_disabled'        => false,
            'is_check_only'      => false,
        ];
    }

    /** Group parent row with no measurement (e.g. "MONITORING :"). */
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
            'is_check_only'      => false,
        ];
    }

    /** Check-only row: Hasil PD hidden, IN/OUT TOL toggle only, Hasil Kalibrasi default "✓". */
    private static function checkOnly(string $section, ?string $code, string $name): array
    {
        return [
            'section_name'       => $section,
            'subsection_name'    => null,
            'item_code'          => $code,
            'parameter_name'     => $name,
            'input_type'         => 'text',
            'calibration_result' => '✓',
            'tolerance'          => null,
            'is_header'          => false,
            'is_subheader'       => false,
            'is_disabled'        => false,
            'is_check_only'      => true,
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
            'is_check_only'      => false,
        ];
    }

    public static function buildItemRows(int $recordId): array
    {
        $rows = [];
        $sort = 0;
        $now = now()->toDateTimeString();

        foreach (self::items() as $item) {
            $rows[] = [
                'ground_check_gp_record_id' => $recordId,
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
                'is_check_only'      => $item['is_check_only'],
                'sort_order'         => $sort++,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        return $rows;
    }
}
