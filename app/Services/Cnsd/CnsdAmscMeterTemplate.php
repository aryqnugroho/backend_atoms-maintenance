<?php

namespace App\Services\Cnsd;

/**
 * CnsdAmscMeterTemplate — canonical AMSC Meter Reading item list,
 * mirrored from the official "METER READING — AMSC" paper form
 * used by AirNav Surabaya.
 *
 * Form structure (per the reference image):
 *
 *   Section 1 — FRONT PANEL
 *     Items: All Status Indikator, Operation Server, Signal selector,
 *            Change Over, Clock Signal
 *     Layout: NO | PEMBACAAN METER READING | NOMINAL | HASIL A | HASIL B | KETERANGAN
 *
 *   Section 2 — POWER SUPPLY UNIT
 *     Items: +60V, -60V, +12V, +5V, -12V
 *     Layout: NO | PEMBACAAN METER READING | NOMINAL(blocked) | HASIL | KETERANGAN
 *
 *   Section 3 — CHANNEL AMSC
 *     Items: Channel 1 - 32
 *     Layout: NO | PEMBACAAN METER READING | ADDRESS | STATUS | CCT | KETERANGAN
 *
 *   Section 4 — LINGKUNGAN KERJA
 *     Items: Suhu Ruangan, Air Humidity, Kebersihan Ruangan
 *     Layout: NO | KEGIATAN | Nominal | HASIL PEMERIKSAAN | KETERANGAN
 */
class CnsdAmscMeterTemplate
{
    /**
     * Full section + group + item structure for the AMSC Meter Reading form.
     */
    public static function sections(): array
    {
        return [
            // ───────────────────────────────────────────────────────────
            // SECTION 1 — FRONT PANEL
            // Inputs: HASIL A + HASIL B columns (hasil_a / hasil_b)
            // ───────────────────────────────────────────────────────────
            [
                'code'            => '1',
                'name'            => 'FRONT PANEL',
                'inputs_layout'   => 'dual_ab',
                'columns_label_1' => 'A',
                'columns_label_2' => 'B',
                'groups' => [
                    [
                        'number' => 1,
                        'name'   => 'FRONT PANEL',
                        'items'  => [
                            ['item_number' => '1', 'item_name' => 'All Status Indikator', 'nominal' => 'Normal / Alrm'],
                            ['item_number' => '2', 'item_name' => 'Operation Server',     'nominal' => '√ / -'],
                            ['item_number' => '3', 'item_name' => 'Signal selector',      'nominal' => '√ / -'],
                            ['item_number' => '4', 'item_name' => 'Change Over',          'nominal' => '√ / -'],
                            ['item_number' => '5', 'item_name' => 'Clock Signal',         'nominal' => 'OK / Not'],
                        ],
                    ],
                ],
            ],

            // ───────────────────────────────────────────────────────────
            // SECTION 2 — POWER SUPPLY UNIT
            // Inputs: HASIL column only (hasil). Nominal area is blocked/dark.
            // ───────────────────────────────────────────────────────────
            [
                'code'            => '2',
                'name'            => 'POWER SUPPLY UNIT',
                'inputs_layout'   => 'single_hasil',
                'columns_label_1' => 'HASIL',
                'columns_label_2' => null,
                'groups' => [
                    [
                        'number' => 2,
                        'name'   => 'POWER SUPPLY UNIT',
                        'items'  => [
                            ['item_number' => '1', 'item_name' => '+ 60 V', 'nominal' => null],
                            ['item_number' => '2', 'item_name' => '- 60 V', 'nominal' => null],
                            ['item_number' => '3', 'item_name' => '+ 12 V', 'nominal' => null],
                            ['item_number' => '4', 'item_name' => '+ 5 V',  'nominal' => null],
                            ['item_number' => '5', 'item_name' => '- 12 V', 'nominal' => null],
                        ],
                    ],
                ],
            ],

            // ───────────────────────────────────────────────────────────
            // SECTION 3 — CHANNEL AMSC
            // Inputs: ADDRESS + STATUS + CCT + KETERANGAN
            // ───────────────────────────────────────────────────────────
            [
                'code'            => '3',
                'name'            => 'CHANNEL AMSC',
                'inputs_layout'   => 'channel',
                'columns_label_1' => null,
                'columns_label_2' => null,
                'groups' => [
                    [
                        'number' => 3,
                        'name'   => 'CHANNEL AMSC',
                        'items'  => self::channelItems(),
                    ],
                ],
            ],

            // ───────────────────────────────────────────────────────────
            // SECTION 4 — LINGKUNGAN KERJA
            // Inputs: NOMINAL | HASIL PEMERIKSAAN | KETERANGAN
            // ───────────────────────────────────────────────────────────
            [
                'code'            => '4',
                'name'            => 'LINGKUNGAN KERJA',
                'inputs_layout'   => 'environment',
                'columns_label_1' => 'HASIL PEMERIKSAAN',
                'columns_label_2' => null,
                'groups' => [
                    [
                        'number' => 4,
                        'name'   => 'LINGKUNGAN KERJA',
                        'items'  => [
                            ['item_number' => '1', 'item_name' => 'PEMERIKSAAN SUHU RUANGAN',       'nominal' => 'Max 22°C'],
                            ['item_number' => '2', 'item_name' => 'PEMERIKSAAN AIR HUMIDITY',       'nominal' => '√'],
                            ['item_number' => '3', 'item_name' => 'PEMERIKSAAN KEBERSIHAN RUANGAN', 'nominal' => '√'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Channel 1 - 32 list, mirroring the paper form.
     * Each channel has default address, status, and keterangan from the reference image.
     */
    private static function channelItems(): array
    {
        $channels = [
            1  => ['address' => 'TO MKS',     'status' => null,  'keterangan' => null],
            2  => ['address' => null,          'status' => null,  'keterangan' => null],
            3  => ['address' => 'WARD',        'status' => null,  'keterangan' => 'AMHS Kediri'],
            4  => ['address' => 'WARRAODB',    'status' => null,  'keterangan' => null],
            5  => ['address' => 'WARRASMB',    'status' => null,  'keterangan' => 'ASMGCS'],
            6  => ['address' => 'WARRZPZE',    'status' => null,  'keterangan' => 'ARO'],
            7  => ['address' => 'WARRAWOS',    'status' => null,  'keterangan' => 'AWOS'],
            8  => ['address' => 'WARRBILL',    'status' => null,  'keterangan' => null],
            9  => ['address' => 'WARRWARA',    'status' => null,  'keterangan' => 'Malang'],
            10 => ['address' => null,          'status' => null,  'keterangan' => null],
            11 => ['address' => 'WARRYMYE',    'status' => null,  'keterangan' => 'Meteo'],
            12 => ['address' => null,          'status' => null,  'keterangan' => null],
            13 => ['address' => 'WARRATIS',    'status' => null,  'keterangan' => null],
            14 => ['address' => null,          'status' => null,  'keterangan' => null],
            15 => ['address' => 'WARRCOMX',    'status' => null,  'keterangan' => null],
            16 => ['address' => null,          'status' => null,  'keterangan' => null],
            17 => ['address' => 'TO MKS',      'status' => 'U/S', 'keterangan' => 'ALTRN CH01'],
            18 => ['address' => null,          'status' => null,  'keterangan' => null],
            19 => ['address' => 'WARRAODB',    'status' => 'U/S', 'keterangan' => 'ALTRN CH04'],
            20 => ['address' => null,          'status' => null,  'keterangan' => null],
            21 => ['address' => null,          'status' => null,  'keterangan' => null],
            22 => ['address' => null,          'status' => null,  'keterangan' => null],
            23 => ['address' => null,          'status' => null,  'keterangan' => null],
            24 => ['address' => null,          'status' => null,  'keterangan' => null],
            25 => ['address' => 'WARRASMG',    'status' => null,  'keterangan' => null],
            26 => ['address' => null,          'status' => null,  'keterangan' => null],
            27 => ['address' => null,          'status' => null,  'keterangan' => null],
            28 => ['address' => 'WARRZEZA',    'status' => null,  'keterangan' => null],
            29 => ['address' => 'WARRYFYA',    'status' => null,  'keterangan' => 'SPV Correction ARO'],
            30 => ['address' => 'WARRYFYB',    'status' => null,  'keterangan' => 'SPV Correction Lt.2 AMSC'],
            31 => ['address' => null,          'status' => null,  'keterangan' => 'Teleprinter Monitor 1'],
            32 => ['address' => null,          'status' => null,  'keterangan' => 'Teleprinter Monitor 2'],
        ];

        $items = [];
        foreach ($channels as $num => $data) {
            $items[] = [
                'item_number'  => 'Channel ' . $num,
                'item_name'    => 'Channel ' . $num,
                'nominal'      => null,
                'address'      => $data['address'],
                'status_value' => $data['status'],
                'keterangan'   => $data['keterangan'],
            ];
        }

        return $items;
    }

    /**
     * Flatten the structured template into row inserts for cnsd_amsc_meter_items.
     */
    public static function buildItemRows(int $recordId): array
    {
        $rows = [];
        $sortOrder = 0;
        $now = now();

        foreach (self::sections() as $section) {
            foreach ($section['groups'] as $group) {
                foreach ($group['items'] as $item) {
                    $rows[] = [
                        'amsc_meter_record_id' => $recordId,
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
                        'address'              => $item['address']      ?? null,
                        'status_value'         => $item['status_value'] ?? null,
                        'cct'                  => null,
                        'keterangan'           => $item['keterangan']   ?? null,
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

    /**
     * Section + group metadata (without items) — used by the frontend to render
     * tabs/accordions and the right column headers per section.
     */
    public static function sectionMeta(): array
    {
        return array_map(static function ($section) {
            return [
                'code'            => $section['code'],
                'name'            => $section['name'],
                'inputs_layout'   => $section['inputs_layout'] ?? 'single_hasil',
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
