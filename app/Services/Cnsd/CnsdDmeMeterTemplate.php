<?php

namespace App\Services\Cnsd;

/**
 * CnsdDmeMeterTemplate — canonical DME Meter Reading item list,
 * mirrored from the official "METER READING — DME" paper form (FORM N-5)
 * used by AirNav Surabaya. Sourced from `011_DME.jpg`.
 *
 *   Section A — PERALATAN
 *     1. FRONT PANEL — dual TX1/TX2 columns
 *     2. PARAMETER — dual M1/M2 (SPACING, DELAY, POWER OUTPUT, EFFICIENCY,
 *                    TX. PULSE RATE, PULSE WIDTH, PULSE RISE TIME, PULSE FALL TIME)
 *     3. POWER SUPPLY — dual TX1/TX2 (DC CURRENT, DC VOLTAGE)
 *     4. BATTERY — dual TX1/TX2 (DC VOLTAGE)
 *
 *   Section B — LINGKUNGAN KERJA
 *     Items: Suhu Ruangan, Air Humidity, Kebersihan Ruangan
 */
class CnsdDmeMeterTemplate
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
                        'number' => 5,
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
            [
                'number' => 2,
                'name'   => 'PARAMETER',
                'items'  => [
                    ['item_name' => 'SPACING',         'nominal' => '12 ± 0.1 μs', 'hasil_layout' => 'dual'],
                    ['item_name' => 'DELAY',           'nominal' => '50 ± 0.5 μs', 'hasil_layout' => 'dual'],
                    ['item_name' => 'POWER OUTPUT',    'nominal' => '± 1 KW',      'hasil_layout' => 'dual'],
                    ['item_name' => 'EFFICIENCY',      'nominal' => '70 % - 100 %','hasil_layout' => 'dual'],
                    ['item_name' => 'TX. PULSE RATE',  'nominal' => '945 Hz',      'hasil_layout' => 'dual'],
                    ['item_name' => 'PULSE WIDTH',     'nominal' => '3.5 ± 0.5 μs','hasil_layout' => 'dual'],
                    ['item_name' => 'PULSE RISE TIME', 'nominal' => '2.5 ± 0.5 μs','hasil_layout' => 'dual'],
                    ['item_name' => 'PULSE FALL TIME', 'nominal' => '2.5 ± 0.5 μs','hasil_layout' => 'dual'],
                ],
            ],

            // ─── 3. POWER SUPPLY ───────────────────────────────
            [
                'number' => 3,
                'name'   => 'POWER SUPPLY',
                'items'  => [
                    ['item_name' => 'DC CURRENT', 'nominal' => '3.5 A',     'hasil_layout' => 'dual'],
                    ['item_name' => 'DC VOLTAGE', 'nominal' => '26 - 27 Vdc','hasil_layout' => 'dual'],
                ],
            ],

            // ─── 4. BATTERY ────────────────────────────────────
            [
                'number' => 4,
                'name'   => 'BATTERY',
                'items'  => [
                    ['item_name' => 'DC VOLTAGE', 'nominal' => '26 - 27 Vdc', 'hasil_layout' => 'dual'],
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
                        'dme_meter_record_id' => $recordId,
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
                        'dme_meter_record_id' => $recordId,
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
