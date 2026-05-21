<?php

namespace App\Models\Cnsd;

use App\Models\LocalUser;
use App\Traits\HasSignature;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CnsdReadinessRecord — header for one CNSD readiness form.
 *
 * Currently scoped to Form EQ-1 ("Kesiapan Peralatan CNSD") only. The schema
 * is form-agnostic so additional CNSD form types (Radar, Recorder, etc.) can
 * reuse the same table by passing a different `form_type`.
 *
 * Signature contract (mirrors Work Order):
 *   - Required roles: every technician on duty for this shift, plus manager
 *     and supervisor when present.
 *   - Manager / Supervisor signatures live as columns on this model.
 *   - Technician signatures live on the related `cnsd_readiness_technicians`.
 *   - HasSignature trait handles only manager + supervisor here.
 *     Technician signing is handled by the service directly because each
 *     technician has their own row.
 */
class CnsdReadinessRecord extends Model
{
    use HasSignature, SoftDeletes;

    protected $table = 'cnsd_readiness_records';

    protected $fillable = [
        'form_number',
        'form_type',
        'facility',
        'date',
        'shift_type',
        'location',
        'room',
        'sections_meta',
        'status',
        'manager_id',
        'manager_name',
        'manager_signature',
        'manager_signed_by',
        'manager_signed_at',
        'supervisor_id',
        'supervisor_name',
        'supervisor_signature',
        'supervisor_signed_by',
        'supervisor_signed_at',
        'created_by_id',
        'created_by_name',
    ];

    protected $casts = [
        'date'                 => 'date:Y-m-d',
        'sections_meta'        => 'array',
        'manager_signed_at'    => 'datetime',
        'supervisor_signed_at' => 'datetime',
    ];

    public const STATUSES   = ['ongoing', 'on_hold', 'completed'];
    public const SHIFT_TYPES = ['pagi', 'siang', 'malam'];
    public const FACILITIES  = ['CNSD'];
    public const FORM_TYPES  = ['EQ-1'];

    // ─── Relationships ─────────────────────────────────────────

    public function manager(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'manager_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'supervisor_id');
    }

    public function managerSigner(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'manager_signed_by');
    }

    public function supervisorSigner(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'supervisor_signed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'created_by_id');
    }

    public function technicians(): HasMany
    {
        return $this->hasMany(CnsdReadinessTechnician::class, 'readiness_record_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CnsdReadinessItem::class, 'readiness_record_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    // ─── HasSignature integration ──────────────────────────────

    /**
     * Map signature roles to columns on this model.
     * Technician signing is handled separately (per-row in the technicians table).
     */
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

    /**
     * Required roles for completion:
     *   - manager   when manager_name is cached
     *   - supervisor when supervisor_name is cached
     *   - every technician (handled in isComplete override below)
     */
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

    /**
     * Shift-end logic mirrors Work Order: rostering shift_times when available,
     * fallback to hardcoded shift ends.
     */
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

    public function scopeByFormType($q, string $formType)
    {
        return $q->where('form_type', $formType);
    }

    public function scopeByDate($q, string $date)
    {
        return $q->whereDate('date', $date);
    }

    public function scopeByShift($q, string $shift)
    {
        return $q->where('shift_type', $shift);
    }
}
