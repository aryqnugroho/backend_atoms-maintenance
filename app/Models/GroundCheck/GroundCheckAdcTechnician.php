<?php

namespace App\Models\GroundCheck;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroundCheckAdcTechnician extends Model
{
    protected $table = 'ground_check_adc_technicians';

    protected $fillable = [
        'ground_check_adc_record_id',
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
        return $this->belongsTo(GroundCheckAdcRecord::class, 'ground_check_adc_record_id');
    }
}
