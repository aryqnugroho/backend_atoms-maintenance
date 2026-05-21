<?php

namespace App\Services\GroundCheck;

/**
 * GroundCheckVhfTemplate — source of truth for VHF Ground Check Form 1.
 *
 * Based on the official paper form:
 *   "PENGUJIAN BERKALA DI DARAT — PERALATAN FASLEKTRIK PENERBANGAN" (VHF)
 *
 * Sections: TRANSMITTER, RECEIVER, CONSOLE.
 *
 * `input_type` drives the frontend widget per row:
 *   - numeric                : numeric/text input (Power, Modulation, Squelch)
 *   - dropdown_function      : Berfungsi / Tidak Berfungsi
 *   - dropdown_quality       : Baik / Tidak Baik
 *   - dropdown_clarity       : Clear / Tidak Clear
 *   - dropdown_completeness  : Lengkap / Tidak Lengkap   (NEW vs ADC)
 *   - dropdown_normality     : Normal / Tidak Normal     (NEW vs ADC)
 *   - text                   : free-text fallback (Audio Distortion)
 *   - header                 : section header row, no input
 */
class GroundCheckVhfTemplate
{
    public static function items(): array
    {
        return [
            // ─── TRANSMITTER ───────────────────────────────────
            [
                'section_name' => 'TRANSMITTER',
                'item_code' => null,
                'parameter_name' => 'TRANSMITTER',
                'input_type' => 'header',
                'calibration_result' => null,
                'tolerance' => null,
                'is_header' => true,
            ],
            [
                'section_name' => 'TRANSMITTER',
                'item_code' => 'a',
                'parameter_name' => 'Power',
                'input_type' => 'numeric',
                'calibration_result' => null,
                'tolerance' => 'P.Comm -3 dB',
                'is_header' => false,
            ],
            [
                'section_name' => 'TRANSMITTER',
                'item_code' => 'b',
                'parameter_name' => 'Modulation (TX)',
                'input_type' => 'numeric',
                'calibration_result' => null,
                'tolerance' => '90 ± 5%',
                'is_header' => false,
            ],

            // ─── RECEIVER ──────────────────────────────────────
            [
                'section_name' => 'RECEIVER',
                'item_code' => null,
                'parameter_name' => 'RECEIVER',
                'input_type' => 'header',
                'calibration_result' => null,
                'tolerance' => null,
                'is_header' => true,
            ],
            [
                'section_name' => 'RECEIVER',
                'item_code' => 'a',
                'parameter_name' => 'Squelch On',
                'input_type' => 'numeric',
                'calibration_result' => null,
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'RECEIVER',
                'item_code' => 'b',
                'parameter_name' => 'Audio Distortion',
                'input_type' => 'text',
                'calibration_result' => null,
                'tolerance' => null,
                'is_header' => false,
            ],

            // ─── CONSOLE ───────────────────────────────────────
            [
                'section_name' => 'CONSOLE',
                'item_code' => null,
                'parameter_name' => 'CONSOLE',
                'input_type' => 'header',
                'calibration_result' => null,
                'tolerance' => null,
                'is_header' => true,
            ],
            ['section_name' => 'CONSOLE', 'item_code' => 'a', 'parameter_name' => 'Intercom Unit',                                     'input_type' => 'dropdown_function',     'calibration_result' => 'Berfungsi', 'tolerance' => null, 'is_header' => false],
            ['section_name' => 'CONSOLE', 'item_code' => 'b', 'parameter_name' => 'Manual Changeover Unit (Main Standby)',            'input_type' => 'dropdown_function',     'calibration_result' => 'Berfungsi', 'tolerance' => null, 'is_header' => false],
            ['section_name' => 'CONSOLE', 'item_code' => 'c', 'parameter_name' => 'Wind Speed (Kecepatan Angin) Indikator',           'input_type' => 'dropdown_function',     'calibration_result' => 'Berfungsi', 'tolerance' => null, 'is_header' => false],
            ['section_name' => 'CONSOLE', 'item_code' => 'd', 'parameter_name' => 'Wind Direction (Arah Angin) Indikator',            'input_type' => 'dropdown_function',     'calibration_result' => 'Berfungsi', 'tolerance' => null, 'is_header' => false],
            ['section_name' => 'CONSOLE', 'item_code' => 'e', 'parameter_name' => 'Temperature (Suhu) Indikator',                     'input_type' => 'dropdown_function',     'calibration_result' => 'Berfungsi', 'tolerance' => null, 'is_header' => false],
            ['section_name' => 'CONSOLE', 'item_code' => 'f', 'parameter_name' => 'Tekanan Udara (Pressure) Indikator',               'input_type' => 'dropdown_function',     'calibration_result' => 'Berfungsi', 'tolerance' => null, 'is_header' => false],
            ['section_name' => 'CONSOLE', 'item_code' => 'g', 'parameter_name' => 'Headset menyatu dengan Microphone',                'input_type' => 'dropdown_function',     'calibration_result' => 'Berfungsi', 'tolerance' => null, 'is_header' => false],
            ['section_name' => 'CONSOLE', 'item_code' => 'h', 'parameter_name' => 'Hand Microphone / Microphone Meja',                'input_type' => 'dropdown_function',     'calibration_result' => 'Berfungsi', 'tolerance' => null, 'is_header' => false],
            ['section_name' => 'CONSOLE', 'item_code' => 'i', 'parameter_name' => 'PTT Foot Switch (saklar PTT pijak)',               'input_type' => 'dropdown_function',     'calibration_result' => 'Berfungsi', 'tolerance' => null, 'is_header' => false],
            ['section_name' => 'CONSOLE', 'item_code' => 'j', 'parameter_name' => 'Audio Control Unit / Monitor Unit (Loudspeaker)',  'input_type' => 'dropdown_function',     'calibration_result' => 'Berfungsi', 'tolerance' => null, 'is_header' => false],
            ['section_name' => 'CONSOLE', 'item_code' => 'k', 'parameter_name' => 'Clock display',                                    'input_type' => 'dropdown_function',     'calibration_result' => 'Berfungsi', 'tolerance' => null, 'is_header' => false],
            ['section_name' => 'CONSOLE', 'item_code' => 'l', 'parameter_name' => 'Lampu meja operator / Dimmer',                     'input_type' => 'dropdown_function',     'calibration_result' => 'Berfungsi', 'tolerance' => null, 'is_header' => false],
            ['section_name' => 'CONSOLE', 'item_code' => 'm', 'parameter_name' => 'Interconnection (connector, cable)',               'input_type' => 'dropdown_quality',      'calibration_result' => 'Baik',      'tolerance' => null, 'is_header' => false],
            ['section_name' => 'CONSOLE', 'item_code' => 'n', 'parameter_name' => 'Indicator Lamp',                                   'input_type' => 'dropdown_completeness', 'calibration_result' => 'Lengkap',   'tolerance' => null, 'is_header' => false],
            ['section_name' => 'CONSOLE', 'item_code' => 'o', 'parameter_name' => 'Antenna System',                                   'input_type' => 'dropdown_normality',    'calibration_result' => 'Normal',    'tolerance' => null, 'is_header' => false],
            ['section_name' => 'CONSOLE', 'item_code' => 'p', 'parameter_name' => 'Recording',                                        'input_type' => 'dropdown_function',     'calibration_result' => 'Berfungsi', 'tolerance' => null, 'is_header' => false],
            ['section_name' => 'CONSOLE', 'item_code' => 'q', 'parameter_name' => 'Nav Aid Monitor',                                  'input_type' => 'dropdown_completeness', 'calibration_result' => 'Lengkap',   'tolerance' => null, 'is_header' => false],
            ['section_name' => 'CONSOLE', 'item_code' => 'r', 'parameter_name' => 'Interference',                                     'input_type' => 'dropdown_clarity',      'calibration_result' => 'Clear',     'tolerance' => 'Clear', 'is_header' => false],
            // Paper form prints "d." for the last row, which appears to be a typesetting typo
            // for "s." (continuing the a-r sequence). We use "s" as the logical item code.
            ['section_name' => 'CONSOLE', 'item_code' => 's', 'parameter_name' => 'Hot Line (Direct Speech)',                         'input_type' => 'dropdown_function',     'calibration_result' => 'Berfungsi', 'tolerance' => null, 'is_header' => false],
        ];
    }

    public static function buildItemRows(int $recordId): array
    {
        $rows = [];
        $sort = 0;
        $now = now()->toDateTimeString();

        foreach (self::items() as $item) {
            $rows[] = [
                'ground_check_vhf_record_id' => $recordId,
                'section_name' => $item['section_name'],
                'item_code' => $item['item_code'],
                'parameter_name' => $item['parameter_name'],
                'input_type' => $item['input_type'] ?? 'text',
                'calibration_result' => $item['calibration_result'],
                'tolerance' => $item['tolerance'],
                'tx1_hasil_pd' => null,
                'tx1_in_tolerance' => null,
                'tx1_out_of_tolerance' => null,
                'tx2_hasil_pd' => null,
                'tx2_in_tolerance' => null,
                'tx2_out_of_tolerance' => null,
                'keterangan' => null,
                'is_header' => $item['is_header'],
                'sort_order' => $sort++,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }
}
