<?php

namespace App\Services\Cnsd;

/**
 * CnsdRadarMeterTemplate — canonical Radar Meter Reading item list, mirrored
 * from the official "METER READING — RADAR" paper form used by AirNav Surabaya.
 *
 * The backend uses this template at create-time to seed cnsd_radar_meter_items
 * for every new Radar record. Users only fill in technical readings — they
 * never define items themselves.
 *
 * Form structure (per the reference image):
 *
 *   Section A — TERMONITOR DI LCMS
 *     Group 1: MSSR TRANSMITTER A/B   (Power SUM/OMEGA, VSWR SUM/OMEGA)
 *     Group 2: SSR EXTRACTOR          (GPS Receiver checks, Extractor FCOMP)
 *     Group 3: AILAN CH.A/CH.B        (Status, LO, Receiver Supply, deviations)
 *     Group 4: RADAR CONTROL          (Antenna, Power Supply, motors, RADAR sub)
 *
 *   Section C — LINGKUNGAN KERJA      (suhu, humidity, kebersihan)
 *
 * NOTE: The paper form has section "B" reserved (TERMONITOR DI RIM) but the
 * provided reference image only shows A and C. We honor the source-of-truth
 * (image) here. If section B is later required, add it as another group set.
 *
 * Standard values seeded from the form (column STANDART):
 *   - "Green" indicates expected nominal LED state.
 *   - "+- 0", "5,6", "OK / Green" are read off the paper form.
 */
class CnsdRadarMeterTemplate
{
    /**
     * Section + group + item structure for the Radar Meter Reading form.
     *
     * Item shape:
     *   - section_code      : 'A' or 'C' (alphabet header on paper form)
     *   - section_name      : full section name
     *   - group_number      : optional sub-grouping inside the section (1–4 in A)
     *   - group_name        : optional sub-grouping label
     *   - item_number       : visible numbering on paper
     *   - item_name         : display label
     *   - standard          : threshold / expected value from the paper form
     *   - kondisi_teknis_tx1/tx2 / hasil : seeded blank — user fills these
     */
    public static function sections(): array
    {
        return [
            // ───────────────────────────────────────────────────────────
            // SECTION A — TERMONITOR DI LCMS
            // Inputs: TX I + TX II columns (kondisi_teknis_tx1 / tx2)
            // ───────────────────────────────────────────────────────────
            [
                'code'           => 'A',
                'name'           => 'TERMONITOR DI LCMS',
                'inputs_layout'  => 'tx_dual',     // FE renders TX I / TX II columns
                'columns_label_1'=> 'TX I',
                'columns_label_2'=> 'TX II',
                'groups' => [
                    [
                        'number' => 1,
                        'name'   => 'MSSR TRANSMITTER A/B',
                        'items'  => [
                            ['item_number' => '', 'item_name' => 'Power SUM',    'standard' => '>2500 W'],
                            ['item_number' => '', 'item_name' => 'Power OMEGA',  'standard' => '>2500 W'],
                            ['item_number' => '', 'item_name' => 'VSWR SUM',     'standard' => null],
                            ['item_number' => '', 'item_name' => 'VSWR OMEGA',   'standard' => null],
                        ],
                    ],
                    [
                        'number' => 2,
                        'name'   => 'SSR EXTRACTOR',
                        'items'  => [
                            // Sub-section header (GPS Receiver group)
                            ['item_number' => '', 'item_name' => '* GPS Receiver',     'standard' => null, 'is_subheader' => true],
                            ['item_number' => '', 'item_name' => 'Global',             'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'ROM',                'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Receiver',           'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Data Memory',        'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Internal Timer',     'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Oscilator Drift',    'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Configuration Data', 'standard' => 'Green'],
                            // Sub-section header (Extractor FCOMP group)
                            ['item_number' => '', 'item_name' => '* Extractor (FCOMP)','standard' => null, 'is_subheader' => true],
                            ['item_number' => '', 'item_name' => 'PPS Pulse',          'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Data Validity',      'standard' => 'Green'],
                        ],
                    ],
                    [
                        'number' => 3,
                        'name'   => 'AILAN CH.A/CH.B',
                        'items'  => [
                            ['item_number' => '', 'item_name' => '* Status',                'standard' => null, 'is_subheader' => true],
                            ['item_number' => '', 'item_name' => 'LO 2',                    'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'LO 1',                    'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Receiver Supply 18 V',    'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Receiver Supply 12 V',    'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Max Deviation',           'standard' => '+- 0'],
                            ['item_number' => '', 'item_name' => 'Min Deviation',           'standard' => '+- 0'],
                            ['item_number' => '', 'item_name' => 'Time of Revolution (s)',  'standard' => '5,6'],
                            ['item_number' => '', 'item_name' => 'All Indicators (OK / Not OK)', 'standard' => 'OK / Green'],
                            ['item_number' => '', 'item_name' => 'Rectification',           'standard' => null],
                        ],
                    ],
                    [
                        'number' => 4,
                        'name'   => 'RADAR CONTROL',
                        'items'  => [
                            // Antenna: bukan subheader — item editable dengan input RPM TX I dan TX II.
                            // Standard "10 RPM / 15 RPM" adalah nilai nominal; user mengisi RPM aktual.
                            ['item_number' => '', 'item_name' => 'Antenna',              'standard' => '10 RPM / 15 RPM'],
                            ['item_number' => '', 'item_name' => 'Power Supply',            'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Remote Control',          'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Motor 1',                 'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Motor 2',                 'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Antenna Start',           'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Temperatur 60°C',         'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Temperatur 5°C',          'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Open Door',               'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Rotation Change',         'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Polarisation Change',     'standard' => 'Green'],
                            // RADAR sub-group
                            ['item_number' => '', 'item_name' => '* RADAR',                 'standard' => null, 'is_subheader' => true],
                            ['item_number' => '', 'item_name' => 'Power Supply',            'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Tx Power Supply',         'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Tx Over Voltage',         'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Smoke Detector',          'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Temperature Range',       'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Obstacle Light',          'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Sw Board Over Voltage',   'standard' => 'Green'],
                            ['item_number' => '', 'item_name' => 'Transmit Fan',            'standard' => 'Green'],
                        ],
                    ],
                ],
            ],

            // ───────────────────────────────────────────────────────────
            // SECTION B — LINGKUNGAN KERJA
            // Inputs: STANDART | HASIL | KETERANGAN (single-result column)
            // ───────────────────────────────────────────────────────────
            [
                'code'            => 'B',
                'name'            => 'LINGKUNGAN KERJA',
                'inputs_layout'   => 'environment',  // FE renders single HASIL column
                'columns_label_1' => 'HASIL',
                'columns_label_2' => null,
                'groups' => [
                    [
                        'number' => null,
                        'name'   => null,
                        'items'  => [
                            ['item_number' => '1', 'item_name' => 'Pemeriksaan suhu Ruangan',     'standard' => 'Max 22°C'],
                            ['item_number' => '2', 'item_name' => 'Pemeriksaan Air Humidity',     'standard' => '√'],
                            ['item_number' => '3', 'item_name' => 'Pemeriksaan Kebersihan Ruangan','standard' => '√'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Flatten the structured template into row inserts for cnsd_radar_meter_items.
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
                        'radar_meter_record_id' => $recordId,
                        'section_code'          => $section['code'],
                        'section_name'          => $section['name'],
                        'group_number'          => $group['number'] ?? null,
                        'group_name'            => $group['name']   ?? null,
                        'item_number'           => $item['item_number'] ?? null,
                        'item_name'             => $item['item_name'],
                        'standard'              => $item['standard']    ?? null,
                        'kondisi_teknis_tx1'    => null,
                        'kondisi_teknis_tx2'    => null,
                        'hasil'                 => null,
                        'keterangan'            => null,
                        'sort_order'            => $sortOrder++,
                        'created_at'            => $now,
                        'updated_at'            => $now,
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
                'inputs_layout'   => $section['inputs_layout'] ?? 'tx_dual',
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
