<?php

namespace App\Services\Grounding;

class GroundingReportTemplate
{
    public static function sections(): array
    {
        return [
            [
                'name' => 'VISUAL',
                'items' => [
                    ['item_number' => 1, 'item_name' => 'Terminal Udara', 'standard' => null],
                    ['item_number' => 2, 'item_name' => 'Konduktor Turun', 'standard' => null],
                    ['item_number' => 3, 'item_name' => 'Modul Penangkal Petir', 'standard' => null],
                    ['item_number' => 4, 'item_name' => 'Sambungan dan Clamp', 'standard' => null],
                    ['item_number' => 5, 'item_name' => 'Kabel Pembumian', 'standard' => null],
                    ['item_number' => 6, 'item_name' => 'Lightning Counter', 'standard' => null],
                ],
            ],
            [
                'name' => 'PENGUKURAN',
                'items' => [
                    ['item_number' => 1, 'item_name' => 'Nilai Tahanan Tahanan Tanah', 'standard' => '≤ 1 Ω'],
                    ['item_number' => 2, 'item_name' => 'Nilai Tahanan Pentanahan Peralatan', 'standard' => '≤ 1 Ω'],
                    ['item_number' => 3, 'item_name' => 'Uji Kontinuitas Konduktor Turun dan Kabel Pentanahan', 'standard' => '-'],
                ],
            ],
        ];
    }

    public static function buildItemRows(int $recordId): array
    {
        $rows = [];
        $sortOrder = 0;
        $now = now();
        foreach (self::sections() as $section) {
            foreach ($section['items'] as $item) {
                $rows[] = [
                    'grounding_report_record_id' => $recordId,
                    'section_name'  => $section['name'],
                    'item_number'   => $item['item_number'],
                    'item_name'     => $item['item_name'],
                    'standard'      => $item['standard'],
                    'availability'  => null,
                    'condition'     => null,
                    'notes'         => null,
                    'sort_order'    => $sortOrder++,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }
        }
        return $rows;
    }
}
