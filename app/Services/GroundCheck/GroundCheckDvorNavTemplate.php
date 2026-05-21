<?php

namespace App\Services\GroundCheck;

/**
 * GroundCheckDvorNavTemplate — Form 4 NAV-analyzer measurement items.
 *
 * Four parameter groups, each with rows under the columns
 *   REFF TOWER (TX1, TX2)  /  PERALATAN DVOR (TX1, TX2):
 *
 *   BEARING:
 *     1. Bearing (degree)
 *     2. FMI
 *   MODULASI:
 *     1. 30 Hz
 *     2. 9960 Hz
 *     3. RF Level
 *   IDENT INFORMATION:
 *     1. Ident Modulation
 *     2. Ident Frequency
 *   MEASUREMENT FREQ:
 *     1. 9960 Hz Freq
 *     2. Carrier Freq (KHz)
 *     3. 30 Hz Subcarrier
 *     4. 30 Hz Freq
 *
 * All 4 measurement columns start empty — teknisi fills manually.
 */
class GroundCheckDvorNavTemplate
{
    public static function items(): array
    {
        return [
            // ─── BEARING ──────────────────────────────────────────
            self::sectionHeader('bearing', 'BEARING'),
            self::row('bearing', 'BEARING', '1', 'Bearing (degree)'),
            self::row('bearing', 'BEARING', '2', 'FMI'),

            // ─── MODULASI ─────────────────────────────────────────
            self::sectionHeader('modulasi', 'MODULASI'),
            self::row('modulasi', 'MODULASI', '1', '30 Hz'),
            self::row('modulasi', 'MODULASI', '2', '9960 Hz'),
            self::row('modulasi', 'MODULASI', '3', 'RF Level'),

            // ─── IDENT INFORMATION ────────────────────────────────
            self::sectionHeader('ident', 'IDENT INFORMATION'),
            self::row('ident', 'IDENT INFORMATION', '1', 'Ident Modulation'),
            self::row('ident', 'IDENT INFORMATION', '2', 'Ident Frequency'),

            // ─── MEASUREMENT FREQ ─────────────────────────────────
            self::sectionHeader('meas_freq', 'MEASUREMENT FREQ'),
            self::row('meas_freq', 'MEASUREMENT FREQ', '1', '9960 Hz Freq.'),
            self::row('meas_freq', 'MEASUREMENT FREQ', '2', 'Carrier Freq. (KHz)'),
            self::row('meas_freq', 'MEASUREMENT FREQ', '3', '30 Hz Subcarrier'),
            self::row('meas_freq', 'MEASUREMENT FREQ', '4', '30 Hz Freq.'),
        ];
    }

    private static function sectionHeader(string $code, string $label): array
    {
        return [
            'section_code'      => $code,
            'section_label'     => $label,
            'item_code'         => null,
            'parameter_name'    => $label,
            'is_section_header' => true,
        ];
    }

    private static function row(string $code, string $label, string $itemCode, string $name): array
    {
        return [
            'section_code'      => $code,
            'section_label'     => $label,
            'item_code'         => $itemCode,
            'parameter_name'    => $name,
            'is_section_header' => false,
        ];
    }

    public static function buildRows(int $recordId): array
    {
        $rows = [];
        $sort = 0;
        $now = now()->toDateTimeString();

        foreach (self::items() as $item) {
            $rows[] = [
                'ground_check_dvor_record_id' => $recordId,
                'section_code'      => $item['section_code'],
                'section_label'     => $item['section_label'],
                'item_code'         => $item['item_code'],
                'parameter_name'    => $item['parameter_name'],
                'ref_tx1_value'     => null,
                'ref_tx2_value'     => null,
                'eq_tx1_value'      => null,
                'eq_tx2_value'      => null,
                'is_section_header' => $item['is_section_header'],
                'sort_order'        => $sort++,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
        }

        return $rows;
    }
}
