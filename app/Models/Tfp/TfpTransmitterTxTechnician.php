<?php

namespace App\Models\Tfp;

use App\Models\LocalUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TfpTransmitterTxTechnician extends Model
{
    protected $table = 'tfp_transmitter_tx_technicians';

    protected $fillable = [
        'tx_record_id', 'technician_id', 'technician_name',
        'technician_signature', 'technician_signed_by', 'technician_signed_at', 'sort_order',
    ];

    protected $casts = [
        'technician_signed_at' => 'datetime',
        'sort_order'           => 'integer',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(TfpTransmitterTxRecord::class, 'tx_record_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'technician_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'technician_signed_by');
    }
}
