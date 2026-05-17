<?php

namespace App\Models\Cnsd;

use App\Models\LocalUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CnsdRadarMeterTechnician — one row per CNSD technician on duty for the
 * Radar Meter Reading record.
 *
 * Each row carries its own immutable base64 PNG signature. A technician can
 * only sign their own row (validated by the service via name + ID match).
 */
class CnsdRadarMeterTechnician extends Model
{
    protected $table = 'cnsd_radar_meter_technicians';

    protected $fillable = [
        'radar_meter_record_id',
        'technician_id',
        'technician_name',
        'technician_signature',
        'technician_signed_by',
        'technician_signed_at',
        'sort_order',
    ];

    protected $casts = [
        'technician_signed_at' => 'datetime',
        'sort_order'           => 'integer',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(CnsdRadarMeterRecord::class, 'radar_meter_record_id');
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
