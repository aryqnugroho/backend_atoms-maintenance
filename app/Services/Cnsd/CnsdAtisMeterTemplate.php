<?php

namespace App\Services\Cnsd;

/**
 * CnsdAtisMeterTemplate — canonical ATIS Meter Reading item list, mirrored
 * from the official "METER READING — REPRODUCER ATIS" paper form, sourced
 * from `013_ATIS.jpg`.
 *
 * Single READING column layout (no TX1/TX2 split).
 *
 *   Section A — TERMA RCM
 *     1. REMOTE CONTROL AND MONITORING (System Status, Main Server, ATIS_A/B Status)
 *
 *   Section B — TERMA ATIS PLUS SYSTEM
 *     1. ATIS (System Time, Last Update, Runway, QNH, Info, ATIS_A/B Mode)
 *     2. ADMIN (Speech Rate, Atis Time Out, Update Mode, Speech Type, Voice Update)
 */
class CnsdAtisMeterTemplate
{
    public static function sections(): array
    {
        return [
            [
                'code'          => 'A',
                'name'          => 'TERMA RCM',
                'inputs_layout' => 'single_reading',
                'groups'        => [
                    [
                        'number' => 1,
                        'name'   => 'REMOTE CONTROL AND MONITORING',
                        'items'  => [
                            ['item_name' => 'System Status',  'nominal' => 'Dual'],
                            ['item_name' => 'Main Server',    'nominal' => 'ATIS A / B'],
                            ['item_name' => 'ATIS_A Status',  'nominal' => 'OK / NO'],
                            ['item_name' => 'ATIS_B Status',  'nominal' => 'OK / NO'],
                        ],
                    ],
                ],
            ],
            [
                'code'          => 'B',
                'name'          => 'TERMA ATIS PLUS SYSTEM',
                'inputs_layout' => 'single_reading',
                'groups'        => [
                    [
                        'number' => 1,
                        'name'   => 'ATIS',
                        'items'  => [
                            ['item_name' => 'System Time',        'nominal' => 'Time'],
                            ['item_name' => 'Last Update System', 'nominal' => 'Time'],
                            ['item_name' => 'Runway In Use',      'nominal' => '10 / 28'],
                            ['item_name' => 'QNH',                'nominal' => 'Dual'],
                            ['item_name' => 'Info',               'nominal' => 'Freq Tower'],
                            ['item_name' => 'ATIS_A',             'nominal' => 'Main / Stby'],
                            ['item_name' => 'ATIS_B',             'nominal' => 'Main / Stby'],
                        ],
                    ],
                    [
                        'number' => 2,
                        'name'   => 'ADMIN',
                        'items'  => [
                            ['item_name' => 'Speech Rate',   'nominal' => '100 word / minute'],
                            ['item_name' => 'Atis Time Out', 'nominal' => 'Minutes'],
                            ['item_name' => 'Update Mode',   'nominal' => 'Automatic / Manual'],
                            ['item_name' => 'Speech Type',   'nominal' => null],
                            ['item_name' => 'Voice Update',  'nominal' => null],
                        ],
                    ],
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
                foreach ($group['items'] as $item) {
                    $rows[] = [
                        'atis_meter_record_id' => $recordId,
                        'section_code' => $section['code'],
                        'section_name' => $section['name'],
                        'group_number' => $group['number'] ?? null,
                        'group_name'   => $group['name']   ?? null,
                        'item_name'    => $item['item_name'],
                        'nominal'      => $item['nominal'] ?? null,
                        'reading'      => null,
                        'keterangan'   => null,
                        'is_header'    => false,
                        'sort_order'   => $sortOrder++,
                        'created_at'   => $now,
                        'updated_at'   => $now,
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
                'inputs_layout' => $section['inputs_layout'] ?? 'single_reading',
                'groups'        => array_map(static function ($g) {
                    return ['number' => $g['number'] ?? null, 'name' => $g['name'] ?? null];
                }, $section['groups']),
            ];
        }, self::sections());
    }
}
