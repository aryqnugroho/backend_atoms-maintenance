<?php

namespace App\Services\GroundCheck;

/**
 * GroundCheckAdcTemplate — source of truth for ADC Ground Check form items.
 *
 * Based on the official paper form:
 * "PENGUJIAN BERKALA DI DARAT — PERALATAN FASELEKTRIK PENERBANGAN"
 * Equipment: ADC
 *
 * Sections: TRANSMITTER, RECEIVER, CONSOLE
 */
class GroundCheckAdcTemplate
{
    /**
     * Return the full template items for ADC Ground Check.
     *
     * Each item has:
     *   - section_name: section header (TRANSMITTER, RECEIVER, CONSOLE)
     *   - item_code: letter code (a, b, c, ...)
     *   - parameter_name: parameter description
     *   - calibration_result: prefilled "Hasil Pengukuran Setelah Kalibrasi" (reference standard)
     *   - tolerance: prefilled tolerance value
     *   - is_header: true for section headers (not editable)
     *
     * TX1/TX2 columns are always empty on new records — user fills manually.
     */
    public static function items(): array
    {
        return [
            // ─── TRANSMITTER ───────────────────────────────────
            [
                'section_name' => 'TRANSMITTER',
                'item_code' => null,
                'parameter_name' => 'TRANSMITTER',
                'calibration_result' => null,
                'tolerance' => null,
                'is_header' => true,
            ],
            [
                'section_name' => 'TRANSMITTER',
                'item_code' => 'a',
                'parameter_name' => 'Power',
                'calibration_result' => '30',
                'tolerance' => 'P.Comm -3 dB',
                'is_header' => false,
            ],
            [
                'section_name' => 'TRANSMITTER',
                'item_code' => 'b',
                'parameter_name' => 'Modulation (Tx)',
                'calibration_result' => '90',
                'tolerance' => '90 ± 5%',
                'is_header' => false,
            ],

            // ─── RECEIVER ──────────────────────────────────────
            [
                'section_name' => 'RECEIVER',
                'item_code' => null,
                'parameter_name' => 'RECEIVER',
                'calibration_result' => null,
                'tolerance' => null,
                'is_header' => true,
            ],
            [
                'section_name' => 'RECEIVER',
                'item_code' => 'a',
                'parameter_name' => 'Squelch On',
                'calibration_result' => null,
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'RECEIVER',
                'item_code' => 'b',
                'parameter_name' => 'Audio Distorsi',
                'calibration_result' => null,
                'tolerance' => null,
                'is_header' => false,
            ],

            // ─── CONSOLE ───────────────────────────────────────
            [
                'section_name' => 'CONSOLE',
                'item_code' => null,
                'parameter_name' => 'CONSOLE',
                'calibration_result' => null,
                'tolerance' => null,
                'is_header' => true,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 'a',
                'parameter_name' => 'Intercom Unit',
                'calibration_result' => 'Berfungsi',
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 'b',
                'parameter_name' => 'Manual Changeover Switch (main Standby)',
                'calibration_result' => 'Berfungsi',
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 'c',
                'parameter_name' => 'Wind Speed (kecepatan angin) indikator',
                'calibration_result' => 'Berfungsi',
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 'd',
                'parameter_name' => 'Wind direction (Arah angin) Indikator',
                'calibration_result' => 'Berfungsi',
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 'e',
                'parameter_name' => 'Temperatur (suhu) Indikator',
                'calibration_result' => 'Berfungsi',
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 'f',
                'parameter_name' => 'Pressure (tekanan angin) Indikator',
                'calibration_result' => 'Berfungsi',
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 'g',
                'parameter_name' => 'Crass Bell Function',
                'calibration_result' => 'Berfungsi',
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 'h',
                'parameter_name' => 'Headset menyatu dengan microphone',
                'calibration_result' => 'Berfungsi',
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 'i',
                'parameter_name' => 'Hand Microphone / Microphone meja',
                'calibration_result' => 'Berfungsi',
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 'j',
                'parameter_name' => 'PTT Foot Switch (saklar PTT pijak)',
                'calibration_result' => 'Berfungsi',
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 'k',
                'parameter_name' => 'Audio Control Unit / Monitor unit (Loud speaker)',
                'calibration_result' => 'Berfungsi',
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 'l',
                'parameter_name' => 'Clock Display',
                'calibration_result' => 'Berfungsi',
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 'm',
                'parameter_name' => 'Lampu meja operator',
                'calibration_result' => 'Berfungsi',
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 'n',
                'parameter_name' => 'Indikator lamp (Lampu Indikator)',
                'calibration_result' => 'Berfungsi',
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 'o',
                'parameter_name' => 'Interconnection (Connector, cable)',
                'calibration_result' => 'Baik',
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 'p',
                'parameter_name' => 'Antenna System (Tx/Rx)',
                'calibration_result' => 'Berfungsi',
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 'q',
                'parameter_name' => 'Recording',
                'calibration_result' => 'Berfungsi',
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 'r',
                'parameter_name' => 'Nav Aid monitor (monitor peralatan navigasi)',
                'calibration_result' => 'Berfungsi',
                'tolerance' => null,
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 's',
                'parameter_name' => 'Interference',
                'calibration_result' => 'CLEAR',
                'tolerance' => 'Clear',
                'is_header' => false,
            ],
            [
                'section_name' => 'CONSOLE',
                'item_code' => 't',
                'parameter_name' => 'Hot Line (Direct speech)',
                'calibration_result' => 'Berfungsi',
                'tolerance' => null,
                'is_header' => false,
            ],
        ];
    }

    /**
     * Build item rows ready for bulk insert.
     */
    public static function buildItemRows(int $recordId): array
    {
        $rows = [];
        $sort = 0;
        $now = now()->toDateTimeString();

        foreach (self::items() as $item) {
            $rows[] = [
                'ground_check_adc_record_id' => $recordId,
                'section_name' => $item['section_name'],
                'item_code' => $item['item_code'],
                'parameter_name' => $item['parameter_name'],
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
