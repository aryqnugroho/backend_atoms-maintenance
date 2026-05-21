<?php

namespace App\Services\GroundCheck;

/**
 * GroundCheckLlzCurveTemplate — Form 2 "Groundcheck Performance Curve".
 *
 * 17 fixed measurement points along the localizer beam:
 *   - 8 rows on 90 Hz SIDE (35° → 1.85°)
 *   - 1 row on centerline (0°)
 *   - 8 rows on 150 Hz SIDE (1.85° → 35°)
 *
 * Each row records 6 TX1 + 6 TX2 measurements (DDM %, DDM µA, SUM %,
 * MOD 90 Hz %, MOD 150 Hz %, RF LEVEL dB).
 *
 * Measurement points are standard ICAO LOC field-strength angles.
 * Teknisi only fills the TX1 / TX2 numeric columns.
 */
class GroundCheckLlzCurveTemplate
{
    /** Each tuple: [side, jarak_m, degrees] */
    private const POINTS = [
        // 90 HZ SIDE (descending degrees)
        ['90hz',   210.1, 35.00],
        ['90hz',   173.2, 30.00],
        ['90hz',   139.9, 25.00],
        ['90hz',   109.2, 20.00],
        ['90hz',    80.4, 15.00],
        ['90hz',    52.9, 10.00],
        ['90hz',    26.2,  5.00],
        ['90hz',     9.7,  1.85],

        // CENTERLINE
        ['center',   0.0,  0.00],

        // 150 HZ SIDE (ascending degrees)
        ['150hz',    9.7,  1.85],
        ['150hz',   26.2,  5.00],
        ['150hz',   52.9, 10.00],
        ['150hz',   80.4, 15.00],
        ['150hz',  109.2, 20.00],
        ['150hz',  139.9, 25.00],
        ['150hz',  173.2, 30.00],
        ['150hz',  210.1, 35.00],
    ];

    public static function points(): array
    {
        return self::POINTS;
    }

    public static function buildRows(int $recordId): array
    {
        $rows = [];
        $sort = 0;
        $now = now()->toDateTimeString();

        foreach (self::POINTS as [$side, $jarakM, $degrees]) {
            $rows[] = [
                'ground_check_llz_record_id' => $recordId,
                'side'            => $side,
                'jarak_m'         => $jarakM,
                'degrees'         => $degrees,
                'tx1_ddm_pct'     => null,
                'tx1_ddm_ua'      => null,
                'tx1_sum_pct'     => null,
                'tx1_mod_90hz'    => null,
                'tx1_mod_150hz'   => null,
                'tx1_rf_level_db' => null,
                'tx2_ddm_pct'     => null,
                'tx2_ddm_ua'      => null,
                'tx2_sum_pct'     => null,
                'tx2_mod_90hz'    => null,
                'tx2_mod_150hz'   => null,
                'tx2_rf_level_db' => null,
                'sort_order'      => $sort++,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        return $rows;
    }
}
