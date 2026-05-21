<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TfpTransmitterTxItem — one measurement parameter row for a TFP Transmitter TX record.
 *
 * Cell values live in `values` JSON, keyed by composite "panelId.subKey"
 * (e.g. "panel_cos_tx03.input"). Panel structure is defined per record via
 * tfp_transmitter_tx_records.columns_config.
 */
class TfpTransmitterTxItem extends Model
{
    protected $table = 'tfp_transmitter_tx_items';

    protected $fillable = [
        'tx_record_id',
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
        return $this->belongsTo(TfpTransmitterTxRecord::class, 'tx_record_id');
    }
}
