<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TfpAobLt12Item — one measurement parameter row for a TFP AOB Lantai 1 & 2 record.
 *
 * Columns map to the physical form columns (6 panels, each single value):
 *   - panel_a05_app_room   → Panel A 05 APP Room
 *   - panel_a06_app_room   → Panel A 06 APP Room
 *   - panel_a07_app_room   → Panel A 07 APP Room
 *   - panel_a08_gudang_lt1 → Panel A 08 Gudang Lt 1
 *   - panel_a22_gudang_lt1 → Panel A 22 Gudang Lt 1
 *   - panel_a09_amsc_room  → Panel A 09 AMSC Room
 *
 * is_disabled_map marks which columns are greyed-out for this parameter.
 */
class TfpAobLt12Item extends Model
{
    protected $table = 'tfp_aob_lt12_items';

    protected $fillable = [
        'aob_lt12_record_id',
        'parameter_number',
        'parameter_name',
        'unit',
        'panel_a05_app_room',
        'panel_a06_app_room',
        'panel_a07_app_room',
        'panel_a08_gudang_lt1',
        'panel_a22_gudang_lt1',
        'panel_a09_amsc_room',
        'is_disabled_map',
        'sort_order',
    ];

    protected $casts = [
        'is_disabled_map' => 'array',
        'sort_order'      => 'integer',
    ];

    // ─── Relationships ─────────────────────────────────────────

    public function record(): BelongsTo
    {
        return $this->belongsTo(TfpAobLt12Record::class, 'aob_lt12_record_id');
    }
}
