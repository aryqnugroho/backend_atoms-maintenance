<?php

namespace App\Models\Grounding;

use App\Models\LocalUser;
use App\Traits\HasSignature;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * GroundingReportRecord — header for one Grounding Report form.
 *
 * Signature contract (mirrors TfpAobGroundRecord):
 *   - Required roles: every technician on duty for this shift, plus manager
 *     and supervisor when present.
 *   - Manager / Supervisor signatures live as columns on this model.
 *   - Technician signatures live on grounding_report_technicians.
 *   - HasSignature trait handles only manager + supervisor here.
 *     Technician signing is handled by the service directly.
 *
 * Unlike TFP AOB Ground, multiple records per (date, shift_type) are allowed
 * because different equipment can be checked in the same shift.
 */
class GroundingReportRecord extends Model
{
    use HasSignature, SoftDeletes;

    protected $table = 'grounding_report_records';

    protected $fillable = [
        'report_number',
        'date',
        'day_name',
        'time_filled',
        'shift_type',
        'work_unit',
        'equipment_name',
        'equipment_location',
        'status',
        'manager_id',
        'manager_name',
        'manager_signature',
        'manager_signed_by',
        'manager_signed_at',
        'manager_signed_by_name',
        'manager_signed_by_role',
        'supervisor_id',
        'supervisor_name',
        'supervisor_signature',
        'supervisor_signed_by',
        'supervisor_signed_at',
        'supervisor_signed_by_name',
        'supervisor_signed_by_role',
        'created_by_id',
        'created_by_name',
    ];

    protected $casts = [
        'date'                 => 'date:Y-m-d',
        'manager_signed_at'    => 'datetime',
        'supervisor_signed_at' => 'datetime',
    ];

    public const STATUSES    = ['ongoing', 'on_hold', 'completed'];
    public const SHIFT_TYPES = ['pagi', 'siang', 'malam'];

    // ─── Relationships ─────────────────────────────────────────

    public function manager(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'manager_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'supervisor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'created_by_id');
    }

    public function technicians(): HasMany
    {
        return $this->hasMany(GroundingReportTechnician::class, 'grounding_report_record_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GroundingReportItem::class, 'grounding_report_record_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    // ─── HasSignature integration ──────────────────────────────

    public function signatureRoleMap(): array
    {
        return [
            'manager' => [
                'name'      => 'manager_name',
                'signature' => 'manager_signature',
                'signed_at' => 'manager_signed_at',
                'signed_by' => 'manager_signed_by',
            ],
            'supervisor' => [
                'name'      => 'supervisor_name',
                'signature' => 'supervisor_signature',
                'signed_at' => 'supervisor_signed_at',
                'signed_by' => 'supervisor_signed_by',
            ],
        ];
    }

    public function requiredSignatureRoles(): array
    {
        $roles = [];
        if (!empty($this->manager_name)) {
            $roles[] = 'manager';
        }
        if (!empty($this->supervisor_name)) {
            $roles[] = 'supervisor';
        }
        return $roles;
    }

    /**
     * Override isComplete() to also require every technician row to be signed.
     */
    public function isComplete(): bool
    {
        if (!empty($this->getPendingSignatures())) {
            return false;
        }

        $unsignedTech = $this->technicians()
            ->whereNull('technician_signature')
            ->exists();

        return !$unsignedTech;
    }

    public function isShiftEnded(): bool
    {
        if (!$this->date || !$this->shift_type) {
            return false;
        }

        try {
            /** @var \App\Services\RosteringIntegrationService $service */
            $service = app(\App\Services\RosteringIntegrationService::class);
            return $service->isShiftEnded(
                $this->shift_type,
                $this->date->format('Y-m-d')
            );
        } catch (\Throwable) {
            $shiftEnds = ['pagi' => '13:00', 'siang' => '19:00', 'malam' => '07:00'];
            if (!isset($shiftEnds[$this->shift_type])) {
                return false;
            }
            $endDate = $this->date->copy();
            if ($this->shift_type === 'malam') {
                $endDate = $endDate->addDay();
            }
            $shiftEnd = Carbon::parse($endDate->format('Y-m-d') . ' ' . $shiftEnds[$this->shift_type]);
            return now()->greaterThanOrEqualTo($shiftEnd);
        }
    }

    // ─── Scopes ────────────────────────────────────────────────

    public function scopeByDate($q, string $date)
    {
        return $q->whereDate('date', $date);
    }

    public function scopeByShift($q, string $shift)
    {
        return $q->where('shift_type', $shift);
    }
}
