<?php

namespace App\Services\Cnsd;

/**
 * CnsdVccsMeterTemplate — canonical VCCS LES Meter Reading item list,
 * mirrored from the official "METER READING — VCCS" paper form
 * used by AirNav Surabaya (merk LES).
 *
 * Form structure (per the reference image, section A. PERALATAN
 * is split into 3 sub-sections so each can have its own column layout):
 *
 *   Section 1 — FRONT PANEL  (inputs_layout: dual_adaptive)
 *     Items adapt per-row to the nominal:
 *       - "Normal / Alrm" → Server A & B toggles (NORMAL / ALARM)
 *       - "√ / -"         → Server A & B toggles (√ / -)
 *       - "48 V"          → Server A & B free-text/number input
 *     Columns: NO | PEMBACAAN METER READING | NOMINAL (Standart) |
 *              HASIL (Server A | Server B) | KETERANGAN
 *
 *   Section 2 — MSC & RCMS  (inputs_layout: dual_toggle_nf)
 *     Two columns of √ / - toggles labeled Normal / Fault.
 *     Items grouped by Frame 1 (MPU/EXP) and Frame 2 (ALT/ASL/BSS/DRV/RIU).
 *
 *   Section 3 — CWP  (inputs_layout: dual_toggle_nf)
 *     Same dual Normal / Fault toggle as MSC & RCMS, items CWP 1..12.
 *
 *   Section 4 — LINGKUNGAN KERJA  (inputs_layout: environment)
 *     Single result column. Reuses the same pattern as AMSC / Radar.
 */
class CnsdVccsMeterTemplate
{
    public static function sections(): array
    {
        return [
            // ───────────────────────────────────────────────────────────
            // SECTION 1 — FRONT PANEL
            // ───────────────────────────────────────────────────────────
            [
                'code'            => '1',
                'name'            => 'FRONT PANEL',
                'inputs_layout'   => 'dual_adaptive',
                'columns_label_1' => 'Server A',
                'columns_label_2' => 'Server B',
                'groups' => [
                    [
                        'number' => 1,
                        'name'   => 'FRONT PANEL',
                        'items'  => [
                            ['item_number' => '1', 'item_name' => 'All Status Indikator', 'nominal' => 'Normal / Alrm'],
                            ['item_number' => '2', 'item_name' => 'Main Server',          'nominal' => '√ / -'],
                            ['item_number' => '3', 'item_name' => 'Standby server',       'nominal' => '√ / -'],
                            ['item_number' => '4', 'item_name' => 'AC/DC-48V Power',      'nominal' => '48 V'],
                        ],
                    ],
                ],
            ],

            // ───────────────────────────────────────────────────────────
            // SECTION 2 — MSC & RCMS
            // ───────────────────────────────────────────────────────────
            [
                'code'            => '2',
                'name'            => 'MSC & RCMS',
                'inputs_layout'   => 'dual_toggle_nf',
                'columns_label_1' => 'Normal',
                'columns_label_2' => 'Fault',
                'groups' => [
                    [
                        'number' => 1,
                        'name'   => 'Frame 1',
                        'items'  => [
                            ['item_number' => null, 'item_name' => 'MPU 1', 'nominal' => 'Content'],
                            ['item_number' => null, 'item_name' => 'MPU 2', 'nominal' => 'Content'],
                            ['item_number' => null, 'item_name' => 'EXP1',  'nominal' => 'Content'],
                            ['item_number' => null, 'item_name' => 'EXP2',  'nominal' => 'Content'],
                        ],
                    ],
                    [
                        'number' => 2,
                        'name'   => 'Frame 2',
                        'items'  => [
                            ['item_number' => null, 'item_name' => 'ALT 1', 'nominal' => 'Content'],
                            ['item_number' => null, 'item_name' => 'ALT 2', 'nominal' => 'Content'],
                            ['item_number' => null, 'item_name' => 'ALT 3', 'nominal' => 'Content'],
                            ['item_number' => null, 'item_name' => 'ASL',   'nominal' => 'Content'],
                            ['item_number' => null, 'item_name' => 'BSS 1', 'nominal' => 'Content'],
                            ['item_number' => null, 'item_name' => 'BSS 2', 'nominal' => 'Content'],
                            ['item_number' => null, 'item_name' => 'DRV 1', 'nominal' => 'Content'],
                            ['item_number' => null, 'item_name' => 'DRV 2', 'nominal' => 'Content'],
                            ['item_number' => null, 'item_name' => 'RIU 1', 'nominal' => 'Content'],
                            ['item_number' => null, 'item_name' => 'RIU 2', 'nominal' => 'Content'],
                        ],
                    ],
                ],
            ],

            // ───────────────────────────────────────────────────────────
            // SECTION 3 — CWP
            // ───────────────────────────────────────────────────────────
            [
                'code'            => '3',
                'name'            => 'CWP',
                'inputs_layout'   => 'dual_toggle_nf',
                'columns_label_1' => 'Normal',
                'columns_label_2' => 'Fault',
                'groups' => [
                    [
                        'number' => 1,
                        'name'   => 'CWP',
                        'items'  => array_map(static function ($n) {
                            return [
                                'item_number' => null,
                                'item_name'   => 'CWP ' . $n,
                                'nominal'     => 'Name',
                            ];
                        }, range(1, 12)),
                    ],
                ],
            ],

            // ───────────────────────────────────────────────────────────
            // SECTION 4 — LINGKUNGAN KERJA
            // ───────────────────────────────────────────────────────────
            [
                'code'            => '4',
                'name'            => 'LINGKUNGAN KERJA',
                'inputs_layout'   => 'environment',
                'columns_label_1' => 'HASIL PEMERIKSAAN',
                'columns_label_2' => null,
                'groups' => [
                    [
                        'number' => 1,
                        'name'   => 'LINGKUNGAN KERJA',
                        'items'  => [
                            ['item_number' => '1', 'item_name' => 'PEMERIKSAAN SUHU RUANGAN',       'nominal' => '< 22°C'],
                            ['item_number' => '2', 'item_name' => 'PEMERIKSAAN AIR HUMIDITY',       'nominal' => '√'],
                            ['item_number' => '3', 'item_name' => 'PEMERIKSAAN KEBERSIHAN RUANGAN', 'nominal' => '√'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function buildItemRows(int $recordId): array
    {
        $rows = [];
        $sortOrder = 0;
        $now = now();

        foreach (self::sections() as $section) {
            foreach ($section['groups'] as $group) {
                foreach ($group['items'] as $item) {
                    $rows[] = [
                        'vccs_meter_record_id' => $recordId,
                        'section_code'         => $section['code'],
                        'section_name'         => $section['name'],
                        'group_number'         => $group['number'] ?? null,
                        'group_name'           => $group['name']   ?? null,
                        'item_number'          => $item['item_number'] ?? null,
                        'item_name'            => $item['item_name'],
                        'nominal'              => $item['nominal']    ?? null,
                        'hasil_a'              => null,
                        'hasil_b'              => null,
                        'hasil'                => null,
                        'keterangan'           => $item['keterangan'] ?? null,
                        'is_blocked'           => false,
                        'block_reason'         => null,
                        'sort_order'           => $sortOrder++,
                        'created_at'           => $now,
                        'updated_at'           => $now,
                    ];
                }
            }
        }

        return $rows;
    }

    public static function sectionMeta(): array
    {
        return array_map(static function ($section) {
            return [
                'code'            => $section['code'],
                'name'            => $section['name'],
                'inputs_layout'   => $section['inputs_layout'] ?? 'dual_toggle_nf',
                'columns_label_1' => $section['columns_label_1'] ?? null,
                'columns_label_2' => $section['columns_label_2'] ?? null,
                'groups'          => array_map(static function ($g) {
                    return [
                        'number' => $g['number'] ?? null,
                        'name'   => $g['name']   ?? null,
                    ];
                }, $section['groups']),
            ];
        }, self::sections());
    }
}
