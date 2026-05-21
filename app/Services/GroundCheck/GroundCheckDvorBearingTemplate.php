<?php

namespace App\Services\GroundCheck;

/**
 * GroundCheckDvorBearingTemplate — Form 1 "Ground Check VOR" bearing table.
 *
 * 25 fixed bearings (0°, 15°, 30°, ..., 360°). Each row records:
 *   - Reading (manual, signed when bearing > 180°)
 *   - Error   (auto: bearing - reading_unwrapped)
 *   - Value   (manual, free text — the empty 4th sub-column on paper form)
 *
 * Reading & Value start null. Teknisi enters Reading; the service / frontend
 * derives Error live on input. Aggregate stats (Min / Max / Spread / Differential)
 * are not stored — they are computed by the frontend on render.
 */
class GroundCheckDvorBearingTemplate
{
    public const BEARINGS = [
        0, 15, 30, 45, 60, 75, 90, 105, 120, 135, 150, 165, 180,
        195, 210, 225, 240, 255, 270, 285, 300, 315, 330, 345, 360,
    ];

    public static function bearings(): array
    {
        return self::BEARINGS;
    }

    public static function buildRows(int $recordId): array
    {
        $rows = [];
        $sort = 0;
        $now = now()->toDateTimeString();

        foreach (self::BEARINGS as $bearing) {
            $rows[] = [
                'ground_check_dvor_record_id' => $recordId,
                'bearing'     => $bearing,
                'tx1_reading' => null,
                'tx1_error'   => null,
                'tx1_value'   => null,
                'tx2_reading' => null,
                'tx2_error'   => null,
                'tx2_value'   => null,
                'sort_order'  => $sort++,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        return $rows;
    }
}
