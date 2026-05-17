<?php

namespace App\Services\Cnsd;

/**
 * CnsdRecorderMeterTemplate — canonical Recorder Meter Reading item list,
 * mirrored from the official "METER READING — RECORDER" paper form
 * (FORM C-3) used by AirNav Surabaya.
 *
 * The backend uses this template at create-time to seed
 * cnsd_recorder_meter_items for every new Recorder record. Users only fill in
 * technical readings — they never define items themselves.
 *
 * Form structure (per the reference image):
 *
 *   Section A — PERALATAN
 *     Group 1: KVM         (All Status Indikator)
 *     Group 2: SERVER      (Main Server, Standby server)
 *     Group 3: POWER       (AC, DC)
 *     Group 4: CHANNEL     (Channel 1 - 64, with U/S items where applicable)
 *
 *   Section B — LINGKUNGAN KERJA  (suhu, humidity, kebersihan)
 *
 * U/S (Un-Serviceable) channels:
 *   Some channels appear as red strips on the paper form with a "U/S" label
 *   (Channel 7, 21-27, 29-35). These are physically unavailable on the
 *   recorder. They are seeded with `is_blocked = true` and `block_reason = 'U/S'`.
 *   Frontend renders them as a red strip with all inputs disabled.
 *   Backend rejects any update payload targeting blocked rows.
 *
 * Nominal values seeded from the form (column NOMINAL):
 *   - "Normal / Alrm"  → KVM (dropdown Normal / Alrm)
 *   - "√ / -"          → SERVER (dropdown √ / -)
 *   - null             → POWER (manual input — no nominal on form)
 *   - "<channel name>" → CHANNEL (dropdown Normal / Fault per channel)
 *   - "< 22° C"        → environment temperature (manual input)
 *   - "√"              → environment humidity & cleanliness (dropdown √ / -)
 */
class CnsdRecorderMeterTemplate
{
    /**
     * Section + group + item structure for the Recorder Meter Reading form.
     *
     * Item shape:
     *   - section_code   : 'A' or 'B' (alphabet header on paper form)
     *   - section_name   : full section name
     *   - group_number   : optional sub-grouping inside the section (1–4 in A)
     *   - group_name     : optional sub-grouping label
     *   - item_number    : visible numbering on paper (e.g. "Channel 7")
     *   - item_name      : display label
     *   - nominal        : threshold / expected value from the paper form
     *   - is_blocked     : true for U/S items (rendered red, inputs disabled)
     *   - block_reason   : "U/S" for blocked items
     */
    public static function sections(): array
    {
        return [
            // ───────────────────────────────────────────────────────────
            // SECTION A — PERALATAN
            // Inputs: Server A + Server B columns (hasil_server_a / b)
            // ───────────────────────────────────────────────────────────
            [
                'code'            => 'A',
                'name'            => 'PERALATAN',
                'inputs_layout'   => 'server_dual',
                'columns_label_1' => 'Server A',
                'columns_label_2' => 'Server B',
                'groups' => [
                    [
                        'number' => 1,
                        'name'   => 'KVM',
                        'items'  => [
                            ['item_number' => '', 'item_name' => 'All Status Indikator', 'nominal' => 'Normal / Alrm'],
                        ],
                    ],
                    [
                        'number' => 2,
                        'name'   => 'SERVER',
                        'items'  => [
                            ['item_number' => '', 'item_name' => 'Main Server',    'nominal' => '√ / -'],
                            ['item_number' => '', 'item_name' => 'Standby server', 'nominal' => '√ / -'],
                        ],
                    ],
                    [
                        'number' => 3,
                        'name'   => 'POWER',
                        'items'  => [
                            ['item_number' => '', 'item_name' => 'AC', 'nominal' => null],
                            ['item_number' => '', 'item_name' => 'DC', 'nominal' => null],
                        ],
                    ],
                    [
                        'number' => 4,
                        'name'   => 'CHANNEL',
                        'items'  => self::channelItems(),
                    ],
                ],
            ],

            // ───────────────────────────────────────────────────────────
            // SECTION B — LINGKUNGAN KERJA
            // Inputs: NOMINAL | HASIL PEMERIKSAAN | KETERANGAN
            // ───────────────────────────────────────────────────────────
            [
                'code'            => 'B',
                'name'            => 'LINGKUNGAN KERJA',
                'inputs_layout'   => 'environment',
                'columns_label_1' => 'HASIL PEMERIKSAAN',
                'columns_label_2' => null,
                'groups' => [
                    [
                        'number' => null,
                        'name'   => null,
                        'items'  => [
                            ['item_number' => '1', 'item_name' => 'PEMERIKSAAN SUHU RUANGAN',      'nominal' => '< 22° C'],
                            ['item_number' => '2', 'item_name' => 'PEMERIKSAAN AIR HUMIDITY',      'nominal' => '√'],
                            ['item_number' => '3', 'item_name' => 'PEMERIKSAAN KEBERSIHAN RUANGAN', 'nominal' => '√'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Channel 1 - 64 list, mirroring the paper form. Channels marked U/S in
     * the reference image are seeded with is_blocked = true.
     *
     * Channel header on paper form:
     *   NOMINAL = "Content" (e.g. "Ground Primary")
     *   HASIL   = Normal / Fault (dropdown on FE)
     */
    private static function channelItems(): array
    {
        // Map of channel number → content (null = U/S blocked).
        // Channel 7, 21-27, 29-35 are U/S per the reference image.
        $channels = [
            1  => 'Ground Primary',
            2  => 'Ground Secondary',
            3  => 'Tower Primary',
            4  => 'Tower Secondary',
            5  => 'Director Primary',
            6  => 'Director Secondary',
            7  => null,                      // U/S
            8  => 'East Primary',
            9  => 'West Primary',
            10 => 'West Secondary',
            11 => 'Blora Primary 125.1 MHz',
            12 => 'DS DHOHO KEDIRI',
            13 => 'MKS-ER Primary',
            14 => 'MKS-ER Secondary',
            15 => 'ATIS',
            16 => 'EMERGENCY',
            17 => 'CDU FUNKE',
            18 => 'CDU Primary',
            19 => 'CDU Secondary',
            20 => 'Blora Secondary',
            21 => null,                      // U/S
            22 => null,                      // U/S
            23 => null,                      // U/S
            24 => null,                      // U/S
            25 => null,                      // U/S
            26 => null,                      // U/S
            27 => null,                      // U/S
            28 => 'DS Jogja',
            29 => null,                      // U/S
            30 => null,                      // U/S
            31 => null,                      // U/S
            32 => null,                      // U/S
            33 => null,                      // U/S
            34 => null,                      // U/S
            35 => null,                      // U/S
            36 => 'Telp TOWER 581',
            37 => 'Telp TOWER 110',
            38 => 'Telp APP 597',
            39 => 'Telp APP 8655223',
            40 => 'TRUNKING TOWER',
            41 => 'Frequentis Control Tower',
            42 => 'Frequentis Ass Tower',
            43 => 'Frequentis Control GND',
            44 => 'Frequentis Control CDU',
            45 => 'Frequentis Control Direct',
            46 => 'Frequentis East Asst',
            47 => 'Frequentis West Asst',
            48 => 'Frequentis West Controller',
            49 => 'Frequentis Director Control',
            50 => 'Frequentis Director Asst',
            51 => 'Frequentis Supervisor APP',
            52 => 'DS Banjarmasin',
            53 => 'DS Pangkalanbun',
            54 => 'DS JAKARTA',
            55 => 'DS MKS- EAST',
            56 => 'DS MKS-WEST',
            57 => 'DS BALI',
            58 => 'DS SEMARANG',
            59 => 'DS BALI INFO',
            60 => 'DS MALANG',
            61 => 'DS YIA',
            62 => 'DS MADIUN',
            63 => 'EAST SECONDARY',
            64 => 'DS UPG PKN',
        ];

        $items = [];
        foreach ($channels as $num => $content) {
            if ($content === null) {
                $items[] = [
                    'item_number'  => 'Channel ' . $num,
                    'item_name'    => 'Channel ' . $num,
                    'nominal'      => null,
                    'is_blocked'   => true,
                    'block_reason' => 'U/S',
                ];
                continue;
            }
            $items[] = [
                'item_number' => 'Channel ' . $num,
                'item_name'   => 'Channel ' . $num,
                'nominal'     => $content,
                'is_blocked'  => false,
            ];
        }

        return $items;
    }

    /**
     * Flatten the structured template into row inserts for cnsd_recorder_meter_items.
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
                        'recorder_meter_record_id' => $recordId,
                        'section_code'             => $section['code'],
                        'section_name'             => $section['name'],
                        'group_number'             => $group['number'] ?? null,
                        'group_name'               => $group['name']   ?? null,
                        'item_number'              => $item['item_number'] ?? null,
                        'item_name'                => $item['item_name'],
                        'nominal'                  => $item['nominal']    ?? null,
                        'hasil_server_a'           => null,
                        'hasil_server_b'           => null,
                        'hasil'                    => null,
                        'keterangan'               => null,
                        'is_blocked'               => $item['is_blocked'] ?? false,
                        'block_reason'             => $item['block_reason'] ?? null,
                        'sort_order'               => $sortOrder++,
                        'created_at'               => $now,
                        'updated_at'               => $now,
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
                'inputs_layout'   => $section['inputs_layout'] ?? 'server_dual',
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
