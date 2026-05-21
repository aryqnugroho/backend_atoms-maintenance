<?php

namespace App\Models\GroundCheck;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroundCheckDvorTechnician extends Model
{
    protected $table = 'ground_check_dvor_technicians';

    protected $fillable = [
        'ground_check_dvor_record_id',
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
        return $this->belongsTo(GroundCheckDvorRecord::class, 'ground_check_dvor_record_id');
    }
}
