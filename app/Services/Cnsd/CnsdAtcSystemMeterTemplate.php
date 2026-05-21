<?php

namespace App\Services\Cnsd;

/**
 * CnsdAtcSystemMeterTemplate — canonical ATC SYSTEM Meter Reading template,
 * mirrored from the official "METER READING — ATC SYSTEM" paper form
 * (FORM A-1) used by AirNav Surabaya. Sourced from `012_ATC_SYSTEM_Page_1.jpg`
 * and `012_ATC_SYSTEM_Page_2.jpg`.
 *
 * Sections (12 total, 7 distinct layouts):
 *   A — MAINTENANCE              layout: maintenance        (NO | Sub | Nominal | Reading-adaptive | Keterangan)
 *   B — SOURCE DATA              layout: source_data        (NO | Item | LINE 1 (O/F/I) | LINE 2 (O/F/I) | Keterangan)
 *   C — SERVERS                  layout: cpu_status         (NO | Item | Nominal | Process Running | CPU STATUS multi | Keterangan)
 *   D — NETWORK                  layout: network_status     (NO | Item | SWITCH STATUS multi | ALL PORT OK | Keterangan)
 *   E — TOWER                    layout: cpu_status
 *   F — APPROACH CONTROL ROOM    layout: cpu_status
 *   G — SUP                      layout: cpu_status
 *   H — FDD                      layout: cpu_status
 *   I — MILITARY                 layout: cpu_status
 *   J — RECORDING SYSTEM         layout: server_dual_ab     (NO | Sub | Nominal | REC A (adaptive) | REC B (adaptive) | Keterangan)
 *   K — DATA BASE CLEANUP RBP    layout: rbp_count          (NO | Kegiatan | RBP-A #1 | RBP-A #2 | RBP-B #1 | RBP-B #2 | Keterangan)
 *   L — LINGKUNGAN KERJA         layout: environment        (NO | Kegiatan | Parameter | Hasil | Keterangan)
 *
 * Each item carries a `nominal` string. On the frontend, the Reading cell
 * adapts to the nominal: "OK / NO" → toggle, "All OK / NO" → toggle, "Time" →
 * HH:MM:SS picker, numeric nominal → number input, etc.
 *
 * Items with `is_header=true` are visual separators only and are skipped by
 * the update handler (the frontend renders them as a sub-header row inside
 * the section's group panel).
 */
class CnsdAtcSystemMeterTemplate
{
    public static function sections(): array
    {
        return [
            // ─── A. MAINTENANCE ─────────────────────────────────────────
            [
                'code'          => 'A',
                'name'          => 'MAINTENANCE',
                'inputs_layout' => 'maintenance',
                'groups' => [[
                    'number' => null, 'name' => null,
                    'items' => [
                        ['item_name' => 'SMC (System Management Console)', 'is_header' => true],
                        ['sub_item_label' => 'ALL Status',                 'nominal' => 'OK / NO'],
                        ['sub_item_label' => 'CONFIGURATION - ANALYSIS',   'nominal' => 'All OK / NO'],

                        ['item_name' => 'Clock', 'is_header' => true],
                        ['sub_item_label' => 'GPS Time',    'nominal' => 'Time'],
                        ['sub_item_label' => 'System Time', 'nominal' => 'Time'],

                        ['item_name' => 'ATIS', 'is_header' => true],
                        ['sub_item_label' => 'Last message update (on FDD message log)', 'nominal' => 'Time'],

                        ['item_name' => 'ATC SPESIALIST', 'is_header' => true],
                        ['sub_item_label' => 'Target on ASD', 'nominal' => 'OK / NO'],
                    ],
                ]],
            ],

            // ─── B. SOURCE DATA ─────────────────────────────────────────
            [
                'code'          => 'B',
                'name'          => 'SOURCE DATA',
                'inputs_layout' => 'source_data',
                'groups' => [[
                    'number' => null, 'name' => null,
                    'items' => [
                        ['item_name' => 'SBY ADSB', 'nominal' => 'O / F / I'],
                        ['item_name' => 'SBY',      'nominal' => 'O / F / I'],
                        ['item_name' => 'BIM',      'nominal' => 'O / F / I'],
                        ['item_name' => 'BLI',      'nominal' => 'O / F / I'],
                        ['item_name' => 'YOG',      'nominal' => 'O / F / I'],
                        ['item_name' => 'SMG',      'nominal' => 'O / F / I'],
                    ],
                ]],
            ],

            // ─── C. SERVERS ─────────────────────────────────────────────
            [
                'code'          => 'C',
                'name'          => 'SERVERS',
                'inputs_layout' => 'cpu_status',
                'groups' => [[
                    'number' => null, 'name' => null,
                    'items' => [
                        ['item_name' => 'FDP-A', 'nominal' => '14'],
                        ['item_name' => 'FDP-B', 'nominal' => '14'],
                        ['item_name' => 'SDP-A', 'nominal' => '16'],
                        ['item_name' => 'SDP-B', 'nominal' => '16'],
                        ['item_name' => 'RBP-A', 'nominal' => '12'],
                        ['item_name' => 'RBP-B', 'nominal' => '11'],
                    ],
                ]],
            ],

            // ─── D. NETWORK ─────────────────────────────────────────────
            [
                'code'          => 'D',
                'name'          => 'NETWORK',
                'inputs_layout' => 'network_status',
                'groups' => [[
                    'number' => null, 'name' => null,
                    'items' => [
                        ['item_name' => 'SW-RACK-A',  'nominal' => 'D / A / N'],
                        ['item_name' => 'SW-RACK-B',  'nominal' => 'D / A / N'],
                        ['item_name' => 'SW-TOWER-1', 'nominal' => 'D / A / N'],
                        ['item_name' => 'SW-TOWER-2', 'nominal' => 'D / A / N'],
                    ],
                ]],
            ],

            // ─── E. TOWER ───────────────────────────────────────────────
            [
                'code'          => 'E',
                'name'          => 'TOWER',
                'inputs_layout' => 'cpu_status',
                'groups' => [[
                    'number' => null, 'name' => null,
                    'items' => [
                        ['item_name' => 'TWR-ASD',         'nominal' => '1'],
                        ['item_name' => 'GND-ASD',         'nominal' => '1'],
                        ['item_name' => 'GND-FDD',         'nominal' => '1'],
                        ['item_name' => 'TWR-FDD',         'nominal' => '1'],
                        ['item_name' => 'APRON',           'nominal' => '1'],
                        ['item_name' => 'STRIP PRINTER 1', 'nominal' => 'OK / NO'],
                    ],
                ]],
            ],

            // ─── F. APPROACH CONTROL ROOM ───────────────────────────────
            [
                'code'          => 'F',
                'name'          => 'APPROACH CONTROL ROOM',
                'inputs_layout' => 'cpu_status',
                'groups' => [[
                    'number' => null, 'name' => null,
                    'items' => [
                        ['item_name' => 'APP-ASD',         'nominal' => '1'],
                        ['item_name' => 'APP-FDD',         'nominal' => '2'],
                        ['item_name' => 'TMA-ASD-1',       'nominal' => '1'],
                        ['item_name' => 'TMA-ASD-2',       'nominal' => '1'],
                        ['item_name' => 'TMA-FDD-1',       'nominal' => '2'],
                        ['item_name' => 'TMA-FDD-2',       'nominal' => '2'],
                        ['item_name' => 'STRIP PRINTER 2', 'nominal' => 'OK / NO'],
                    ],
                ]],
            ],

            // ─── G. SUP ─────────────────────────────────────────────────
            [
                'code'          => 'G',
                'name'          => 'SUP',
                'inputs_layout' => 'cpu_status',
                'groups' => [[
                    'number' => null, 'name' => null,
                    'items' => [
                        ['item_name' => 'APP-SUP', 'nominal' => '1'],
                        ['item_name' => 'TWR-SUP', 'nominal' => '1'],
                    ],
                ]],
            ],

            // ─── H. FDD ─────────────────────────────────────────────────
            [
                'code'          => 'H',
                'name'          => 'FDD',
                'inputs_layout' => 'cpu_status',
                'groups' => [[
                    'number' => null, 'name' => null,
                    'items' => [
                        ['item_name' => 'BRIEFING OFFICE', 'nominal' => '2'],
                        ['item_name' => 'FDD-FDD-1',       'nominal' => '1'],
                    ],
                ]],
            ],

            // ─── I. MILITARY ────────────────────────────────────────────
            [
                'code'          => 'I',
                'name'          => 'MILITARY',
                'inputs_layout' => 'cpu_status',
                'groups' => [[
                    'number' => null, 'name' => null,
                    'items' => [
                        ['item_name' => 'MILITARY', 'nominal' => '1'],
                    ],
                ]],
            ],

            // ─── J. RECORDING SYSTEM ────────────────────────────────────
            [
                'code'          => 'J',
                'name'          => 'RECORDING SYSTEM',
                'inputs_layout' => 'server_dual_ab',
                'groups' => [[
                    'number' => null, 'name' => null,
                    'items' => [
                        ['item_name' => 'Recorder Status', 'nominal' => 'All OK / NO'],

                        ['item_name' => 'Clock', 'is_header' => true],
                        ['sub_item_label' => 'GPS Time',    'nominal' => 'Time'],
                        ['sub_item_label' => 'System Time', 'nominal' => 'Time'],

                        ['item_name' => 'Last Update Video Record', 'is_header' => true],
                        ['sub_item_label' => 'SUR_02_TMA ASD 2 (EAST)', 'nominal' => 'Time'],
                        ['sub_item_label' => 'SUR_03_TMA ASD 1 (WEST)', 'nominal' => 'Time'],
                        ['sub_item_label' => 'SUR_03_APP ASD (APP)',    'nominal' => 'Time'],
                        ['sub_item_label' => 'SUR_04_APP SUP',          'nominal' => 'Time'],
                        ['sub_item_label' => 'SUR_05_TWR ASD',          'nominal' => 'Time'],
                        ['sub_item_label' => 'SUR_06_TWR SUP',          'nominal' => 'Time'],
                        ['sub_item_label' => 'SUR_07_MILITARY',         'nominal' => 'Time'],
                        ['sub_item_label' => 'System Track Surabaya',   'nominal' => 'Time'],
                        ['sub_item_label' => 'Local Radar Surabaya A',  'nominal' => 'Time'],
                        ['sub_item_label' => 'Local Radar Surabaya B',  'nominal' => 'Time'],
                    ],
                ]],
            ],

            // ─── K. DATA BASE CLEANUP RADAR BY PASS ─────────────────────
            [
                'code'          => 'K',
                'name'          => 'DATA BASE CLEANUP RADAR BY PASS',
                'inputs_layout' => 'rbp_count',
                'groups' => [[
                    'number' => null, 'name' => null,
                    'items' => [
                        ['item_name' => 'CLEANUP REFERENCE DATE'],
                        ['item_name' => 'LOGLINES'],
                        ['item_name' => 'FLIGHT HISTORY'],
                    ],
                ]],
            ],

            // ─── L. LINGKUNGAN KERJA ────────────────────────────────────
            [
                'code'          => 'L',
                'name'          => 'LINGKUNGAN KERJA',
                'inputs_layout' => 'environment',
                'groups' => [[
                    'number' => null, 'name' => null,
                    'items' => [
                        ['item_name' => 'Pemeriksaan Suhu Ruangan',       'nominal' => "Max 22° C"],
                        ['item_name' => 'Pemeriksaan Air Humidity',       'nominal' => ''],
                        ['item_name' => 'Pemeriksaan Kebersihan Ruangan', 'nominal' => '√'],
                    ],
                ]],
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
                        'atc_system_meter_record_id' => $recordId,
                        'section_code'   => $section['code'],
                        'section_name'   => $section['name'],
                        'group_number'   => $group['number'] ?? null,
                        'group_name'     => $group['name'] ?? null,
                        'item_name'      => $item['item_name'] ?? null,
                        'sub_item_label' => $item['sub_item_label'] ?? null,
                        'nominal'        => $item['nominal'] ?? null,
                        'value_1'        => null,
                        'value_2'        => null,
                        'value_3'        => null,
                        'value_4'        => null,
                        'status_flags'   => null,
                        'keterangan'     => null,
                        'is_header'      => (bool) ($item['is_header'] ?? false),
                        'sort_order'     => $sortOrder++,
                        'created_at'     => $now,
                        'updated_at'     => $now,
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
                'inputs_layout' => $section['inputs_layout'],
                'groups'        => array_map(static function ($g) {
                    return ['number' => $g['number'] ?? null, 'name' => $g['name'] ?? null];
                }, $section['groups']),
            ];
        }, self::sections());
    }
}
