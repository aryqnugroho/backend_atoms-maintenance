<?php

namespace App\Services\GroundCheck;

/**
 * GroundCheckGpNavTemplate — Form 2 NAV-analyzer measurement items.
 *
 * Two sections per paper form:
 *   Section "NF" (Near Field):
 *     1. DDM (%)
 *     2. SDM (%)
 *     3. RF Level (dBm)
 *   Section "RCMS Transmitter Setting"   (keterangan: "PEMBACAAN DI RCMS TRANSMITTER SETTING"):
 *     1. DDM (%)
 *     2. SDM (%)
 *     3. COU SBO(V)
 *     4. CLR MOD BALANCE (%)
 *
 * Each row records TRANSMITER 1 and TRANSMITER 2 values.
 */
class GroundCheckGpNavTemplate
{
    public static function items(): array
    {
        $rcmsKeterangan = 'PEMBACAAN DI RCMS TRANSMITTER SETTING';

        return [
            // ─── NF section ────────────────────────────────────────
            self::sectionHeader('nf', 'NF'),
            self::row('nf', 'NF', null, '1', 'DDM (%)'),
            self::row('nf', 'NF', null, '2', 'SDM (%)'),
            self::row('nf', 'NF', null, '3', 'RF Level (dBm)'),

            // ─── RCMS section ──────────────────────────────────────
            self::sectionHeader('rcms', 'RCMS Transmitter Setting'),
            self::row('rcms', 'RCMS Transmitter Setting', $rcmsKeterangan, '1', 'DDM (%)'),
            self::row('rcms', 'RCMS Transmitter Setting', $rcmsKeterangan, '2', 'SDM (%)'),
            self::row('rcms', 'RCMS Transmitter Setting', $rcmsKeterangan, '3', 'COU SBO(V)'),
            self::row('rcms', 'RCMS Transmitter Setting', $rcmsKeterangan, '4', 'CLR MOD BALANCE (%)'),
        ];
    }

    private static function sectionHeader(string $code, string $label): array
    {
        return [
            'section_code'       => $code,
            'section_label'      => $label,
            'section_keterangan' => null,
            'item_code'          => null,
            'parameter_name'     => $label,
            'is_section_header'  => true,
        ];
    }

    private static function row(string $code, string $label, ?string $sectionKet, string $itemCode, string $name): array
    {
        return [
            'section_code'       => $code,
            'section_label'      => $label,
            'section_keterangan' => $sectionKet,
            'item_code'          => $itemCode,
            'parameter_name'     => $name,
            'is_section_header'  => false,
        ];
    }

    public static function buildRows(int $recordId): array
    {
        $rows = [];
        $sort = 0;
        $now = now()->toDateTimeString();

        foreach (self::items() as $item) {
            $rows[] = [
                'ground_check_gp_record_id' => $recordId,
                'section_code'       => $item['section_code'],
                'section_label'      => $item['section_label'],
                'section_keterangan' => $item['section_keterangan'],
                'item_code'          => $item['item_code'],
                'parameter_name'     => $item['parameter_name'],
                'tx1_value'          => null,
                'tx2_value'          => null,
                'keterangan'         => null,
                'is_section_header'  => $item['is_section_header'],
                'sort_order'         => $sort++,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        return $rows;
    }
}
