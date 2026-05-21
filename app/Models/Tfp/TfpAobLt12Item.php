<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TfpAobLt12Item — one measurement parameter row for a TFP AOB Lantai 1 & 2 record.
 *
 * Cell values live in `values` JSON, keyed by composite "panelId.subKey"
 * (e.g. "panel_a05_app_room.value"). Panel structure is defined per record
 * via tfp_aob_lt12_records.columns_config.
 */
class TfpAobLt12Item extends Model
{
    protected $table = 'tfp_aob_lt12_items';

    protected $fillable = [
        'aob_lt12_record_id',
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

    public function record(): BelongsTo
    {
        return $this->belongsTo(TfpAobLt12Record::class, 'aob_lt12_record_id');
    }
}
