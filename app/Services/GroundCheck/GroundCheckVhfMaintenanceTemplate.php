<?php

namespace App\Services\GroundCheck;

/**
 * GroundCheckVhfMaintenanceTemplate — source of truth for VHF Ground Check
 * Form 2: "Pelaksanaan Kegiatan Pemeliharaan Pencegahan".
 *
 * Hierarchical layout:
 *   1. Membersihkan bagian dalam / modul peralatan            [section header]
 *      a. Transmitter           — Toleransi: Bersih           [text]
 *      b. Receiver              — Toleransi: Bersih           [text]
 *      c. Change Over Unit      — Toleransi: Bersih           [text]
 *      d. Remote Control Unit   — Toleransi: Bersih           [text]
 *   2. Melaksanakan pengukuran parameter peralatan menggunakan alat ukur
 *      a. Transmitter                                         [subsection header]
 *         Forward Power Output  — Interface: WATT METER       [numeric]
 *         Reverse Power Output  — Interface: WATT METER       [numeric]
 *         VSWR                                                [numeric]
 *      b. Receiver                                            [subsection header]
 *         AF Output                                           [numeric]
 *   3. Melaksanakan Groundcheck peralatan sesuai SKEP/83/V/2005
 *      — Toleransi: Sudah, Keterangan: Form Terpisah          [text]
 *   4. Memeriksa input tegangan ke peralatan
 *      a. AC                                                  [text]
 *      b. DC                                                  [text]
 */
class GroundCheckVhfMaintenanceTemplate
{
    public static function items(): array
    {
        return [
            // ─── 1. Membersihkan ────────────────────────────────────
            [
                'section_number' => 1,
                'section_label' => 'Membersihkan bagian dalam / modul peralatan',
                'subsection_label' => null,
                'item_code' => null,
                'parameter_name' => '1. Membersihkan bagian dalam / modul peralatan',
                'toleransi' => null,
                'interface_value' => null,
                'is_section_header' => true,
                'is_subsection_header' => false,
                'input_type' => 'header',
            ],
            ['section_number' => 1, 'section_label' => 'Membersihkan bagian dalam / modul peralatan', 'subsection_label' => null, 'item_code' => 'a', 'parameter_name' => 'Transmitter',         'toleransi' => 'Bersih', 'interface_value' => null, 'is_section_header' => false, 'is_subsection_header' => false, 'input_type' => 'text'],
            ['section_number' => 1, 'section_label' => 'Membersihkan bagian dalam / modul peralatan', 'subsection_label' => null, 'item_code' => 'b', 'parameter_name' => 'Receiver',            'toleransi' => 'Bersih', 'interface_value' => null, 'is_section_header' => false, 'is_subsection_header' => false, 'input_type' => 'text'],
            ['section_number' => 1, 'section_label' => 'Membersihkan bagian dalam / modul peralatan', 'subsection_label' => null, 'item_code' => 'c', 'parameter_name' => 'Change Over Unit',    'toleransi' => 'Bersih', 'interface_value' => null, 'is_section_header' => false, 'is_subsection_header' => false, 'input_type' => 'text'],
            ['section_number' => 1, 'section_label' => 'Membersihkan bagian dalam / modul peralatan', 'subsection_label' => null, 'item_code' => 'd', 'parameter_name' => 'Remote Control Unit', 'toleransi' => 'Bersih', 'interface_value' => null, 'is_section_header' => false, 'is_subsection_header' => false, 'input_type' => 'text'],

            // ─── 2. Pengukuran parameter ────────────────────────────
            [
                'section_number' => 2,
                'section_label' => 'Melaksanakan pengukuran parameter peralatan menggunakan alat ukur',
                'subsection_label' => null,
                'item_code' => null,
                'parameter_name' => '2. Melaksanakan pengukuran parameter peralatan menggunakan alat ukur',
                'toleransi' => null,
                'interface_value' => null,
                'is_section_header' => true,
                'is_subsection_header' => false,
                'input_type' => 'header',
            ],
            [
                'section_number' => 2,
                'section_label' => 'Melaksanakan pengukuran parameter peralatan menggunakan alat ukur',
                'subsection_label' => 'Transmitter',
                'item_code' => 'a',
                'parameter_name' => 'a. Transmitter',
                'toleransi' => null,
                'interface_value' => null,
                'is_section_header' => false,
                'is_subsection_header' => true,
                'input_type' => 'header',
            ],
            ['section_number' => 2, 'section_label' => 'Melaksanakan pengukuran parameter peralatan menggunakan alat ukur', 'subsection_label' => 'Transmitter', 'item_code' => null, 'parameter_name' => 'Forward Power Output', 'toleransi' => null, 'interface_value' => 'WATT METER', 'is_section_header' => false, 'is_subsection_header' => false, 'input_type' => 'numeric'],
            ['section_number' => 2, 'section_label' => 'Melaksanakan pengukuran parameter peralatan menggunakan alat ukur', 'subsection_label' => 'Transmitter', 'item_code' => null, 'parameter_name' => 'Reverse Power Output', 'toleransi' => null, 'interface_value' => 'WATT METER', 'is_section_header' => false, 'is_subsection_header' => false, 'input_type' => 'numeric'],
            ['section_number' => 2, 'section_label' => 'Melaksanakan pengukuran parameter peralatan menggunakan alat ukur', 'subsection_label' => 'Transmitter', 'item_code' => null, 'parameter_name' => 'VSWR',                 'toleransi' => null, 'interface_value' => null,         'is_section_header' => false, 'is_subsection_header' => false, 'input_type' => 'numeric'],

            [
                'section_number' => 2,
                'section_label' => 'Melaksanakan pengukuran parameter peralatan menggunakan alat ukur',
                'subsection_label' => 'Receiver',
                'item_code' => 'b',
                'parameter_name' => 'b. Receiver',
                'toleransi' => null,
                'interface_value' => null,
                'is_section_header' => false,
                'is_subsection_header' => true,
                'input_type' => 'header',
            ],
            ['section_number' => 2, 'section_label' => 'Melaksanakan pengukuran parameter peralatan menggunakan alat ukur', 'subsection_label' => 'Receiver',    'item_code' => null, 'parameter_name' => 'AF Output',            'toleransi' => null, 'interface_value' => null,         'is_section_header' => false, 'is_subsection_header' => false, 'input_type' => 'numeric'],

            // ─── 3. Groundcheck SKEP ────────────────────────────────
            [
                'section_number' => 3,
                'section_label' => 'Melaksanakan Groundcheck peralatan sesuai SKEP/83/V/2005',
                'subsection_label' => null,
                'item_code' => null,
                'parameter_name' => '3. Melaksanakan Groundcheck peralatan sesuai SKEP/83/V/2005',
                'toleransi' => 'Sudah',
                'interface_value' => null,
                'is_section_header' => false,
                'is_subsection_header' => false,
                'input_type' => 'text',
            ],

            // ─── 4. Memeriksa input tegangan ────────────────────────
            [
                'section_number' => 4,
                'section_label' => 'Memeriksa input tegangan ke peralatan',
                'subsection_label' => null,
                'item_code' => null,
                'parameter_name' => '4. Memeriksa input tegangan ke peralatan',
                'toleransi' => null,
                'interface_value' => null,
                'is_section_header' => true,
                'is_subsection_header' => false,
                'input_type' => 'header',
            ],
            ['section_number' => 4, 'section_label' => 'Memeriksa input tegangan ke peralatan', 'subsection_label' => null, 'item_code' => 'a', 'parameter_name' => 'AC', 'toleransi' => null, 'interface_value' => null, 'is_section_header' => false, 'is_subsection_header' => false, 'input_type' => 'text'],
            ['section_number' => 4, 'section_label' => 'Memeriksa input tegangan ke peralatan', 'subsection_label' => null, 'item_code' => 'b', 'parameter_name' => 'DC', 'toleransi' => null, 'interface_value' => null, 'is_section_header' => false, 'is_subsection_header' => false, 'input_type' => 'text'],
        ];
    }

    public static function buildItemRows(int $recordId): array
    {
        $rows = [];
        $sort = 0;
        $now = now()->toDateTimeString();

        foreach (self::items() as $item) {
            $rows[] = [
                'ground_check_vhf_record_id' => $recordId,
                'section_number'       => $item['section_number'],
                'section_label'        => $item['section_label'],
                'subsection_label'     => $item['subsection_label'],
                'item_code'            => $item['item_code'],
                'parameter_name'       => $item['parameter_name'],
                'toleransi'            => $item['toleransi'],
                'interface_value'      => $item['interface_value'],
                'tx1_value'            => null,
                'tx2_value'            => null,
                'keterangan'           => null,
                'is_section_header'    => $item['is_section_header'],
                'is_subsection_header' => $item['is_subsection_header'],
                'input_type'           => $item['input_type'] ?? 'text',
                'sort_order'           => $sort++,
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }

        return $rows;
    }
}
