<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TfpTowerItem — one measurement parameter row for a TFP Tower record.
 *
 * Cell values live in `values` JSON, keyed by composite "panelId.subKey"
 * (e.g. "panel_ats_a13.input"). Panel structure is defined per record via
 * tfp_tower_records.columns_config.
 */
class TfpTowerItem extends Model
{
    protected $table = 'tfp_tower_items';

    protected $fillable = [
        'tower_record_id',
        'parameter_number', 'parameter_name', 'unit',
        'values', 'is_disabled_map', 'merge_map',
        'sort_order',
    ];

    protected $casts = [
        'values'          => 'array',
        'is_disabled_map' => 'array',
        'merge_map'       => 'array',
        'sort_order'      => 'integer',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(TfpTowerRecord::class, 'tower_record_id');
    }
}
