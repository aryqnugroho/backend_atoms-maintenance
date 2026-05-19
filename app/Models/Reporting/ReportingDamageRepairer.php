<?php

namespace App\Models\Reporting;

use App\Models\LocalUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ReportingDamageRepairer — one row per Pelaksana Perbaikan in a damage report.
 * Repairer signatures live here (immutable).
 *
 * Repairers are picked manually by the user from CNSD/TFP technicians and
 * supervisors — they are NOT auto-pulled from the rostering shift.
 */
class ReportingDamageRepairer extends Model
{
    protected $table = 'reporting_damage_repairers';

    protected $fillable = [
        'report_id',
        'person_id',
        'person_name',
        'person_role',
        'person_division',
        'signature',
        'signed_by',
        'signed_at',
        'signed_by_name',
        'signed_by_role',
        'sort_order',
    ];

    protected $casts = [
        'signed_at'  => 'datetime',
        'sort_order' => 'integer',
    ];

    // ─── Relationships ─────────────────────────────────────────

    public function report(): BelongsTo
    {
        return $this->belongsTo(ReportingDamageReport::class, 'report_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'person_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'signed_by');
    }
}
