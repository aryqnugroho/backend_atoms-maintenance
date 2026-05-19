<?php

namespace App\Services\Cnsd;

/**
 * CnsdReceiverMeterTemplate — canonical Receiver Meter Reading item list,
 * mirrored from the official "METER READING — RECEIVER" paper form (FORM C-2)
 * used by AirNav Surabaya.
 *
 * Form structure (per the reference image):
 *
 *   Section I — RECEIVER
 *     Groups: ADC, GROUND CONTROL, CDU, APP WEST, APP DIRECTOR, APP EAST,
 *             ATIS, ER MAKASSAR, BACK UP
 *     Layout: NO | PEMERIKSAAN | STATUS A | STATUS B | SEQUELSH ON | KETERANGAN
 *     Status values: ON LINE / OFF LINE (dropdown)
 *
 *   Section II — LINGKUNGAN KERJA
 *     Items: Suhu Ruangan, Air Humidity, Kebersihan Ruangan, UPS
 *     Layout: NO | KEGIATAN | NOMINAL | HASIL | KETERANGAN
 */
class CnsdReceiverMeterTemplate
{
    /**
     * Full section + group + item structure for the Receiver Meter Reading form.
     */
    public static function sections(): array
    {
        return [
            // ───────────────────────────────────────────────────────────
            // SECTION I — RECEIVER
            // ───────────────────────────────────────────────────────────
            [
                'code'          => '1',
                'name'          => 'RECEIVER',
                'inputs_layout' => 'receiver',
                'groups'        => self::receiverGroups(),
            ],

            // ───────────────────────────────────────────────────────────
            // SECTION II — LINGKUNGAN KERJA
            // ───────────────────────────────────────────────────────────
            [
                'code'          => '2',
                'name'          => 'LINGKUNGAN KERJA',
                'inputs_layout' => 'environment',
                'groups'        => [
                    [
                        'number' => 10,
                        'name'   => 'LINGKUNGAN KERJA',
                        'items'  => [
                            ['item_name' => 'Pemeriksaan suhu Ruangan',       'nominal' => '<22°C'],
                            ['item_name' => 'Pemeriksaan Air Humidity',       'nominal' => '√'],
                            ['item_name' => 'Pemeriksaan Kebersihan Ruangan', 'nominal' => '√'],
                            ['item_name' => 'Pemeriksaan UPS',                'nominal' => '√'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Receiver groups based on the reference image.
     * Each frequency row has STATUS A and STATUS B columns (ON LINE / OFF LINE).
     */
    private static function receiverGroups(): array
    {
        return [
            // ─── 1. ADC ────────────────────────────────────────────
            [
                'number' => 1,
                'name'   => 'ADC',
                'items'  => [
                    ['item_name' => '118.30 MHz'],
                    ['item_name' => '118.30 MHz'],
                    ['item_name' => '118.10 MHz'],
                ],
            ],

            // ─── 2. GROUND CONTROL ─────────────────────────────────
            [
                'number' => 2,
                'name'   => 'GROUND CONTROL',
                'items'  => [
                    ['item_name' => '118.90 MHz'],
                    ['item_name' => '119.15 MHz'],
                ],
            ],

            // ─── 3. CONTROL DELIVERY UNIT (CDU) ────────────────────
            [
                'number' => 3,
                'name'   => 'CONTROL DELIVERY UNIT (CDU)',
                'items'  => [
                    ['item_name' => '121.65 MHz'],
                ],
            ],

            // ─── 3. APP WEST ───────────────────────────────────────
            // Note: numbered 3 in the form image (same as CDU section)
            [
                'number' => 4,
                'name'   => 'APP WEST',
                'items'  => [
                    ['item_name' => '125.10 MHz'],
                    ['item_name' => '123.55 MHz'],
                ],
            ],

            // ─── 4. APP DIRECTOR ───────────────────────────────────
            [
                'number' => 5,
                'name'   => 'APP DIRECTOR',
                'items'  => [
                    ['item_name' => '123.20 MHz'],
                    ['item_name' => '124.50 MHz'],
                ],
            ],

            // ─── 5. APP EAST ───────────────────────────────────────
            [
                'number' => 6,
                'name'   => 'APP EAST',
                'items'  => [
                    ['item_name' => '124.00 MHz'],
                    ['item_name' => '122.85 MHz'],
                ],
            ],

            // ─── 6. ATIS ───────────────────────────────────────────
            [
                'number' => 7,
                'name'   => 'ATIS',
                'items'  => [
                    ['item_name' => '128.20 MHz'],
                ],
            ],

            // ─── 7. ER MAKASSAR ────────────────────────────────────
            [
                'number' => 8,
                'name'   => 'ER MAKASSAR',
                'items'  => [
                    ['item_name' => '123.90 MHz'],
                    ['item_name' => '125.90 MHz'],
                ],
            ],

            // ─── 8. BACK UP ────────────────────────────────────────
            [
                'number' => 9,
                'name'   => 'BACK UP',
                'items'  => [
                    ['item_name' => '119.15 MHz'],
                    ['item_name' => '123.20 MHz'],
                    ['item_name' => '124.00 MHz'],
                    ['item_name' => '125.10 MHz'],
                ],
            ],
        ];
    }

    /**
     * Flatten the structured template into row inserts for cnsd_receiver_meter_items.
     */
    public static function buildItemRows(int $recordId): array
    {
        $rows      = [];
        $sortOrder = 0;
        $now       = now();

        foreach (self::sections() as $section) {
            foreach ($section['groups'] as $group) {
                // Add group header row for Section 1
                if ($section['code'] === '1') {
                    $rows[] = [
                        'receiver_meter_record_id' => $recordId,
                        'section_code'  => $section['code'],
                        'section_name'  => $section['name'],
                        'group_number'  => $group['number'],
                        'group_name'    => $group['name'],
                        'item_name'     => null,
                        'status_a'      => null,
                        'status_b'      => null,
                        'sequelsh_on'   => null,
                        'keterangan'    => null,
                        'nominal'       => null,
                        'hasil'         => null,
                        'is_header'     => true,
                        'sort_order'    => $sortOrder++,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ];
                }

                foreach ($group['items'] as $item) {
                    if ($section['code'] === '1') {
                        // Receiver frequency row
                        $rows[] = [
                            'receiver_meter_record_id' => $recordId,
                            'section_code'  => $section['code'],
                            'section_name'  => $section['name'],
                            'group_number'  => $group['number'],
                            'group_name'    => $group['name'],
                            'item_name'     => $item['item_name'],
                            'status_a'      => null,
                            'status_b'      => null,
                            'sequelsh_on'   => null,
                            'keterangan'    => null,
                            'nominal'       => null,
                            'hasil'         => null,
                            'is_header'     => false,
                            'sort_order'    => $sortOrder++,
                            'created_at'    => $now,
                            'updated_at'    => $now,
                        ];
                    } else {
                        // Environment item
                        $rows[] = [
                            'receiver_meter_record_id' => $recordId,
                            'section_code'  => $section['code'],
                            'section_name'  => $section['name'],
                            'group_number'  => $group['number'],
                            'group_name'    => $group['name'],
                            'item_name'     => $item['item_name'],
                            'status_a'      => null,
                            'status_b'      => null,
                            'sequelsh_on'   => null,
                            'keterangan'    => null,
                            'nominal'       => $item['nominal'] ?? null,
                            'hasil'         => null,
                            'is_header'     => false,
                            'sort_order'    => $sortOrder++,
                            'created_at'    => $now,
                            'updated_at'    => $now,
                        ];
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * Section + group metadata for frontend rendering.
     */
    public static function sectionMeta(): array
    {
        return array_map(static function ($section) {
            return [
                'code'          => $section['code'],
                'name'          => $section['name'],
                'inputs_layout' => $section['inputs_layout'] ?? 'receiver',
                'groups'        => array_map(static function ($g) {
                    return [
                        'number' => $g['number'] ?? null,
                        'name'   => $g['name']   ?? null,
                    ];
                }, $section['groups']),
            ];
        }, self::sections());
    }
}
