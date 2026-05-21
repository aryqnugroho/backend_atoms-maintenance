<?php

namespace App\Services\GroundCheck;

/**
 * GroundCheckDvorTemplate — Form 3 "Pengujian Berkala di Darat" for DVOR.
 *
 * Hierarchy flags:
 *   - is_header     : top section banner ("BUILT IN TEST")
 *   - is_subheader  : info / sub-section row
 *   - is_disabled   : parent group row, no measurement ("MONITOR")
 *   - is_check_only : Hasil PD hidden; only IN/OUT TOLERANCE toggle active
 *                     (items 8-12: MANUAL CHANGE OVER, INTERKONEKSI,
 *                     CHANGE OVER TIME, INDICATOR LAMP & METERING,
 *                     REMOTE MONITORING)
 *
 * calibration_result pre-filled from paper example (editable).
 * Hasil PD TX1/TX2 left null — teknisi fills manually.
 */
class GroundCheckDvorTemplate
{
    public static function items(): array
    {
        return [
            // ═══ BUILT IN TEST ═══════════════════════════════════════
            self::header('BUILT IN TEST', 'BUILT IN TEST'),

            self::row('BUILT IN TEST', null, '1', 'FORWARD POWER (COVERAGE)',
                'numeric', '79,7 W', '3dB down'),
            self::row('BUILT IN TEST', null, '2', 'REVERSE POWER',
                'numeric', null, '5%'),
            self::row('BUILT IN TEST', null, '3', 'IDENTIFICATION SPEED',
                'numeric', '7 words/minute', '7 words/minute'),
            self::row('BUILT IN TEST', null, '4', 'IDENTIFICATION REPETITION',
                'numeric', '2 times/min', '2 times/min'),
            self::row('BUILT IN TEST', null, '5', 'FORWARD UPPER SIDE BAND POWER',
                'numeric', '6,73 W', '3dB down'),
            self::row('BUILT IN TEST', null, '6', 'FORWARD LOWER SIDE BAND POWER',
                'numeric', '5,98 W', '3dB down'),

            // 7. MONITOR (parent, disabled)
            self::disabledRow('BUILT IN TEST', null, '7', 'MONITOR'),
            self::row('BUILT IN TEST', 'MONITOR', null, 'BEARING',
                'numeric', '0,03°', null),
            self::row('BUILT IN TEST', 'MONITOR', null, 'MODULATION',
                'numeric', '31%', null),

            // 8-12: check-only (Hasil PD hidden)
            self::checkOnly('BUILT IN TEST', '8', 'MANUAL CHANGE OVER SWITCH'),
            self::checkOnly('BUILT IN TEST', '9', 'INTERKONEKSI'),
            // CHANGE OVER TIME on the paper form shows "2 sec" values — technically a numeric
            // measurement, but user opted for check-only behavior across items 8-12 for
            // operational uniformity ("Sama persis pola GP" selection during scaffolding).
            self::checkOnly('BUILT IN TEST', '10', 'CHANGE OVER TIME'),
            self::checkOnly('BUILT IN TEST', '11', 'INDICATOR LAMP & METERING'),
            self::checkOnly('BUILT IN TEST', '12', 'REMOTE MONITORING'),
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
                'ground_check_dvor_record_id' => $recordId,
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
