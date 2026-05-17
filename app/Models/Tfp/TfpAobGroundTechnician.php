<?php

namespace App\Models\Tfp;

use App\Models\LocalUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TfpAobGroundTechnician — one row per TFP technician on duty for a given
 * AOB Ground record. Technician signatures live here.
 */
class TfpAobGroundTechnician extends Model
{
    protected $table = 'tfp_aob_ground_technicians';

    protected $fillable = [
        'aob_ground_record_id',
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

    // ─── Relationships ─────────────────────────────────────────

    public function record(): BelongsTo
    {
        return $this->belongsTo(TfpAobGroundRecord::class, 'aob_ground_record_id');
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
