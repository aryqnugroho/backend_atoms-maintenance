<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TfpTransmitterTxItem extends Model
{
    protected $table = 'tfp_transmitter_tx_items';

    protected $fillable = [
        'tx_record_id', 'parameter_number', 'parameter_name', 'unit',
        'panel_tx01', 'panel_tx02',
        'panel_cos_tx03_input', 'panel_cos_tx03_output',
        'panel_output_ups_tx04',
        'panel_ups_tx07_input', 'panel_ups_tx07_output',
        'panel_ac_tx06',
        'ups_piller_input', 'ups_piller_output',
        'panel_milat_ru11',
        'is_disabled_map', 'sort_order',
    ];

    protected $casts = [
        'is_disabled_map' => 'array',
        'sort_order'      => 'integer',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(TfpTransmitterTxRecord::class, 'tx_record_id');
    }
}
