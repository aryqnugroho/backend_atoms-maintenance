<?php

namespace App\Services\Cnsd;

/**
 * CnsdVccsFreqMeterTemplate — canonical VCCS Frequentis Meter Reading items,
 * mirrored from the official "METER READING — VCCS" paper form (Frequentis brand).
 *
 * Structure (same UI conventions as VCCS LES / CNSD-014, content per Freq paper):
 *
 *   Section 1 — FRONT PANEL  (inputs_layout: single_adaptive)
 *     ONE result column ("Redundant Server") that adapts per nominal:
 *       - "Normal / Alrm" → toggle NORMAL / ALARM
 *       - "√ / −"         → toggle √ / -
 *       - "220 V"         → free-text/number input
 *
 *   Section 2 — MSC & RCMS  (inputs_layout: dual_toggle_nf)
 *     Two √/- toggle columns (Normal | Fault). Items grouped Frame 1..4.
 *
 *   Section 3 — CWP  (inputs_layout: dual_toggle_nf)
 *     Same dual Normal/Fault toggles. item_name = position (TOWER CONTROL etc.),
 *     nominal = CWP slot label (CWP 1..12).
 *
 *   Section 4 — LINGKUNGAN KERJA  (inputs_layout: environment)
 *     Single result column.
 */
class CnsdVccsFreqMeterTemplate
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
                            ['item_number' => '3', 'item_name' => 'AC Power',             'nominal' => '220 V'],
                        ],
                    ],
                ],
            ],

            // ───────────────────────────────────────────────────────────
            // SECTION 2 — MSC & RCMS
            // 4 frames: GATE X2 / GPIF / ERIF / BCA+BCB
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
                            ['item_name' => 'GATE X2-01', 'nominal' => 'LED Hijau'],
                            ['item_name' => 'GATE X2-02', 'nominal' => 'LED Hijau'],
                            ['item_name' => 'GATE X2-03', 'nominal' => 'LED Hijau'],
                            ['item_name' => 'GATE X2-04', 'nominal' => 'LED Hijau'],
                        ],
                    ],
                    [
                        'number' => 2,
                        'name'   => 'Frame 2',
                        'items'  => [
                            ['item_name' => 'GPIF-1', 'nominal' => 'LED Hijau'],
                            ['item_name' => 'GPIF-2', 'nominal' => 'LED Hijau'],
                            ['item_name' => 'GPIF-3', 'nominal' => 'LED Hijau'],
                            ['item_name' => 'GPIF-4', 'nominal' => 'LED Hijau'],
                        ],
                    ],
                    [
                        'number' => 3,
                        'name'   => 'Frame 3',
                        'items'  => array_map(static function ($n) {
                            return ['item_name' => 'ERIF-' . $n, 'nominal' => 'LED Hijau'];
                        }, range(1, 10)),
                    ],
                    [
                        'number' => 4,
                        'name'   => 'Frame 4',
                        'items'  => array_merge(
                            array_map(static fn ($n) => ['item_name' => 'BCA-' . $n, 'nominal' => 'LED Hijau'], range(1, 5)),
                            array_map(static fn ($n) => ['item_name' => 'BCB-' . $n, 'nominal' => 'LED Hijau'], range(1, 9)),
                        ),
                    ],
                ],
            ],

            // ───────────────────────────────────────────────────────────
            // SECTION 3 — CWP
            // item_name = position; nominal = CWP slot identifier
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
                        'items'  => [
                            ['item_name' => 'TOWER CONTROL',        'nominal' => 'CWP 1'],
                            ['item_name' => 'TOWER ASSISTANT',      'nominal' => 'CWP 2'],
                            ['item_name' => 'GROUND CONTROL',       'nominal' => 'CWP 3'],
                            ['item_name' => 'EAST ASSISTANT',       'nominal' => 'CWP 4'],
                            ['item_name' => 'EAST CONTROL',         'nominal' => 'CWP 5'],
                            ['item_name' => 'WEST CONTROL',         'nominal' => 'CWP 6'],
                            ['item_name' => 'WEST ASSISTANT',       'nominal' => 'CWP 7'],
                            ['item_name' => 'DIRRECTOR CONTROL',    'nominal' => 'CWP 8'],
                            ['item_name' => 'DIRRECTOR ASSISTANT',  'nominal' => 'CWP 9'],
                            ['item_name' => 'SUPERVISOR APP',       'nominal' => 'CWP 10'],
                            ['item_name' => 'CDU',                  'nominal' => 'CWP 11'],
                            ['item_name' => 'TEKNIK',               'nominal' => 'CWP 12'],
                        ],
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
                        'vccs_freq_meter_record_id' => $recordId,
                        'section_code'              => $section['code'],
                        'section_name'              => $section['name'],
                        'group_number'              => $group['number'] ?? null,
                        'group_name'                => $group['name']   ?? null,
                        'item_number'               => $item['item_number'] ?? null,
                        'item_name'                 => $item['item_name'],
                        'nominal'                   => $item['nominal']    ?? null,
                        'hasil_a'                   => null,
                        'hasil_b'                   => null,
                        'hasil'                     => null,
                        'keterangan'                => $item['keterangan'] ?? null,
                        'is_blocked'                => false,
                        'block_reason'              => null,
                        'sort_order'                => $sortOrder++,
                        'created_at'                => $now,
                        'updated_at'                => $now,
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
