<?php

namespace App\Models\Cnsd;

use App\Models\LocalUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CnsdTransmitterMeterTechnician — per-row technician snapshot + signature.
 */
class CnsdTransmitterMeterTechnician extends Model
{
    protected $table = 'cnsd_transmitter_meter_technicians';

    protected $fillable = [
        'transmitter_meter_record_id',
        'technician_id',
        'technician_name',
        'technician_signature',
        'technician_signed_by',
        'technician_signed_at',
        'sort_order',
    ];

    protected $casts = [
        'technician_signed_at' => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(CnsdTransmitterMeterRecord::class, 'transmitter_meter_record_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'technician_id');
    }
}
