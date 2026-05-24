<?php

namespace App\Services\Cnsd;

/**
 * CnsdAsmgcsMeterTemplate — canonical ASMGCS (SAAB) Meter Reading items,
 * mirrored from the official "METER READING — ASMGCS" paper form.
 *
 * Structure (same UI conventions as VCCS Freq / CNSD-015 minus CWP):
 *
 *   Section 1 — FRONT PANEL  (inputs_layout: single_adaptive)
 *     ONE result column ("Redundant Server") that adapts per nominal.
 *
 *   Section 2 — CENTRAL SERVER  (inputs_layout: dual_toggle_nf)
 *     Two √/- toggle columns (Normal | Fault). Items grouped into 6 sub-areas
 *     per paper: CENTRAL SERVER / OPS ANALYSER / DP MAINTENANCE /
 *     DP CONTROL TOWER / DP APPROACH / NETWORK EQUIPMENT.
 *
 *   Section 4 — LINGKUNGAN KERJA  (inputs_layout: environment)
 *     (Code '4' kept to match VCCS Freq print partitioning where the env
 *     section is treated separately under "B. LINGKUNGAN KERJA".)
 */
class CnsdAsmgcsMeterTemplate
{
    public static function sections(): array
    {
        return [
            // ───────────────────────────────────────────────────────────
            // SECTION 1 — FRONT PANEL  (single adaptive column)
            // ───────────────────────────────────────────────────────────
            [
                'code'            => '1',
                'name'            => 'FRONT PANEL',
                'inputs_layout'   => 'single_adaptive',
                'columns_label_1' => 'Redundant Server',
                'columns_label_2' => null,
                'groups' => [
                    [
                        'number' => 1,
                        'name'   => 'FRONT PANEL',
                        'items'  => [
                            ['item_number' => '1', 'item_name' => 'All Status Indikator', 'nominal' => 'Normal / Alrm'],
                            ['item_number' => '2', 'item_name' => 'Server',               'nominal' => '√ / -'],
                        ],
                    ],
                ],
            ],

            // ───────────────────────────────────────────────────────────
            // SECTION 2 — CENTRAL SERVER (6 sub-groups, all "Hijau" nominal)
            // ───────────────────────────────────────────────────────────
            [
                'code'            => '2',
                'name'            => 'CENTRAL SERVER',
                'inputs_layout'   => 'dual_toggle_nf',
                'columns_label_1' => 'Normal',
                'columns_label_2' => 'Fault',
                'groups' => [
                    [
                        'number' => 1,
                        'name'   => 'CENTRAL SERVER',
                        'items'  => [
                            ['item_name' => 'CSP 01', 'nominal' => 'Hijau'],
                            ['item_name' => 'CSP 02', 'nominal' => 'Hijau'],
                            ['item_name' => 'COP 01', 'nominal' => 'Hijau'],
                            ['item_name' => 'COP 02', 'nominal' => 'Hijau'],
                        ],
                    ],
                    [
                        'number' => 2,
                        'name'   => 'OPS ANALYSER',
                        'items'  => [
                            ['item_name' => 'WSP 01', 'nominal' => 'Hijau'],
                            ['item_name' => 'ANS 01', 'nominal' => 'Hijau'],
                        ],
                    ],
                    [
                        'number' => 3,
                        'name'   => 'DP MAINTENANCE',
                        'items'  => [
                            ['item_name' => 'DPM 01 MER', 'nominal' => 'Hijau'],
                        ],
                    ],
                    [
                        'number' => 4,
                        'name'   => 'DP CONTROL TOWER',
                        'items'  => [
                            ['item_name' => 'DP 01 Tower',     'nominal' => 'Hijau'],
                            ['item_name' => 'DP 02 Ground',    'nominal' => 'Hijau'],
                            ['item_name' => 'DP 03 Sup Tower', 'nominal' => 'Hijau'],
                        ],
                    ],
                    [
                        'number' => 5,
                        'name'   => 'DP APPROACH',
                        'items'  => [
                            ['item_name' => 'DP 04 Director', 'nominal' => 'Hijau'],
                        ],
                    ],
                    [
                        'number' => 6,
                        'name'   => 'NETWORK EQUIPMENT',
                        'items'  => [
                            ['item_name' => 'SW 01', 'nominal' => 'Hijau'],
                            ['item_name' => 'SW 02', 'nominal' => 'Hijau'],
                            ['item_name' => 'SW 03', 'nominal' => 'Hijau'],
                            ['item_name' => 'SW 04', 'nominal' => 'Hijau'],
                            ['item_name' => 'FW 01', 'nominal' => 'Hijau'],
                        ],
                    ],
                ],
            ],

            // ───────────────────────────────────────────────────────────
            // SECTION 4 — LINGKUNGAN KERJA  (code 4 reserved like VCCS Freq)
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
                        'asmgcs_meter_record_id' => $recordId,
                        'section_code'           => $section['code'],
                        'section_name'           => $section['name'],
                        'group_number'           => $group['number'] ?? null,
                        'group_name'             => $group['name']   ?? null,
                        'item_number'            => $item['item_number'] ?? null,
                        'item_name'              => $item['item_name'],
                        'nominal'                => $item['nominal']    ?? null,
                        'hasil_a'                => null,
                        'hasil_b'                => null,
                        'hasil'                  => null,
                        'keterangan'             => $item['keterangan'] ?? null,
                        'is_blocked'             => false,
                        'block_reason'           => null,
                        'sort_order'             => $sortOrder++,
                        'created_at'             => $now,
                        'updated_at'             => $now,
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
