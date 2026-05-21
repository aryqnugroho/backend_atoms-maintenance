<?php

namespace App\Models\Cnsd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CnsdDmeMeterTechnician extends Model
{
    protected $table = 'cnsd_dme_meter_technicians';

    protected $fillable = [
        'dme_meter_record_id', 'technician_id', 'technician_name',
        'technician_signature', 'technician_signed_by', 'technician_signed_at', 'sort_order',
    ];

    protected $casts = ['technician_signed_at' => 'datetime'];

    public function record(): BelongsTo
    {
        return $this->belongsTo(CnsdDmeMeterRecord::class, 'dme_meter_record_id');
    }
}
