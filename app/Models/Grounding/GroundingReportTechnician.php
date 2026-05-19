<?php

namespace App\Models\Grounding;

use App\Models\LocalUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GroundingReportTechnician — one row per TFP technician on duty for a given
 * Grounding Report record. Technician signatures live here.
 */
class GroundingReportTechnician extends Model
{
    protected $table = 'grounding_report_technicians';

    protected $fillable = [
        'grounding_report_record_id',
        'technician_id',
        'technician_name',
        'technician_signature',
        'technician_signed_by',
        'technician_signed_at',
        'technician_signed_by_name',
        'technician_signed_by_role',
        'sort_order',
    ];

    protected $casts = [
        'technician_signed_at' => 'datetime',
        'sort_order'           => 'integer',
    ];

    // ─── Relationships ─────────────────────────────────────────

    public function record(): BelongsTo
    {
        return $this->belongsTo(GroundingReportRecord::class, 'grounding_report_record_id');
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
