<?php

namespace App\Models\Tfp;

use App\Models\LocalUser;
use App\Traits\HasSignature;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * TfpAobGroundRecord — header for one TFP Performance Check AOB Lantai Ground form.
 *
 * Signature contract (mirrors CnsdRecorderMeterRecord):
 *   - Required roles: every technician on duty for this shift, plus manager
 *     and supervisor when present.
 *   - Manager / Supervisor signatures live as columns on this model.
 *   - Technician signatures live on tfp_aob_ground_technicians.
 *   - HasSignature trait handles only manager + supervisor here.
 *     Technician signing is handled by the service directly.
 */
class TfpAobGroundRecord extends Model
{
    use HasSignature, SoftDeletes;

    protected $table = 'tfp_aob_ground_records';

    protected $fillable = [
        'form_number',
        'form_type',
        'date',
        'day_name',
        'time_filled',
        'shift_type',
        'location',
        'columns_config',
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
        'columns_config'       => 'array',
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
        return $this->hasMany(TfpAobGroundTechnician::class, 'aob_ground_record_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TfpAobGroundItem::class, 'aob_ground_record_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(TfpAobGroundFacility::class, 'aob_ground_record_id')
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
