<?php

namespace App\Services\Cnsd;

/**
 * CnsdTdmeMeterTemplate — canonical T-DME Meter Reading item list,
 * mirrored from the official "METER READING — T-DME" paper form (FORM N-5)
 * used by AirNav Surabaya.
 *
 * Form structure (per the reference image):
 *
 *   Section A — PERALATAN
 *     Groups:
 *       1. FRONT PANEL          — dual TX1/TX2 columns
 *       2. PARAMETER            — dual M1/M2 columns
 *       3. Pulse Shape and Spacing — dual M1/M2
 *       4. Frequency and Power  — dual M1/M2
 *       5. POWER SUPPLY DC/DC-A — single HASIL (5V, 15V, -15V)
 *       6. POWER SUPPLY DC/DC   — single HASIL (5V, 8V, 15V, -15V, 28V, 50V)
 *       7. POWER SUPPLY Baterai — single HASIL
 *
 *   Section B — LINGKUNGAN KERJA
 *     Items: Suhu Ruangan, Air Humidity, Kebersihan Ruangan
 */
class CnsdTdmeMeterTemplate
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
                        'number' => 8,
                        'name'   => 'LINGKUNGAN KERJA',
                        'items'  => [
                            ['item_name' => 'Pemeriksaan Suhu Ruangan',       'nominal' => 'Max 22° C', 'hasil_layout' => 'single'],
                            ['item_name' => 'Pemeriksaan Air Humidity',       'nominal' => '√',         'hasil_layout' => 'single'],
                            ['item_name' => 'Pemeriksaan Kebersihan Ruangan', 'nominal' => '√',         'hasil_layout' => 'single'],
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
            // Dual TX1/TX2 columns
            [
                'number' => 1,
                'name'   => 'FRONT PANEL',
                'items'  => [
                    ['item_name' => 'All Status Indikator', 'nominal' => 'Normal / Alrm', 'hasil_layout' => 'dual'],
                    ['item_name' => 'Main Selected',        'nominal' => '√ / -',         'hasil_layout' => 'dual'],
                    ['item_name' => 'TX On AIR',            'nominal' => '√ / -',         'hasil_layout' => 'dual'],
                    ['item_name' => 'TX Stby',              'nominal' => '√ / -',         'hasil_layout' => 'dual'],
                    ['item_name' => 'Control Status',       'nominal' => 'local / Remote','hasil_layout' => 'dual'],
                ],
            ],

            // ─── 2. PARAMETER ──────────────────────────────────
            // Dual M1/M2 columns
            [
                'number' => 2,
                'name'   => 'PARAMETER',
                'items'  => [
                    ['item_name' => 'Time Delay',        'nominal' => '50.00',  'hasil_layout' => 'dual'],
                    ['item_name' => 'Reply Efficiency',  'nominal' => '80.00',  'hasil_layout' => 'dual'],
                    ['item_name' => 'Transmission Rate', 'nominal' => '2875',   'hasil_layout' => 'dual'],
                ],
            ],

            // ─── 3. Pulse Shape and Spacing ────────────────────
            [
                'number' => 3,
                'name'   => 'Pulse Shape and Spacing',
                'items'  => [
                    ['item_name' => 'Pulse Rise Time',  'nominal' => '2.50',  'hasil_layout' => 'dual'],
                    ['item_name' => 'Pulse Duration',   'nominal' => '3.50',  'hasil_layout' => 'dual'],
                    ['item_name' => 'Pulse Decay Time', 'nominal' => '2.50',  'hasil_layout' => 'dual'],
                    ['item_name' => 'Pulse Spacing',    'nominal' => '12.00', 'hasil_layout' => 'dual'],
                ],
            ],

            // ─── 4. Frequency and Power ────────────────────────
            [
                'number' => 4,
                'name'   => 'Frequency and Power',
                'items'  => [
                    ['item_name' => 'Radio Frequency',    'nominal' => '0.00',   'hasil_layout' => 'dual'],
                    ['item_name' => 'Peak Power Output',  'nominal' => '100.00', 'hasil_layout' => 'dual'],
                    ['item_name' => 'Antenna VSWR',       'nominal' => '1.00',   'hasil_layout' => 'dual'],
                    ['item_name' => 'Effective Radiated', 'nominal' => '0.0',    'hasil_layout' => 'dual'],
                ],
            ],

            // ─── 5. POWER SUPPLY DC/DC-A ───────────────────────
            [
                'number' => 5,
                'name'   => 'POWER SUPPLY',
                'items'  => [
                    ['item_name' => 'DC/DC-A', 'nominal' => null,   'hasil_layout' => 'single'],
                    ['item_name' => '5 V',     'nominal' => '5 V',  'hasil_layout' => 'single'],
                    ['item_name' => '15V',     'nominal' => '15V',  'hasil_layout' => 'single'],
                    ['item_name' => '-15V',    'nominal' => '-15V', 'hasil_layout' => 'single'],
                ],
            ],

            // ─── 6. POWER SUPPLY DC/DC ─────────────────────────
            [
                'number' => 6,
                'name'   => 'POWER SUPPLY',
                'items'  => [
                    ['item_name' => 'DC/DC',  'nominal' => null,   'hasil_layout' => 'single'],
                    ['item_name' => '5V',     'nominal' => '5V',   'hasil_layout' => 'single'],
                    ['item_name' => '8V',     'nominal' => '8V',   'hasil_layout' => 'single'],
                    ['item_name' => '15V',    'nominal' => '15V',  'hasil_layout' => 'single'],
                    ['item_name' => '-15V',   'nominal' => '-15V', 'hasil_layout' => 'single'],
                    ['item_name' => '28V',    'nominal' => '28V',  'hasil_layout' => 'single'],
                    ['item_name' => '50V',    'nominal' => '50V',  'hasil_layout' => 'single'],
                ],
            ],

            // ─── 7. POWER SUPPLY Baterai ───────────────────────
            [
                'number' => 7,
                'name'   => 'POWER SUPPLY',
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
                        'tdme_meter_record_id' => $recordId,
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
                        'tdme_meter_record_id' => $recordId,
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
