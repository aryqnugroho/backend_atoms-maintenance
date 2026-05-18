<?php

namespace App\Models\Tfp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TfpTransmitterTxFacility extends Model
{
    protected $table = 'tfp_transmitter_tx_facilities';

    protected $fillable = [
        'tx_record_id', 'facility_name', 'kondisi', 'keterangan', 'sort_order',
    ];

    protected $casts = ['sort_order' => 'integer'];

    public function record(): BelongsTo
    {
        return $this->belongsTo(TfpTransmitterTxRecord::class, 'tx_record_id');
    }
}
