<?php

namespace App\Services\Cnsd;

/**
 * CnsdEq1Template — canonical EQ-1 item list, mirrored from the official
 * "Kesiapan Peralatan" form (FORM EQ-1) used by AirNav Surabaya CNSD.
 *
 * The backend uses this template at create-time to seed cnsd_readiness_items
 * for every new EQ-1 record. The user only fills in status, kondisi
 * operasional, and keterangan — they never define items themselves.
 *
 * Section column headers are tracked via columns_label_1 / columns_label_2
 * so the frontend can render the right header per section without hardcoding.
 *
 * Note: Additional CNSD form types (RADAR, RECORDER, AMSC, etc.) have since
 * been implemented as separate templates (CnsdRadarMeterTemplate, etc.) with
 * their own services. No shared registry — each module owns its own template.
 */
class CnsdEq1Template
{
    /**
     * Section + item structure for Form EQ-1.
     *
     * Item shape:
     *   - item_number       : visible numbering (matches paper form)
     *   - equipment_name    : main row
     *   - sub_equipment_name: optional PRIMARY / SECONDARY subrow
     *   - kondisi_operasional_1 / _2 : *placeholder/default* values
     *     (frequencies for RADIO section; null elsewhere — user fills these in)
     */
    public static function sections(): array
    {
        return [
            // ───────────────────────────────────────────────────────────
            [
                'name'             => 'KOMUNIKASI PENERBANGAN',
                'columns_label_1'  => 'SERVER AKTIF',
                'columns_label_2'  => 'DUAL STATE',
                'items' => [
                    ['item_number' => '1', 'equipment_name' => 'VCCS MERA FREQUENTIS'],
                    ['item_number' => '2', 'equipment_name' => 'VOICE RECORDER'],
                    ['item_number' => '3', 'equipment_name' => 'AMSC'],
                    ['item_number' => '4', 'equipment_name' => 'ATIS'],
                ],
            ],

            // ───────────────────────────────────────────────────────────
            [
                'name'             => 'RADIO',
                'columns_label_1'  => 'DUAL STATUS',
                'columns_label_2'  => 'FREQUENCY',
                'items' => [
                    ['item_number' => '1', 'equipment_name' => 'CDU',           'sub_equipment_name' => 'PRIMARY',   'kondisi_operasional_2' => '121,65 MHz'],
                    ['item_number' => '1', 'equipment_name' => 'CDU',           'sub_equipment_name' => 'SECONDARY', 'kondisi_operasional_2' => '121,80 MHz'],
                    ['item_number' => '2', 'equipment_name' => 'GMC',           'sub_equipment_name' => 'PRIMARY',   'kondisi_operasional_2' => '118,90 MHz'],
                    ['item_number' => '2', 'equipment_name' => 'GMC',           'sub_equipment_name' => 'SECONDARY', 'kondisi_operasional_2' => '119,15 MHz'],
                    ['item_number' => '3', 'equipment_name' => 'ADC',           'sub_equipment_name' => 'PRIMARY',   'kondisi_operasional_2' => '118,30 MHz'],
                    ['item_number' => '3', 'equipment_name' => 'ADC',           'sub_equipment_name' => 'SECONDARY', 'kondisi_operasional_2' => '118,10 MHz'],
                    ['item_number' => '4', 'equipment_name' => 'APP DIRECTOR',  'sub_equipment_name' => 'PRIMARY',   'kondisi_operasional_2' => '123,20 MHz'],
                    ['item_number' => '4', 'equipment_name' => 'APP DIRECTOR',  'sub_equipment_name' => 'SECONDARY', 'kondisi_operasional_2' => '124,50 MHz'],
                    ['item_number' => '5', 'equipment_name' => 'APP WEST',      'sub_equipment_name' => 'PRIMARY',   'kondisi_operasional_2' => '125,10 MHz'],
                    ['item_number' => '5', 'equipment_name' => 'APP WEST',      'sub_equipment_name' => 'SECONDARY', 'kondisi_operasional_2' => '123,55 MHz'],
                    ['item_number' => '6', 'equipment_name' => 'APP EAST',      'sub_equipment_name' => 'PRIMARY',   'kondisi_operasional_2' => '124,00 MHz'],
                    ['item_number' => '6', 'equipment_name' => 'APP EAST',      'sub_equipment_name' => 'SECONDARY', 'kondisi_operasional_2' => '122,85 MHz'],
                    ['item_number' => '7', 'equipment_name' => 'EMERGENCY',                                               'kondisi_operasional_2' => '121,50 MHz'],
                    ['item_number' => '8', 'equipment_name' => 'VHF ER UPKN',   'sub_equipment_name' => 'PRIMARY',   'kondisi_operasional_2' => '134,10 MHz'],
                    ['item_number' => '8', 'equipment_name' => 'VHF ER UPKN',   'sub_equipment_name' => 'SECONDARY', 'kondisi_operasional_2' => '133,60 MHz'],
                    ['item_number' => '9', 'equipment_name' => 'VHF ER UBLI',                                             'kondisi_operasional_2' => '120,70 MHz'],
                    ['item_number' => '10','equipment_name' => 'VHF ER USBY',   'sub_equipment_name' => 'PRIMARY',   'kondisi_operasional_2' => '123,90 MHz'],
                    ['item_number' => '10','equipment_name' => 'VHF ER USBY',   'sub_equipment_name' => 'SECONDARY', 'kondisi_operasional_2' => '125,90 MHz'],
                    ['item_number' => '11','equipment_name' => 'VHF ER BLORA',  'sub_equipment_name' => 'PRIMARY',   'kondisi_operasional_2' => '125,10 MHz'],
                    ['item_number' => '11','equipment_name' => 'VHF ER BLORA',  'sub_equipment_name' => 'SECONDARY', 'kondisi_operasional_2' => '123,55 MHz'],
                ],
            ],

            // ───────────────────────────────────────────────────────────
            [
                'name'             => 'NAVIGASI PENERBANGAN',
                'columns_label_1'  => 'TX OPERASI',
                'columns_label_2'  => 'DUAL STATE',
                'items' => [
                    ['item_number' => '1', 'equipment_name' => 'LOCALIZER'],
                    ['item_number' => '2', 'equipment_name' => 'GLIDE PATH'],
                    ['item_number' => '3', 'equipment_name' => 'TDME'],
                    ['item_number' => '4', 'equipment_name' => 'DVOR'],
                    ['item_number' => '5', 'equipment_name' => 'DME'],
                ],
            ],

            // ───────────────────────────────────────────────────────────
            [
                'name'             => 'PENGAMATAN PENERBANGAN',
                'columns_label_1'  => 'CHANNEL AKTIF',
                'columns_label_2'  => 'DUAL STATE',
                'items' => [
                    ['item_number' => '1', 'equipment_name' => 'PSR'],
                    ['item_number' => '2', 'equipment_name' => 'MSSR'],
                    ['item_number' => '3', 'equipment_name' => 'ADSB STATUS'],
                    ['item_number' => '4', 'equipment_name' => 'MLAT'],
                ],
            ],

            // ───────────────────────────────────────────────────────────
            [
                'name'             => 'AUTOMASI PENERBANGAN',
                'columns_label_1'  => 'SERVER STATE',
                'columns_label_2'  => 'WORKSTATION STATE',
                'items' => [
                    ['item_number' => '1', 'equipment_name' => 'ATC SYSTEM TERN'],
                    ['item_number' => '2', 'equipment_name' => 'ATC SYSTEM NOW'],
                    ['item_number' => '3', 'equipment_name' => 'ASMGS'],
                ],
            ],
        ];
    }

    /**
     * Flatten the structured template into row inserts for cnsd_readiness_items.
     */
    public static function buildItemRows(int $recordId): array
    {
        $rows = [];
        $sortOrder = 0;
        $now = now();

        foreach (self::sections() as $section) {
            foreach ($section['items'] as $item) {
                $rows[] = [
                    'readiness_record_id'    => $recordId,
                    'section_name'           => $section['name'],
                    'item_number'            => $item['item_number']         ?? null,
                    'equipment_name'         => $item['equipment_name'],
                    'sub_equipment_name'     => $item['sub_equipment_name']  ?? null,
                    'status_peralatan'       => $item['status_peralatan']    ?? null,
                    'kondisi_operasional_1'  => $item['kondisi_operasional_1'] ?? null,
                    'kondisi_operasional_2'  => $item['kondisi_operasional_2'] ?? null,
                    'keterangan'             => $item['keterangan']         ?? null,
                    'sort_order'             => $sortOrder++,
                    'created_at'             => $now,
                    'updated_at'             => $now,
                ];
            }
        }

        return $rows;
    }

    /**
     * Section metadata (without items) — used by the frontend to render section
     * tabs/accordions and the right column headers.
     */
    public static function sectionMeta(): array
    {
        return array_map(static function ($section) {
            return [
                'name'            => $section['name'],
                'columns_label_1' => $section['columns_label_1'] ?? null,
                'columns_label_2' => $section['columns_label_2'] ?? null,
            ];
        }, self::sections());
    }
}
