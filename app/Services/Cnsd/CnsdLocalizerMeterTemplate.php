<?php

namespace App\Services\Cnsd;

/**
 * CnsdLocalizerMeterTemplate — canonical Localizer Meter Reading item list,
 * mirrored from the official "METER READING — ILS LOCALIZER" paper form
 * used by AirNav Surabaya.
 *
 * Form structure (per the reference image):
 *
 *   Section A — PERALATAN
 *     Groups:
 *       1. FRONT PANEL        — dual TX1/TX2 columns
 *       2. CL (course Line)   — dual M1/M2 columns (DDM, SDM, Level, IDENT)
 *       3. DS (Displacement Sensitivity) — dual M1/M2 (DDM, SDM, Level, IDENT)
 *       4. CLR (Clearance)    — dual M1/M2 (DDM, SDM, Level, IDENT)
 *       5. Near Field         — dual M1/M2 (DDM, SDM, Level, IDENT)
 *       6. Power Supply DC/DC-A — single HASIL (5V, 15V, -15V)
 *       7. Power Supply DC/DC   — single HASIL (5V, 8V, 15V, -15V, 28V)
 *       8. Power Supply Baterai — single HASIL
 *
 *   Section B — LINGKUNGAN KERJA
 *     Items: Suhu Ruangan, Air Humidity, Kebersihan Ruangan
 */
class CnsdLocalizerMeterTemplate
{
    public static function sections(): array
    {
        return [
            [
                'code'          => 'A',
                'name'          => 'PERALATAN',
                'inputs_layout' => 'meter_reading',
                'groups'        => self::meterReadingGroups(),
            ],
            [
                'code'          => 'B',
                'name'          => 'LINGKUNGAN KERJA',
                'inputs_layout' => 'environment',
                'groups'        => [
                    [
                        'number' => 9,
                        'name'   => 'LINGKUNGAN KERJA',
                        'items'  => [
                            ['item_name' => 'Pemeriksaan Suhu Ruangan',       'nominal' => '< 22° C', 'hasil_layout' => 'single'],
                            ['item_name' => 'Pemeriksaan Air Humidity',       'nominal' => '√',       'hasil_layout' => 'single'],
                            ['item_name' => 'Pemeriksaan Kebersihan Ruangan', 'nominal' => '√',       'hasil_layout' => 'single'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function meterReadingGroups(): array
    {
        return [
            // ─── 1. FRONT PANEL ────────────────────────────────
            // Dual TX1/TX2 columns (same as Glide Path)
            [
                'number' => 1,
                'name'   => 'FRONT PANEL',
                'items'  => [
                    ['item_name' => 'All Status Indikator', 'nominal' => 'Normal / Alrm', 'hasil_layout' => 'dual'],
                    ['item_name' => 'Main Selected',        'nominal' => '√ / -',         'hasil_layout' => 'dual'],
                    ['item_name' => 'TX On Air',            'nominal' => '√ / -',         'hasil_layout' => 'dual'],
                    ['item_name' => 'TX Stby',              'nominal' => '√ / -',         'hasil_layout' => 'dual'],
                    ['item_name' => 'Control Status',       'nominal' => 'local / Remote','hasil_layout' => 'dual'],
                    ['item_name' => 'DC Power Suplay',      'nominal' => '28 V',          'hasil_layout' => 'dual'],
                ],
            ],

            // ─── 2. CL (course Line) ───────────────────────────
            // Dual M1/M2 — Localizer: SDM=40%, IDENT added
            [
                'number' => 2,
                'name'   => 'CL (course Line)',
                'items'  => [
                    ['item_name' => 'DDM',   'nominal' => '0.00%',  'hasil_layout' => 'dual'],
                    ['item_name' => 'SDM',   'nominal' => '40%',    'hasil_layout' => 'dual'],
                    ['item_name' => 'Level', 'nominal' => '0.00dB', 'hasil_layout' => 'dual'],
                    ['item_name' => 'IDENT', 'nominal' => '8.00%',  'hasil_layout' => 'dual'],
                ],
            ],

            // ─── 3. DS (Displacement Sensitivity) ─────────────
            // Localizer: DDM=15.5%, SDM=40%
            [
                'number' => 3,
                'name'   => 'DS (Displacement Sensitivity)',
                'items'  => [
                    ['item_name' => 'DDM',   'nominal' => '15.5%',  'hasil_layout' => 'dual'],
                    ['item_name' => 'SDM',   'nominal' => '40%',    'hasil_layout' => 'dual'],
                    ['item_name' => 'Level', 'nominal' => '0.00dB', 'hasil_layout' => 'dual'],
                    ['item_name' => 'IDENT', 'nominal' => '8.00%',  'hasil_layout' => 'dual'],
                ],
            ],

            // ─── 4. CLR (Clearance) ────────────────────────────
            // Localizer: DDM=22.28%, SDM=40%
            [
                'number' => 4,
                'name'   => 'CLR (Clearance)',
                'items'  => [
                    ['item_name' => 'DDM',   'nominal' => '22.28%', 'hasil_layout' => 'dual'],
                    ['item_name' => 'SDM',   'nominal' => '40%',    'hasil_layout' => 'dual'],
                    ['item_name' => 'Level', 'nominal' => '0.00dB', 'hasil_layout' => 'dual'],
                    ['item_name' => 'IDENT', 'nominal' => '8.00%',  'hasil_layout' => 'dual'],
                ],
            ],

            // ─── 5. Near Field ─────────────────────────────────
            // Localizer: SDM=40.00, IDENT=8.00
            [
                'number' => 5,
                'name'   => 'Near Field',
                'items'  => [
                    ['item_name' => 'DDM',   'nominal' => '0.00',   'hasil_layout' => 'dual'],
                    ['item_name' => 'SDM',   'nominal' => '40.00',  'hasil_layout' => 'dual'],
                    ['item_name' => 'Level', 'nominal' => '0.00',   'hasil_layout' => 'dual'],
                    ['item_name' => 'IDENT', 'nominal' => '8.00',   'hasil_layout' => 'dual'],
                ],
            ],

            // ─── 6. Power Supply DC/DC-A ───────────────────────
            [
                'number' => 6,
                'name'   => 'Power Supply',
                'items'  => [
                    ['item_name' => 'DC/DC-A', 'nominal' => null,   'hasil_layout' => 'single'],
                    ['item_name' => '5V',      'nominal' => '5V',   'hasil_layout' => 'single'],
                    ['item_name' => '15V',     'nominal' => '15V',  'hasil_layout' => 'single'],
                    ['item_name' => '-15V',    'nominal' => '-15V', 'hasil_layout' => 'single'],
                ],
            ],

            // ─── 7. Power Supply DC/DC ─────────────────────────
            [
                'number' => 7,
                'name'   => 'Power Supply',
                'items'  => [
                    ['item_name' => 'DC/DC',  'nominal' => null,   'hasil_layout' => 'single'],
                    ['item_name' => '5V',     'nominal' => '5V',   'hasil_layout' => 'single'],
                    ['item_name' => '8V',     'nominal' => '8V',   'hasil_layout' => 'single'],
                    ['item_name' => '15V',    'nominal' => '15V',  'hasil_layout' => 'single'],
                    ['item_name' => '-15V',   'nominal' => '-15V', 'hasil_layout' => 'single'],
                    ['item_name' => '28V',    'nominal' => '28V',  'hasil_layout' => 'single'],
                ],
            ],

            // ─── 8. Power Supply Baterai ───────────────────────
            [
                'number' => 8,
                'name'   => 'Power Supply',
                'items'  => [
                    ['item_name' => 'Baterai', 'nominal' => null, 'hasil_layout' => 'single'],
                ],
            ],
        ];
    }

    public static function buildItemRows(int $recordId): array
    {
        $rows      = [];
        $sortOrder = 0;
        $now       = now();

        foreach (self::sections() as $section) {
            foreach ($section['groups'] as $group) {
                if ($section['code'] === 'A') {
                    $rows[] = [
                        'localizer_meter_record_id' => $recordId,
                        'section_code'  => $section['code'],
                        'section_name'  => $section['name'],
                        'group_number'  => $group['number'],
                        'group_name'    => $group['name'],
                        'item_name'     => null,
                        'nominal'       => null,
                        'hasil_layout'  => 'single',
                        'hasil_1'       => null,
                        'hasil_2'       => null,
                        'keterangan'    => null,
                        'is_header'     => true,
                        'sort_order'    => $sortOrder++,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ];
                }

                foreach ($group['items'] as $item) {
                    $rows[] = [
                        'localizer_meter_record_id' => $recordId,
                        'section_code'  => $section['code'],
                        'section_name'  => $section['name'],
                        'group_number'  => $group['number'],
                        'group_name'    => $group['name'],
                        'item_name'     => $item['item_name'],
                        'nominal'       => $item['nominal'] ?? null,
                        'hasil_layout'  => $item['hasil_layout'] ?? 'single',
                        'hasil_1'       => null,
                        'hasil_2'       => null,
                        'keterangan'    => null,
                        'is_header'     => false,
                        'sort_order'    => $sortOrder++,
                        'created_at'    => $now,
                        'updated_at'    => $now,
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
                'code'          => $section['code'],
                'name'          => $section['name'],
                'inputs_layout' => $section['inputs_layout'] ?? 'meter_reading',
                'groups'        => array_map(static function ($g) {
                    return ['number' => $g['number'] ?? null, 'name' => $g['name'] ?? null];
                }, $section['groups']),
            ];
        }, self::sections());
    }
}
