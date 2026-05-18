<?php

namespace App\Models\Cnsd;

use App\Models\LocalUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CnsdAmscMeterTechnician — per-row technician snapshot + signature
 * for CNSD AMSC Meter Reading forms.
 */
class CnsdAmscMeterTechnician extends Model
{
    protected $table = 'cnsd_amsc_meter_technicians';

    protected $fillable = [
        'amsc_meter_record_id',
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
        return $this->belongsTo(CnsdAmscMeterRecord::class, 'amsc_meter_record_id');
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
