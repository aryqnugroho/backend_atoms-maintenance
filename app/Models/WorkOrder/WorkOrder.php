<?php

namespace App\Models\WorkOrder;

use App\Models\LocalUser;
use App\Traits\HasSignature;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    use HasSignature, SoftDeletes;

    protected $table = 'work_orders';

    protected $fillable = [
        'wo_number',
        'wo_type',
        'division',
        'shift_type',
        'shift_date',
        'shift_id',
        'description',
        'status',
        'manager_id',
        'supervisor_id',
        'assigned_technician_id',
        'has_supervisor',
        'manager_name_snapshot',
        'supervisor_name_snapshot',
        'mt_name',
        'mt_signature',
        'mt_signed_by',
        'mt_signed_at',
        'supervisor_name',
        'supervisor_signature',
        'supervisor_signed_by',
        'supervisor_signed_at',
        'technician_name',
        'technician_signature',
        'technician_signed_by',
        'technician_signed_at',
        'start_time',
        'end_time',
        'completion_status',
        'notes_kendala',
        'notes_usulan',
        'notes_pemberi_tugas',
        'created_by',
        'closed_at',
    ];

    protected $casts = [
        'shift_date' => 'date:Y-m-d',
        'has_supervisor' => 'boolean',
        'mt_signed_at' => 'datetime',
        'supervisor_signed_at' => 'datetime',
        'technician_signed_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /**
     * Valid work order statuses (aligned with frontend).
     */
    public const STATUSES = ['completed', 'on_hold', 'ongoing'];

    /**
     * Valid work order types.
     *
     * - shift: standard division shift work order, auto-fills personnel from rostering.
     * - personal: ad-hoc WO targeted at a single technician.
     * - gm_directive: directive issued by General Manager to a Manager Teknik
     *   (and optionally a Supervisor). No technician personnel assigned.
     */
    public const TYPES = ['shift', 'personal', 'gm_directive'];

    /**
     * Valid divisions.
     */
    public const DIVISIONS = ['CNSD', 'TFP'];

    /**
     * Valid shift types.
     */
    public const SHIFT_TYPES = ['pagi', 'siang', 'malam'];

    /**
     * Valid completion statuses.
     */
    public const COMPLETION_STATUSES = ['selesai', 'belum_selesai_dilanjut', 'tidak_bisa'];

    // ─── Relationships ─────────────────────────────────────────

    public function manager(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'manager_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'supervisor_id');
    }

    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'assigned_technician_id');
    }

    public function mtSigner(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'mt_signed_by');
    }

    public function supervisorSigner(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'supervisor_signed_by');
    }

    public function technicianSigner(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'technician_signed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(LocalUser::class, 'created_by');
    }

    public function personnel(): HasMany
    {
        return $this->hasMany(WorkOrderPersonnel::class, 'work_order_id');
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(WorkOrderOutput::class, 'work_order_id');
    }

    public function requiredSignatureRoles(): array
    {
        // GM directive: no signatures required. The directive is "complete"
        // when the GM marks it (or the shift ends → on_hold).
        if ($this->wo_type === 'gm_directive') {
            return [];
        }

        return $this->has_supervisor
            ? ['mt', 'supervisor', 'technician']
            : ['mt', 'technician'];
    }

    public function isShiftEnded(): bool
    {
        if (!$this->shift_date || !$this->shift_type) {
            return false;
        }

        // Use RosteringIntegrationService for real shift times when available.
        // Falls back to hardcoded times if rostering DB is unavailable.
        try {
            /** @var \App\Services\RosteringIntegrationService $service */
            $service = app(\App\Services\RosteringIntegrationService::class);
            return $service->isShiftEnded(
                $this->shift_type,
                $this->shift_date->format('Y-m-d')
            );
        } catch (\Exception $e) {
            // Fallback: hardcoded shift end times
            $shiftEnds = [
                'pagi'  => '13:00',
                'siang' => '19:00',
                'malam' => '07:00',
            ];

            if (!isset($shiftEnds[$this->shift_type])) {
                return false;
            }

            $endDate = $this->shift_date->copy();
            if ($this->shift_type === 'malam') {
                $endDate = $endDate->addDay();
            }

            $shiftEnd = \Carbon\Carbon::parse(
                $endDate->format('Y-m-d') . ' ' . $shiftEnds[$this->shift_type]
            );

            return now()->greaterThanOrEqualTo($shiftEnd);
        }
    }

    // ─── Scopes ────────────────────────────────────────────────

    public function scopeByDivision($query, string $division)
    {
        return $query->where('division', $division);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByShiftDate($query, string $date)
    {
        return $query->where('shift_date', $date);
    }

    public function scopeByShiftType($query, string $shiftType)
    {
        return $query->where('shift_type', $shiftType);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('wo_type', $type);
    }

    /**
     * Scope to filter work orders visible to a given Teknisi.
     * Teknisi can only see WOs they are assigned to.
     */
    public function scopeVisibleToTeknisi($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('assigned_technician_id', $userId)
              ->orWhereHas('personnel', function ($sub) use ($userId) {
                  $sub->where('user_id', $userId);
              });
        });
    }
}
