<?php

namespace App\Models\Cnsd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CnsdAtisMeterTechnician extends Model
{
    protected $table = 'cnsd_atis_meter_technicians';

    protected $fillable = [
        'atis_meter_record_id', 'technician_id', 'technician_name',
        'technician_signature', 'technician_signed_by', 'technician_signed_at', 'sort_order',
    ];

    protected $casts = ['technician_signed_at' => 'datetime'];

    public function record(): BelongsTo
    {
        return $this->belongsTo(CnsdAtisMeterRecord::class, 'atis_meter_record_id');
    }
}
