<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TfpAobGroundItem — one measurement parameter row for a TFP AOB Ground record.
 *
 * Cell values live in `values` JSON, keyed by composite "panelId.subKey"
 * (e.g. "panel_cos_a03.input"). The panel structure is defined per record
 * via tfp_aob_ground_records.columns_config.
 *
 * `is_disabled_map` and `merge_map` use the same composite key:
 *   - is_disabled_map: {"panel_cos_a03.input": true}     → cell is greyed out
 *   - merge_map:       {"panel_cos_a03.input": 2}        → cell spans 2 columns
 */
class TfpAobGroundItem extends Model
{
    protected $table = 'tfp_aob_ground_items';

    protected $fillable = [
        'aob_ground_record_id',
        'parameter_number',
        'parameter_name',
        'unit',
        'values',
        'is_disabled_map',
        'merge_map',
        'sort_order',
    ];

    protected $casts = [
        'values'          => 'array',
        'is_disabled_map' => 'array',
        'merge_map'       => 'array',
        'sort_order'      => 'integer',
    ];

    // ─── Relationships ─────────────────────────────────────────

    public function record(): BelongsTo
    {
        return $this->belongsTo(TfpAobGroundRecord::class, 'aob_ground_record_id');
    }
}
