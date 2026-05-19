<?php

namespace App\Services\Cnsd;

/**
 * CnsdDvorMeterTemplate — canonical DVOR Meter Reading item list,
 * mirrored from the official "METER READING — DVOR" paper form (FORM N-5)
 * used by AirNav Surabaya.
 *
 * Key differences from Localizer/T-DME:
 *   - Uses 'limit_value' instead of 'nominal'
 *   - Single 'hasil_pemeriksaan' column (no TX1/TX2 or M1/M2 split)
 *   - Groups labeled A-G (not numbered)
 *   - Section I = PERALATAN, Section II = LINGKUNGAN KERJA
 *
 * Form structure (per the reference image):
 *
 *   Section I — PERALATAN
 *     Groups:
 *       A. Tx. PARA METER
 *       B. MONITOR PARAMETER
 *       C. POWER SUPPLY VOLTAGE
 *       D. TRANSMITTER LEVEL
 *       E. SIDEBAND LEVEL
 *       F. POWER SUPPLY VOLTAGE (DC)
 *       G. BATTERY
 *
 *   Section II — LINGKUNGAN KERJA
 *     Items: Suhu Ruangan, Air Humidity, Kebersihan Ruangan
 */
class CnsdDvorMeterTemplate
{
    public static function sections(): array
    {
        return [
            [
                'code'          => 'I',
                'name'          => 'PERALATAN',
                'inputs_layout' => 'single_result',
                'groups'        => self::peralatanGroups(),
            ],
            [
                'code'          => 'II',
                'name'          => 'LINGKUNGAN KERJA',
                'inputs_layout' => 'environment',
                'groups'        => [
                    [
                        'code'  => 'ENV',
                        'name'  => 'LINGKUNGAN KERJA',
                        'items' => [
                            ['item_name' => 'Pemeriksaan Suhu Ruangan',       'limit_value' => '< 22° C'],
                            ['item_name' => 'Pemeriksaan Air Humidity',       'limit_value' => '√'],
                            ['item_name' => 'Pemeriksaan Kebersihan Ruangan', 'limit_value' => '√'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function peralatanGroups(): array
    {
        return [
            // ─── A. Tx. PARA METER ─────────────────────────────
            [
                'code'  => 'A',
                'name'  => 'Tx. PARA METER',
                'items' => [
                    ['item_name' => 'CARRIER POWER', 'limit_value' => '104 - 106 Watt'],
                    ['item_name' => 'CARRIER MOD',   'limit_value' => '29 - 31 %'],
                    ['item_name' => 'VSWR',           'limit_value' => '< 1.2'],
                    ['item_name' => 'LSB POWER',      'limit_value' => '5.4 - 8 Watt'],
                    ['item_name' => 'USB POWER',      'limit_value' => '5.4 - 8 Watt'],
                ],
            ],

            // ─── B. MONITOR PARAMETER ──────────────────────────
            [
                'code'  => 'B',
                'name'  => 'MONITOR PARAMETER',
                'items' => [
                    ['item_name' => 'Bearing',          'limit_value' => null],
                    ['item_name' => '30 HZ AM',         'limit_value' => '1 ± 0.5 Volt'],
                    ['item_name' => '30 HZ FM',         'limit_value' => '1 ± 0.5 Volt'],
                    ['item_name' => 'SUB.CARRIER',      'limit_value' => '1 ± 0.5 Volt'],
                    ['item_name' => 'IDENT',            'limit_value' => '3 ± 0.5 Vpp'],
                    ['item_name' => 'RECEIVER CARRIER', 'limit_value' => '1 ± 0.5 Volt'],
                ],
            ],

            // ─── C. POWER SUPPLY VOLTAGE ───────────────────────
            [
                'code'  => 'C',
                'name'  => 'POWER SUPPLY VOLTAGE',
                'items' => [
                    ['item_name' => '24 V',      'limit_value' => '23 - 27 Volt'],
                    ['item_name' => '- 40 V',    'limit_value' => '-40 TO -47 Volt'],
                    ['item_name' => '- 45 V',    'limit_value' => '-45 TO -42 Volt'],
                    ['item_name' => '5 V',       'limit_value' => '5 ± 0.5 Volt'],
                    ['item_name' => '- 15 V',    'limit_value' => '-15 ± 0.5 Volt'],
                    ['item_name' => '15 V',      'limit_value' => '15 ± 0.5 Volt'],
                    ['item_name' => '- 15 V MON','limit_value' => '-15 ± 0.5 Volt'],
                    ['item_name' => '15 V MON',  'limit_value' => '15 ± 0.5 Volt'],
                    ['item_name' => '5 V MON',   'limit_value' => '5 ± 0.5 Volt'],
                ],
            ],

            // ─── D. TRANSMITTER LEVEL ──────────────────────────
            [
                'code'  => 'D',
                'name'  => 'TRANSMITTER LEVEL',
                'items' => [
                    ['item_name' => '30 HZ REF.',          'limit_value' => '19.0 - 21.0 Vpp'],
                    ['item_name' => 'TX.DRIVE',            'limit_value' => '3.5 - 5 Volt'],
                    ['item_name' => 'TX.BAL. I',           'limit_value' => '< 0.7 Volt'],
                    ['item_name' => 'TX.BAL. II',          'limit_value' => '< 0.7 Volt'],
                    ['item_name' => 'COMB. BAL.',          'limit_value' => '< 1.2 Volt'],
                    ['item_name' => 'CARRIER FWD.',        'limit_value' => '4.33 - 4.37 Volt'],
                    ['item_name' => 'CARRIER PEAK 30 HZ AM','limit_value' => '5.6 - 5.7 Vpk'],
                    ['item_name' => 'CARRIER REV.',        'limit_value' => '< 0.35 Volt'],
                ],
            ],

            // ─── E. SIDEBAND LEVEL ─────────────────────────────
            [
                'code'  => 'E',
                'name'  => 'SIDEBAND LEVEL',
                'items' => [
                    ['item_name' => 'BLEND.FUNCTION LSB', 'limit_value' => '6 - 9 Vpk'],
                    ['item_name' => 'BLEND.FUNCTION USB', 'limit_value' => '6 - 9 Vpk'],
                    ['item_name' => 'FREQ.CONTROL LSB',   'limit_value' => 'Volt'],
                    ['item_name' => 'FREQ.CONTROL USB',   'limit_value' => 'Volt'],
                    ['item_name' => 'LSB LEVEL / USB LEVEL','limit_value' => '3.3 ± 0.5 Volt'],
                    ['item_name' => 'STATUS',             'limit_value' => 'NORMAL'],
                ],
            ],

            // ─── F. POWER SUPPLY VOLTAGE (DC) ──────────────────
            [
                'code'  => 'F',
                'name'  => 'POWER SUPPLY VOLTAGE',
                'items' => [
                    ['item_name' => 'DC VOLTAGE / DC CURRENT TX 1', 'limit_value' => '27-28V / 20-22 A'],
                    ['item_name' => 'DC VOLTAGE / DC CURRENT TX 2', 'limit_value' => '27-28V / 20-22 A'],
                ],
            ],

            // ─── G. BATTERY ────────────────────────────────────
            [
                'code'  => 'G',
                'name'  => 'BATTERY',
                'items' => [
                    ['item_name' => 'DC VOLTAGE TX 1 / DC VOLTAGE TX 2', 'limit_value' => '27-28V / 27-28 V'],
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
                // Add group header row for Section I
                if ($section['code'] === 'I') {
                    $rows[] = [
                        'dvor_meter_record_id' => $recordId,
                        'section_code'    => $section['code'],
                        'section_name'    => $section['name'],
                        'group_code'      => $group['code'],
                        'group_name'      => $group['name'],
                        'item_name'       => null,
                        'limit_value'     => null,
                        'hasil_pemeriksaan' => null,
                        'keterangan'      => null,
                        'is_header'       => true,
                        'sort_order'      => $sortOrder++,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];
                }

                foreach ($group['items'] as $item) {
                    $rows[] = [
                        'dvor_meter_record_id' => $recordId,
                        'section_code'    => $section['code'],
                        'section_name'    => $section['name'],
                        'group_code'      => $group['code'],
                        'group_name'      => $group['name'],
                        'item_name'       => $item['item_name'],
                        'limit_value'     => $item['limit_value'] ?? null,
                        'hasil_pemeriksaan' => null,
                        'keterangan'      => null,
                        'is_header'       => false,
                        'sort_order'      => $sortOrder++,
                        'created_at'      => $now,
                        'updated_at'      => $now,
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
                'inputs_layout' => $section['inputs_layout'] ?? 'single_result',
                'groups'        => array_map(static function ($g) {
                    return ['code' => $g['code'] ?? null, 'name' => $g['name'] ?? null];
                }, $section['groups']),
            ];
        }, self::sections());
    }
}
